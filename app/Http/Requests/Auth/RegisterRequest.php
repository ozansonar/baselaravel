<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\UserEmail;
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
            'email'                => ['required', 'string', 'email', 'max:' . UserEmail::MAX_LENGTH, UserEmail::unique()],
            'phone'                => ['nullable', 'string', 'max:20', 'regex:/^[0-9\s\-\+\(\).]+$/'],
            'password'             => ['required', 'string', Password::min(8), 'confirmed'],
            'g-recaptcha-response' => app(RecaptchaService::class)->isEnabled()
                ? ['required', new RecaptchaRule()]
                : [],
        ];
    }

    /**
     * Uyarı metinleri panelden yönetiliyor (Dil Yazıları).
     *
     * Koda gömülü olduklarında İngilizce ziyaretçi Türkçe uyarı görüyordu ve
     * yönetici metni değiştiremiyordu. Sayılar :min / :max ile kuraldan
     * geliyor: elle yazılan sayı, kural değişince yalan söylüyor — nitekim
     * söylemişti: iletişim formu sınır 191'ken "255" diyordu.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required'           => __('site.forms.first_name_required'),
            'first_name.min'                => __('site.register.first_name_min'),
            'first_name.max'                => __('site.register.first_name_max'),
            'first_name.regex'              => __('site.forms.first_name_letters'),
            'last_name.required'            => __('site.forms.last_name_required'),
            'last_name.min'                 => __('site.register.last_name_min'),
            'last_name.max'                 => __('site.register.last_name_max'),
            'last_name.regex'               => __('site.forms.last_name_letters'),
            'email.required'                => __('site.forms.email_required'),
            'email.email'                   => __('site.forms.email_invalid'),
            'email.unique'                  => __('site.register.email_taken'),
            'phone.regex'                   => __('site.register.phone_digits'),
            'password.required'             => __('site.forms.password_required'),
            'password.min'                  => __('site.forms.password_min'),
            'password.confirmed'            => __('site.register.password_confirmed'),
            'g-recaptcha-response.required' => __('site.forms.recaptcha'),
        ];
    }
}
