<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\RecaptchaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class RecaptchaRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $service = app(RecaptchaService::class);

        if (! $service->isEnabled()) {
            return;
        }

        if (! $service->verify($value, request()->ip())) {
            // Kural ön yüzdeki iletişim ve yorum formlarında çalışıyor; metin
            // koda gömülü olduğu için İngilizce sayfada Türkçe uyarı çıkıyordu.
            $fail(__('site.forms.recaptcha'));
        }
    }
}
