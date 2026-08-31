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
 * API isteğinin dili.
 *
 * Ön yüzde dil adresin ilk parçasında (/en/blog) taşınıyor; API'de böyle bir
 * parça yok ve oturum da yok — istek durumsuz. Dil bu yüzden başlıktan
 * okunuyor:
 *
 *   1. `?lang=en` — istemci bir seçim dayattıysa (uygulama içi dil menüsü)
 *   2. `X-Locale: en` — aynı şeyin başlık hâli
 *   3. `Accept-Language: en-GB,en;q=0.9,tr;q=0.8` — cihazın kendi dili
 *   4. sitenin varsayılan dili
 *
 * Desteklenmeyen bir kod 404 değil, yok sayılır: mobil uygulama cihazın dilini
 * gönderir ve o dil sitede yoksa kullanıcı hata değil, varsayılan dilde içerik
 * görmeli.
 *
 * Sonuç `Content-Language` ile geri bildiriliyor — istemci hangi dile
 * düşüldüğünü tahmin etmek zorunda kalmasın.
 */
final class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale);

        // Menü bağlantıları ve içerik adresleri route() ile kuruluyor; ön yüz
        // rotaları {locale} parçasını bekliyor. Doldurulmazsa API'nin döndürdüğü
        // her bağlantı sitenin varsayılan diline işaret ederdi.
        URL::defaults(['locale' => $locale]);

        $response = $next($request);

        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function resolve(Request $request): string
    {
        $languages = app(LanguageService::class);

        foreach ([$request->query('lang'), $request->header('X-Locale')] as $explicit) {
            if (is_string($explicit) && $languages->isSupported(strtolower(trim($explicit)))) {
                return strtolower(trim($explicit));
            }
        }

        // Accept-Language'ı q değerleriyle birlikte çözen kod ön yüzle ortak:
        // "de-DE" sitedeki "de" ile eşleşiyor, iki yerde ayrı yazılsaydı biri
        // düzelip öteki geride kalırdı.
        return app(LocaleResolver::class)->fromBrowser($request)
            ?? $languages->defaultCode();
    }
}
