<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Redirect;
use App\Services\CustomRouteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Site içi bir bağlantının gerçekten bir yere çıkıp çıkmadığını söyler.
 *
 * Soruyu iki katmanda yanıtlıyor, çünkü tek katman yetmiyor:
 *
 *  1. **Rota var mı?** Adres hiçbir rotaya uymuyorsa kırık — burada iş biter.
 *  2. **İçerik var mı?** Uyduğu rota bir içerik rotasıysa (`/tr/{slug}` gibi)
 *     eşleşme tek başına bir şey söylemiyor: `/tr/olmayan-sayfa` da o kalıba
 *     uyuyor. O yüzden slug'ın karşılığı ayrıca aranıyor.
 *
 * Panelden açılmış adresler ve yönlendirmeler de hesaba katılıyor — ikisi de
 * ziyaretçiyi bir yere götürüyor, yani bağlantı kırık değil. Denetleyicinin
 * kendi adres listesini tutmamasının sebebi bu: tutsaydı panelden açılan yeni
 * bir adres orada görünmez ve sağlam bağlantı kırık sanılırdı.
 *
 * Bütün bağlantılar tek seferde çözümleniyor ve gereken slug'lar tek sorguda
 * çekiliyor: yüz bağlantılı bir sayfa yüz sorgu açmıyor.
 */
final class LinkTargetResolver
{
    /** Slug'ın içerik karşılığı aranan rotalar. */
    private const CONTENT_ROUTES = [
        'pages.show'    => 'page',
        'blog.show'     => 'blog_post',
        'blog.category' => 'blog_category',
    ];

    public function __construct(
        private readonly CustomRouteService $customRoutes,
    ) {}

    /**
     * Hangi bağlantılar hiçbir yere çıkmıyor?
     *
     * @param  list<string> $hrefs
     * @return list<string> Kırık olanlar, geldikleri hâliyle
     */
    public function broken(array $hrefs): array
    {
        $candidates = [];

        foreach (array_unique($hrefs) as $href) {
            $path = $this->internalPath($href);

            if ($path !== null) {
                $candidates[$href] = $path;
            }
        }

        if ($candidates === []) {
            return [];
        }

        $needed = ['page' => [], 'blog_post' => [], 'blog_category' => []];
        $resolved = [];

        foreach ($candidates as $href => $path) {
            $match = $this->matchRoute($path);

            if ($match === null) {
                // Rota yok; panelden açılmış bir adres ya da yönlendirme olabilir.
                $resolved[$href] = $this->knownOutsideRouter($path) ? true : false;

                continue;
            }

            [$routeName, $parameters] = $match;
            $kind = self::CONTENT_ROUTES[$routeName] ?? null;

            if ($kind === null) {
                // İçerik rotası değil: eşleşmesi yeterli.
                $resolved[$href] = true;

                continue;
            }

            $slug = $this->contentSlug($kind, $parameters);

            if ($slug === null) {
                $resolved[$href] = true;

                continue;
            }

            $needed[$kind][] = $slug;
            $resolved[$href] = ['kind' => $kind, 'slug' => $slug, 'path' => $path];
        }

        $existing = $this->existingSlugs($needed);
        $broken = [];

        foreach ($resolved as $href => $state) {
            if ($state === true) {
                continue;
            }

            if ($state === false) {
                $broken[] = (string) $href;

                continue;
            }

            if (in_array($state['slug'], $existing[$state['kind']], true)) {
                continue;
            }

            // İçerik yok ama panelden açılmış bir adres ya da yönlendirme
            // olabilir; ziyaretçi yine bir yere gidiyor.
            if (! $this->knownOutsideRouter($state['path'])) {
                $broken[] = (string) $href;
            }
        }

        return $broken;
    }

    /**
     * Bağlantı site içi mi? Değilse null.
     *
     * Dış adresler, e-posta ve telefon bağlantıları, çapa ve JavaScript
     * bağlantıları denetimin dışında: hiçbirinin varlığı buradan bilinemez.
     */
    private function internalPath(string $href): ?string
    {
        $href = trim($href);

        if ($href === '' || Str::startsWith($href, ['#', 'mailto:', 'tel:', 'javascript:', 'data:'])) {
            return null;
        }

        if (Str::startsWith($href, ['http://', 'https://', '//'])) {
            $host = parse_url(Str::start($href, ''), PHP_URL_HOST);
            $own = parse_url((string) config('app.url'), PHP_URL_HOST);

            if (! is_string($host) || ! is_string($own) || Str::lower($host) !== Str::lower($own)) {
                return null;
            }
        }

        $path = parse_url($href, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        // Yüklenen dosyalar diskten geliyor, rota tablosunda karşılıkları yok.
        if (Str::startsWith(ltrim($path, '/'), ['uploads/', 'assets/', 'storage/'])) {
            return null;
        }

        return '/' . trim(rawurldecode($path), '/');
    }

    /**
     * Yolu rota tablosuna sorar.
     *
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    private function matchRoute(string $path): ?array
    {
        try {
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (\Throwable) {
            return null;
        }

        $name = $route->getName();

        // Fallback rotası her şeyi yakalıyor; "eşleşti" demek yanıltıcı olurdu.
        if ($name === null || $route->isFallback) {
            return null;
        }

        return [$name, $route->parameters()];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function contentSlug(string $kind, array $parameters): ?string
    {
        $key = $kind === 'blog_category' ? 'categorySlug' : 'slug';
        $value = $parameters[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Panelden açılmış adres ya da tanımlı yönlendirme.
     */
    private function knownOutsideRouter(string $path): bool
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $s): bool => $s !== ''));

        if ($segments !== []) {
            // İlk parça dil kodu olabilir; özel adresler dile göre tanımlı.
            $locale = count($segments) > 1 ? $segments[0] : app()->getLocale();
            $slug = count($segments) > 1 ? implode('/', array_slice($segments, 1)) : $segments[0];

            if ($this->customRoutes->resolve($locale, $slug) !== null) {
                return true;
            }
        }

        return Redirect::query()
            ->where('is_active', true)
            ->whereIn('old_url', [$path, ltrim($path, '/')])
            ->exists();
    }

    /**
     * Aranan slug'ların gerçekten var olanları — tür başına tek sorgu.
     *
     * @param  array{page: list<string>, blog_post: list<string>, blog_category: list<string>} $needed
     * @return array{page: list<string>, blog_post: list<string>, blog_category: list<string>}
     */
    private function existingSlugs(array $needed): array
    {
        return [
            'page' => $needed['page'] === [] ? [] : Page::query()
                ->whereIn('slug', array_unique($needed['page']))
                ->pluck('slug')->all(),

            'blog_post' => $needed['blog_post'] === [] ? [] : BlogPost::query()
                ->whereIn('slug', array_unique($needed['blog_post']))
                ->pluck('slug')->all(),

            'blog_category' => $needed['blog_category'] === [] ? [] : BlogCategory::query()
                ->whereIn('slug', array_unique($needed['blog_category']))
                ->pluck('slug')->all(),
        ];
    }
}
