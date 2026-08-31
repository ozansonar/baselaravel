<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\UserEmail;
use Illuminate\Foundation\Http\FormRequest;

final class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Adresin kayıtlı olup olmadığı burada sorulmuyor (`exists` kuralı yok).
     * Sorulsaydı doğrulama hatası, hangi adreslerin sistemde olduğunu tek tek
     * denemeye açık bir kapı olurdu.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:' . UserEmail::MAX_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('site.forms.email_required'),
            'email.email'    => __('site.forms.email_invalid'),
        ];
    }
}
