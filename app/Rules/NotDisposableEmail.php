<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class NotDisposableEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !str_contains($value, '@')) {
            return;
        }

        $domain = strtolower(substr($value, strrpos($value, '@') + 1));

        /** @var array<int, string> $disposableDomains */
        $disposableDomains = config('disposable_emails', []);

        if (in_array($domain, $disposableDomains, true)) {
            $fail('Tek kullanımlık e-posta adresleri kabul edilmemektedir.');
        }
    }
}
