<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\LanguageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides which language the visitor sees.
 *
 * Order of preference:
 *   1. A language the visitor picked from the switcher (kept in the session)
 *   2. The best match from the browser's Accept-Language header
 *   3. The default language
 *
 * Only active languages count; anything else falls through to the default, so a
 * visitor never lands on a language the site does not publish.
 */
final class SetLocale
{
    public const SESSION_KEY = 'app_locale';

    public function __construct(
        private readonly LanguageService $languages,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolveLocale($request));

        // The timezone is applied in AppServiceProvider instead: doing it here
        // covered web requests only, so anything the scheduler wrote — backups,
        // campaign send times, analytics — used a different timezone from the
        // same rows written through the site.

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $chosen = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if (is_string($chosen) && $this->languages->isSupported($chosen)) {
            return $chosen;
        }

        return $this->fromBrowser($request) ?? $this->languages->defaultCode();
    }

    /**
     * Best supported language from Accept-Language, honouring its q-values.
     *
     * "de-DE" matches a site language of "de", so a visitor with a regional
     * variant still gets the language rather than the default.
     */
    private function fromBrowser(Request $request): ?string
    {
        $header = (string) $request->header('Accept-Language', '');

        if ($header === '') {
            return null;
        }

        $supported = $this->languages->activeCodes();
        $candidates = [];

        foreach (explode(',', $header) as $part) {
            $bits = explode(';q=', trim($part));
            $tag = strtolower(trim($bits[0]));

            if ($tag === '' || $tag === '*') {
                continue;
            }

            $quality = isset($bits[1]) ? (float) $bits[1] : 1.0;
            $candidates[] = ['tag' => $tag, 'quality' => $quality];
        }

        usort($candidates, static fn (array $a, array $b): int => $b['quality'] <=> $a['quality']);

        foreach ($candidates as $candidate) {
            if (in_array($candidate['tag'], $supported, true)) {
                return $candidate['tag'];
            }

            $base = explode('-', $candidate['tag'])[0];

            if (in_array($base, $supported, true)) {
                return $base;
            }
        }

        return null;
    }
}
