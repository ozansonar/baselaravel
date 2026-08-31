<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

/**
 * Hesabı kapatma isteği — şifre onaylı.
 *
 * Jeton tek başına yetmiyor: telefonu birkaç dakika eline geçiren biri, açık
 * uygulamadan hesabı kapatabilseydi bu geri alınması en zor işlem olurdu.
 */
final class CloseAccountRequest extends FormRequest
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
