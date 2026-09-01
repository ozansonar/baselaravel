<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Tek kullanımlık ("throwaway") e-posta adreslerini eler.
 *
 * `email:rfc,dns` uydurma alan adlarını zaten eliyor; bu kural onun
 * göremediği şeye bakıyor. Tek kullanımlık sağlayıcıların alan adları gerçek,
 * MX kayıtları çalışıyor ve biçimleri kusursuz — kural olarak geçerli, hesap
 * olarak on dakika sonra yok.
 *
 * Liste `config/disposable_emails.php`'de: yeni bir sağlayıcı çıktığında tek
 * satır eklemek yetiyor, koda dokunmak gerekmiyor.
 *
 * Alt alan adları da yakalanıyor (`kutu.10minutemail.com`): sağlayıcılar
 * genelde her kullanıcıya bir alt alan adı veriyor ve yalnız tam eşleşmeye
 * bakan bir liste ilk gün delinirdi.
 *
 * Nerede uygulandığı {@see EmailAddress::rules()} içinde yazılı.
 */
final class NotDisposableEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_contains($value, '@')) {
            return;
        }

        $domain = strtolower(trim(substr($value, strrpos($value, '@') + 1)));

        if ($domain === '') {
            return;
        }

        /** @var array<int, string> $disposable */
        $disposable = config('disposable_emails', []);

        foreach ($disposable as $blocked) {
            $blocked = strtolower((string) $blocked);

            if ($domain === $blocked || str_ends_with($domain, '.' . $blocked)) {
                $fail(__('site.forms.email_disposable'));

                return;
            }
        }
    }
}
