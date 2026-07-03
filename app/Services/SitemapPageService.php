<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogCategory;
use App\Models\Category;
use App\Models\CityLandingPage;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * HTML site haritası sayfası için tüm grupları (kategorize) cache'li çeker.
 *
 * Sitemap.xml ile farklı amacı vardır:
 *  - sitemap.xml → Google crawler için makine-okunur URL listesi
 *  - /site-haritasi → kullanıcılar + topic siloing için organize HTML
 *
 * Tüm gruplar 1 saat cache'lenir; içerik değişikliklerinde
 * SitemapPageService::clearCache çağrılır (CRUD service'lerinin clearCache
 * pipeline'ında).
 */
final class SitemapPageService
{
    private const string CACHE_KEY = 'sitemap_page.groups';
    private const int CACHE_TTL    = 3600;

    /**
     * Build all groups for /site-haritasi page.
     *
     * @return array{
     *     categories: Collection<int, Category>,
     *     city_pages: Collection<int, CityLandingPage>,
     *     blog_categories: Collection<int, BlogCategory>,
     *     pages: Collection<int, Page>
     * }
     */
    public function buildGroups(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            return [
                // Ürün kategorileri + altındaki aktif ürünler (eager-load,
                // her kategori sayfasında ayrı sorgu açmasın).
                'categories' => Category::query()
                    ->where('is_active', true)
                    ->whereNull('parent_id')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->with([
                        'products' => fn ($q) => $q->active()->sorted()->select('id', 'category_id', 'name', 'slug'),
                    ])
                    ->get(['id', 'name', 'slug']),

                // Aktif şehir landing page'leri (Modül 1)
                'city_pages' => CityLandingPage::query()
                    ->active()
                    ->orderBy('tier')
                    ->orderBy('sort_order')
                    ->orderBy('city_name')
                    ->get(['id', 'city_name', 'city_slug', 'tier']),

                // Blog kategorileri + yayındaki yazılar.
                // Kategori başına en yeni 100 yazı — site büyüdükçe sayfa
                // devasa olmasın (1000+ yazıda performans + UX koruması).
                'blog_categories' => BlogCategory::active()
                    ->sorted()
                    ->with([
                        'posts' => fn ($q) => $q
                            ->where('is_published', true)
                            ->where('published_at', '<=', now())
                            ->orderByDesc('published_at')
                            ->limit(100)
                            ->select('id', 'blog_category_id', 'title', 'slug', 'published_at'),
                    ])
                    ->get(['id', 'name', 'slug']),

                // Statik sayfalar (Hakkımızda, Teslimat, KVKK, vb.)
                'pages' => Page::published()
                    ->sorted()
                    ->get(['id', 'title', 'slug']),
            ];
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
