<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PushAudience;
use App\Rules\SafeRedirectTarget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Yeni bir push duyurusunun doğrulaması.
 *
 * Uzunluk tavanları ekrandaki `maxSize[n]` değerleriyle ve sütun genişlikleriyle
 * aynı: istemci kuralı sunucudan gevşek olamaz, sunucu son sözü söyler.
 */
class StorePushNotificationRequest extends FormRequest
{
    /** Başlık için karakter tavanı — sütun genişliği ve ekran kuralıyla aynı. */
    public const TITLE_MAX = 120;

    /** Metin için karakter tavanı. */
    public const BODY_MAX = 500;

    /** Bağlantı için karakter tavanı. */
    public const LINK_MAX = 500;

    public function authorize(): bool
    {
        return true; // Yetki denetleyicide, policy üzerinden.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:' . self::TITLE_MAX],
            'body'  => ['required', 'string', 'max:' . self::BODY_MAX],
            // Bildirime tıklandığında açılacak yer. Site içi bir yol ya da
            // izin verilen bir adres; açık yönlendirmeye dönüşmesin diye
            // yönlendirme hedefiyle aynı kuraldan geçiyor.
            'link'  => ['nullable', 'string', 'max:' . self::LINK_MAX, new SafeRedirectTarget('Bağlantı')],

            'audience'    => ['required', Rule::enum(PushAudience::class)],
            'audience_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title'       => 'başlık',
            'body'        => 'metin',
            'link'        => 'bağlantı',
            'audience'    => 'hedef kitle',
            'audience_id' => 'hedef',
        ];
    }

    /**
     * Hedef seçimi kitleye bağlı: "herkes" için ek bir seçim yok, rol ve
     * kullanıcı için zorunlu ve var olan bir kayda işaret etmeli.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $audience = PushAudience::tryFrom((string) $this->input('audience'));

            if ($audience === null || ! $audience->needsTarget()) {
                return;
            }

            $id = $this->input('audience_id');

            if ($id === null || $id === '') {
                $validator->errors()->add('audience_id', $audience === PushAudience::Role
                    ? 'Bir rol seçin.'
                    : 'Bir kullanıcı seçin.');

                return;
            }

            $table = $audience === PushAudience::Role ? 'roles' : 'users';

            $exists = \Illuminate\Support\Facades\DB::table($table)
                ->where('id', (int) $id)
                ->whereNull('deleted_at')
                ->exists();

            if (! $exists) {
                $validator->errors()->add('audience_id', $audience === PushAudience::Role
                    ? 'Seçilen rol bulunamadı.'
                    : 'Seçilen kullanıcı bulunamadı.');
            }
        });
    }

    /**
     * Servisin beklediği biçim.
     *
     * @return array{title: string, body: string, link: ?string, audience: PushAudience, audience_id: ?int, user_id: ?int}
     */
    public function notificationData(): array
    {
        $link = trim((string) $this->input('link', ''));
        $id = $this->input('audience_id');

        return [
            'title'       => $this->string('title')->toString(),
            'body'        => $this->string('body')->toString(),
            'link'        => $link === '' ? null : $link,
            'audience'    => PushAudience::from((string) $this->input('audience')),
            'audience_id' => ($id === null || $id === '') ? null : (int) $id,
            'user_id'     => $this->user()?->getKey(),
        ];
    }
}
