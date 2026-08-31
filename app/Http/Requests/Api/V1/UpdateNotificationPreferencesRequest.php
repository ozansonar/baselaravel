<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\NotificationPreference;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bildirim tercihlerinin güncellenmesi.
 *
 * Anahtarlar enum'dan doğrulanıyor: tanınmayan bir tür sessizce yok sayılsaydı
 * istemci "kapattım" sanır, posta gelmeye devam ederdi.
 */
final class UpdateNotificationPreferencesRequest extends FormRequest
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
            'newsletter'    => ['nullable', 'boolean'],
            'preferences'   => ['nullable', 'array'],
            'preferences.*' => ['boolean'],
        ];
    }

    /**
     * Enum'da olmayan anahtar 422 ile geri dönüyor.
     */
    public function after(): array
    {
        return [
            function (\Illuminate\Validation\Validator $validator): void {
                $allowed = array_map(
                    fn (NotificationPreference $type): string => $type->value,
                    NotificationPreference::cases(),
                );

                foreach (array_keys((array) $this->input('preferences', [])) as $key) {
                    if (! in_array($key, $allowed, true)) {
                        $validator->errors()->add('preferences.' . $key, __('api.common.invalid_field'));
                    }
                }
            },
        ];
    }
}
