<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;

/**
 * Which language a visitor who has not named one should get.
 *
 * Used by the language-less root URL and by any request outside the localized
 * routes; the localized routes themselves take the language from the URL.
 */
final class LocaleResolver
{
    public function __construct(
        private readonly LanguageService $languages,
    ) {}

    /**
     * İsteğin hangi dili kastettiği — uygulamadan değil, isteğin kendisinden.
     *
     * SetLocale dili kurmadan önce çalışan bir şey (hız sınırlayıcısının yanıt
     * kapanışı gibi) app()->getLocale() ile varsayılan dili görüyor ve
     * ziyaretçiye yanlış dilde yazıyor. Burası yan etkisiz: oturuma yazmıyor,
     * 404 vermiyor, yalnız cevabı söylüyor.
     */
    public function forRequest(Request $request): string
    {
        $fromUrl = $this->fromUrl($request);

        return $fromUrl !== null && $this->languages->isSupported($fromUrl)
            ? $fromUrl
            : $this->fromRequest($request);
    }

    /**
     * Yerelleştirilmiş bir rotanın adresindeki dil kodu.
     *
     * Yalnız okuyor. Kodun geçerli olup olmadığına, oturuma yazılıp
     * yazılmayacağına SetLocale karar veriyor — orada bir istek bir kez
     * işleniyor, burada aynı soru başka yerlerden de sorulabiliyor.
     */
    public function fromUrl(Request $request): ?string
    {
        $route = $request->route();

        // Dili ilk parçasında taşıyan yalnız ön yüz grubu; panelin {locale}
        // parametreleri başka bir şey anlatıyor.
        if ($route === null || ! str_starts_with($route->uri(), '{locale}')) {
            return null;
        }

        $code = $route->parameter('locale');

        return is_string($code) ? $code : null;
    }

    /**
     * Preference order: the language they picked, then the best match from
     * their browser, then the site default.
     */
    public function fromRequest(Request $request): string
    {
        $chosen = $request->hasSession() ? $request->session()->get(SetLocale::SESSION_KEY) : null;

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
    public function fromBrowser(Request $request): ?string
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
