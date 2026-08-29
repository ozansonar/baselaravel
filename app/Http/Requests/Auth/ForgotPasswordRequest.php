<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\UserEmail;
use App\Rules\RecaptchaRule;
use App\Services\RecaptchaService;
use Illuminate\Foundation\Http\FormRequest;

final class ForgotPasswordRequest extends FormRequest
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
            'email'                => ['required', 'string', 'email', 'max:' . UserEmail::MAX_LENGTH],
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
            'email.required'                => __('site.forms.email_required'),
            'email.email'                   => __('site.forms.email_invalid'),
            'g-recaptcha-response.required' => __('site.forms.recaptcha'),
        ];
    }
}
