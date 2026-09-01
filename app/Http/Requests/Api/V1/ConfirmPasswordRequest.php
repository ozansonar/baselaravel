<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

/**
 * Şifre onayı isteyen işlemler için ortak istek.
 *
 * İki adımlı doğrulamayı kapatmak ve kurtarma kodlarını yenilemek jeton tek
 * başına yapılabilecek işler değil: telefonu birkaç dakika eline geçiren biri,
 * açık uygulamadan hesabın ikinci adımını sessizce kaldırabilseydi 2FA'nın
 * koruduğu şey kalmazdı.
 *
 * {@see CloseAccountRequest} ile aynı deseni izliyor; ayrı sınıf çünkü hata
 * mesajı işleme göre değişebilmeli ve ikisinin kuralları birbirine bağlı
 * kalmamalı.
 */
final class ConfirmPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if ($user === null || ! Hash::check((string) $this->input('password'), (string) $user->password)) {
                $validator->errors()->add('password', __('site.account.current_password_wrong'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => __('site.account.current_password_required'),
        ];
    }
}
