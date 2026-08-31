<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

/**
 * Hesabın korumasını gevşeten işlemler için şifre onayı.
 *
 * İki adımlı doğrulamayı kapatmak ve kurtarma kodlarını yenilemek bu kapıdan
 * geçiyor: ele geçirilmiş bir oturum, sahibinin ikinci adımını sessizce
 * kaldırabilseydi 2FA'nın koruduğu şey kalmazdı.
 */
final class PasswordConfirmationRequest extends FormRequest
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
            'password' => ['required', 'string'],
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
