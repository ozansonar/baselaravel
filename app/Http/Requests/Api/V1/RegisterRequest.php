<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\UserEmail;
use App\Enums\TokenAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Rules\EmailAddress;

/**
 * API üzerinden kayıt.
 *
 * Kurallar ön yüzdeki {@see \App\Http\Requests\Auth\RegisterRequest} ile
 * birebir aynı — aynı sütunlara yazıyorlar, sunucunun iki ayrı sözü olamaz.
 * Tek fark reCAPTCHA: tarayıcıya bağlı bir denetim, mobil uygulamada karşılığı
 * yok. API tarafında kötüye kullanımı hız sınırı tutuyor (`throttle:api-register`).
 *
 * E-posta benzersizliği {@see UserEmail::unique()} üzerinden: yalnız yaşayan
 * satırlar arasında bakıyor, yani soft-delete ile silinmiş bir hesabın adresi
 * sonsuza dek işgal edilmiş olmuyor.
 */
final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-ZçÇğĞıİöÖşŞüÜ\s]+$/u'],
            'last_name'  => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-ZçÇğĞıİöÖşŞüÜ\s]+$/u'],
            'email'      => ['required', 'string', ...EmailAddress::rules(), 'max:' . UserEmail::MAX_LENGTH, UserEmail::unique()],
            'phone'      => ['nullable', 'string', 'max:20', 'regex:/^[0-9\s\-\+\(\).]+$/'],
            'password'   => ['required', 'string', Password::min(8), 'confirmed'],
            // Jetonun etiketi: kullanıcı "hangi cihazdan girmişim" sorusunu
            // bununla yanıtlıyor. Zorunlu değil, gönderilmezse config'teki ad.
            'device_name' => ['nullable', 'string', 'max:100'],
            // Yetki istemek yalnızca DARALTIR: gönderilmezse jeton tam yetkili
            // olur, gönderilirse yalnız listedekiler verilir. Bu yol hiçbir
            // koşulda `*` üretemez.
            'abilities'   => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::enum(TokenAbility::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => __('site.forms.first_name_required'),
            'first_name.min'      => __('site.register.first_name_min'),
            'first_name.max'      => __('site.register.first_name_max'),
            'first_name.regex'    => __('site.forms.first_name_letters'),
            'last_name.required'  => __('site.forms.last_name_required'),
            'last_name.min'       => __('site.register.last_name_min'),
            'last_name.max'       => __('site.register.last_name_max'),
            'last_name.regex'     => __('site.forms.last_name_letters'),
            'email.required'      => __('site.forms.email_required'),
            'email.email'         => __('site.forms.email_invalid'),
            'email.unique'        => __('site.register.email_taken'),
            'phone.regex'         => __('site.register.phone_digits'),
            'password.required'   => __('site.forms.password_required'),
            'password.min'        => __('site.forms.password_min'),
            'password.confirmed'  => __('site.register.password_confirmed'),
        ];
    }
}
