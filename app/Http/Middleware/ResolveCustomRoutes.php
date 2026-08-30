<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\CustomRouteType;
use App\Services\CustomRouteService;
use App\Services\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panelden açılmış adresleri karşılar.
 *
 * Rotalar koda gömülüydü; yeni bir adres açmak geliştirici işiydi. Bu katman
 * gelen isteği veritabanındaki adreslerle eşleştiriyor: eşleşme yoksa hiçbir
 * şey yapmadan çekiliyor ve istek normal akışına devam ediyor — yani mevcut
 * hiçbir rota bundan etkilenmiyor.
 *
 * Eşleşme varsa iki şey olabilir: ya hedef sayfa bu adres altında basılıyor
 * (adres çubuğu değişmez), ya da ziyaretçi hedefe yönlendiriliyor.
 *
 * Sıralama önemli: bu katman ön yüzün "/{slug}" yakalayıcısından önce karar
 * vermeli, yoksa /en/contact önce dinamik sayfa olarak aranır ve bulunamayınca
 * 404 verirdi.
 */
final class ResolveCustomRoutes
{
    public function __construct(
        private readonly CustomRouteService $routes,
        private readonly LocaleResolver $locales,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Yalnız okuma istekleri: form gönderimi kendi rotasına gider.
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $locale = $this->locales->forRequest($request);
        $slug = $this->slugOf($request, $locale);

        if ($slug === null) {
            return $next($request);
        }

        $match = $this->routes->resolve($locale, $slug);

        if ($match === null) {
            return $next($request);
        }

        $target = $this->targetUrl($match, $locale);

        if ($target === null) {
            // Hedef rota artık yok (kod değişmiş olabilir). Sessizce normal
            // akışa bırakılıyor: yarım bir yönlendirme yerine olağan 404.
            return $next($request);
        }

        $type = CustomRouteType::tryFrom((string) $match['type']) ?? CustomRouteType::Render;

        if ($type->isRedirect()) {
            return redirect($target, $type->statusCode());
        }

        // Gösterim: adres çubuğu bu adreste kalıyor, içerik hedeften geliyor.
        // İstek hedefin rotasına bağlanıp o rota çalıştırılıyor; ikinci bir
        // HTTP isteği açılmıyor, ara katmanlar bir kez çalışıyor.
        return $this->render($request, $match, $locale);
    }

    /**
     * Adresin dil ön ekinden arındırılmış hâli.
     *
     * Ön ek yoksa (kök adres, dil taşımayan uç noktalar) burası karar
     * vermiyor: o adresler zaten kendi rotalarına ait.
     */
    private function slugOf(Request $request, string $locale): ?string
    {
        $path = trim($request->path(), '/');

        if ($path === '' || $path === $locale) {
            return null;
        }

        $prefix = $locale . '/';

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : null;
    }

    /**
     * @param array<string, mixed> $match
     */
    private function targetUrl(array $match, string $locale): ?string
    {
        if (! RouteFacade::has((string) $match['route'])) {
            return null;
        }

        return route((string) $match['route'], array_merge(
            ['locale' => $locale],
            (array) ($match['params'] ?? []),
        ));
    }

    /**
     * Hedef rotayı bu istek üzerinde çalıştırır.
     *
     * @param array<string, mixed> $match
     */
    private function render(Request $request, array $match, string $locale): Response
    {
        $route = RouteFacade::getRoutes()->getByName((string) $match['route']);

        if ($route === null) {
            return redirect($this->targetUrl($match, $locale) ?? '/');
        }

        $route = clone $route;
        $route->bind($request);

        foreach (array_merge(['locale' => $locale], (array) ($match['params'] ?? [])) as $name => $value) {
            $route->setParameter($name, $value);
        }

        // SetLocale, {locale} parametresini işini bitirince siliyor; burada
        // yeniden bağlanan rota onu tekrar taşımasın diye aynısı yapılıyor.
        $request->setRouteResolver(static fn () => $route);
        $route->forgetParameter('locale');

        return RouteFacade::toResponse($request, $route->run());
    }
}
