<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Keeps redirect targets from becoming an open redirect.
 *
 * A relative path on this site is always fine. An absolute URL is only
 * accepted when its host is this application's own host or appears in
 * config('redirects.allowed_hosts').
 */
final class SafeRedirectTarget implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $target = trim($value);

        // A backslash is normalised to a forward slash by some browsers, which
        // turns "/\evil.test" into a protocol-relative URL pointing off-site.
        if (str_contains($target, '\\')) {
            $fail('Yeni URL ters bölü (\\) içeremez.');

            return;
        }

        // Control characters can be used to smuggle a different target past
        // this check while the browser still follows it.
        if (preg_match('/[\x00-\x1F\x7F]/', $target) === 1) {
            $fail('Yeni URL geçersiz karakter içeriyor.');

            return;
        }

        if (str_starts_with($target, '/')) {
            // "//evil.test" is protocol-relative: it leaves the site.
            if (str_starts_with($target, '//')) {
                $fail('Yeni URL çift bölü (//) ile başlayamaz, bu site dışına çıkar.');
            }

            return;
        }

        $host = parse_url($target, PHP_URL_HOST);

        if ($host === false || $host === null) {
            $fail('Yeni URL ya / ile başlayan bir yol ya da tam bir adres olmalıdır.');

            return;
        }

        $scheme = strtolower((string) parse_url($target, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('Yeni URL yalnızca http veya https olabilir.');

            return;
        }

        if (! in_array(strtolower($host), $this->allowedHosts(), true)) {
            $fail(sprintf(
                'Site dışı yönlendirme yapılamaz. "%s" izin verilen adresler arasında değil.',
                $host,
            ));
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedHosts(): array
    {
        $hosts = [];

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($appHost) && $appHost !== '') {
            $hosts[] = strtolower($appHost);
        }

        /** @var array<int, string> $configured */
        $configured = config('redirects.allowed_hosts', []);

        foreach ($configured as $host) {
            $host = strtolower(trim($host));

            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        return array_unique($hosts);
    }
}
