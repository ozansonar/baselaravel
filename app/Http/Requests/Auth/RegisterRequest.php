<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\RecaptchaRule;
use App\Services\RecaptchaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

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
            'first_name'           => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-ZçÇğĞıİöÖşŞüÜ\s]+$/u'],
            'last_name'            => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-ZçÇğĞıİöÖşŞüÜ\s]+$/u'],
            'email'                => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone'                => ['nullable', 'string', 'max:20', 'regex:/^[0-9\s\-\+\(\)]+$/'],
            'password'             => ['required', 'string', Password::min(8), 'confirmed'],
            'g-recaptcha-response' => app(RecaptchaService::class)->isEnabled()
                ? ['required', new RecaptchaRule()]
                : [],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required'              => 'Ad alanı zorunludur.',
            'first_name.min'                   => 'Ad en az 2 karakter olmalıdır.',
            'first_name.max'                   => 'Ad en fazla 50 karakter olabilir.',
            'first_name.regex'                 => 'Ad yalnızca harf ve boşluk içerebilir.',
            'last_name.required'               => 'Soyad alanı zorunludur.',
            'last_name.min'                    => 'Soyad en az 2 karakter olmalıdır.',
            'last_name.max'                    => 'Soyad en fazla 50 karakter olabilir.',
            'last_name.regex'                  => 'Soyad yalnızca harf ve boşluk içerebilir.',
            'email.required'                   => 'E-posta adresi zorunludur.',
            'email.email'                      => 'Geçerli bir e-posta adresi girin.',
            'email.unique'                     => 'Bu e-posta adresi zaten kayıtlı.',
            'phone.regex'                      => 'Telefon numarası yalnızca rakam içerebilir.',
            'password.required'                => 'Şifre zorunludur.',
            'password.min'                     => 'Şifre en az 8 karakter olmalıdır.',
            'password.confirmed'               => 'Şifre tekrarı uyuşmuyor.',
            'g-recaptcha-response.required'    => 'Lütfen robot olmadığınızı doğrulayın.',
        ];
    }
}
