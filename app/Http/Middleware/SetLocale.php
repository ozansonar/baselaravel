<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\LanguageService;
use App\Services\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides which language the visitor sees.
 *
 * Front pages carry their language in the URL (/en/blog), so that segment wins
 * outright — the same address must always show the same language, or search
 * engines and shared links would see whatever the last visitor picked.
 *
 * Requests without such a segment (the bare root, the sitemap, e-mail links)
 * fall back to the visitor's own preference.
 */
final class SetLocale
{
    public const SESSION_KEY = 'app_locale';

    public function __construct(
        private readonly LanguageService $languages,
        private readonly LocaleResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->fromUrl($request) ?? $this->resolver->fromRequest($request);

        app()->setLocale($locale);

        // Every route() call in the app can now stay language-unaware: the
        // {locale} segment is filled in from here.
        URL::defaults(['locale' => $locale]);

        // The timezone is applied in AppServiceProvider instead: doing it here
        // covered web requests only, so anything the scheduler wrote — backups,
        // campaign send times, analytics — used a different timezone from the
        // same rows written through the site.

        return $next($request);
    }

    /**
     * The language segment of a localized route.
     *
     * A code the site does not publish is a dead address rather than a reason
     * to quietly show another language, so it 404s.
     */
    private function fromUrl(Request $request): ?string
    {
        // Adresten kodu okumak LocaleResolver'da: aynı soruyu hız
        // sınırlayıcısı da soruyor ve iki yerde ayrı yazılınca biri değişip
        // öteki geride kalıyor. Karar —geçerli mi, oturuma yazılsın mı—
        // burada kalıyor; o bir isteği bir kez işlemenin işi.
        $code = $this->resolver->fromUrl($request);

        if ($code === null) {
            return null;
        }

        // A fresh install has no language rows yet; the configured default
        // still has to serve pages, or the whole site would 404.
        $supported = $this->languages->isSupported($code)
            || ($this->languages->activeCodes() === [] && $code === $this->languages->defaultCode());

        abort_unless($supported, 404);

        // Remembered so the language-less URLs — the root, e-mail links — keep
        // following the visitor.
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $code);
        }

        // Controller arguments are filled positionally, so the language segment
        // would arrive as the first one — /tr/hakkimizda would look up the page
        // "tr". It has done its job here; URL::defaults puts it back on the way
        // out.
        $request->route()?->forgetParameter('locale');

        return $code;
    }
}
