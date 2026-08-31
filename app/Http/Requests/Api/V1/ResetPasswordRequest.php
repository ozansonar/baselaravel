<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\UserEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ResetPasswordRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:' . UserEmail::MAX_LENGTH],
            // Tam altı hane. Uzunluk ve biçim burada elenirse yanlış kod
            // denemesi doğrulamada durur, sıfırlama akışına hiç girmez.
            'code'     => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required'     => __('site.forms.email_required'),
            'email.email'        => __('site.forms.email_invalid'),
            'code.required'      => __('api.password.code_required'),
            'code.digits'        => __('api.password.code_digits'),
            'password.required'  => __('site.forms.password_required'),
            'password.min'       => __('site.forms.password_min'),
            'password.confirmed' => __('site.register.password_confirmed'),
        ];
    }
}
