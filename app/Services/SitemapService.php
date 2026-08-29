<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\Page;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The map of the whole site, in every language it publishes.
 *
 * Each language version of a page is a URL of its own here, and every one of
 * them carries the full set of its translations as <xhtml:link> alternates —
 * including a link back to itself, which search engines require before they
 * will trust the set. Content that exists in one language only simply has no
 * alternates: naming a translation that does not exist gets the whole group
 * ignored.
 */
final class SitemapService
{
    private const CACHE_KEY = 'sitemap.urls';

    private const CACHE_TTL = 3600;

    /**
     * Google refuses a <url> block carrying more than a thousand images.
     */
    private const MAX_IMAGES_PER_URL = 1000;

    public function __construct(
        private readonly LanguageService $languages,
    ) {}

    /**
     * @return array<int, array{
     *     loc: string,
     *     lastmod: string,
     *     changefreq: string,
     *     priority: string,
     *     alternates: array<string, string>,
     *     x_default: string|null,
     *     images: array<int, array{loc: string, title: string}>
     * }>
     */
    public function generateUrls(): array
    {
        // Deliberately not scoped to a language: a sitemap lists every
        // language's URLs, so the output must not depend on who asked for it.
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $locales = $this->locales();

            return array_merge(
                $this->staticUrls($locales),
                $this->galleryCategoryUrls($locales),
                $this->pageUrls(),
                $this->blogCategoryUrls(),
                $this->blogPostUrls(),
            );
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, string>
     */
    private function locales(): array
    {
        $codes = $this->languages->activeCodes();

        return $codes === [] ? [$this->languages->defaultCode()] : $codes;
    }

    /**
     * Pages that exist in code rather than in the database — the same set in
     * every language, so they are all translations of one another.
     *
     * @param array<int, string> $locales
     * @return array<int, array<string, mixed>>
     */
    private function staticUrls(array $locales): array
    {
        $latestPost = BlogPost::published()->max('updated_at');
        $lastmod = ($latestPost ? Carbon::parse($latestPost) : now())->toW3cString();

        $galleryImages = $this->galleryImages($locales);

        $routes = [
            ['name' => 'home',       'priority' => '1.0', 'changefreq' => 'daily'],
            ['name' => 'blog.index', 'priority' => '0.8', 'changefreq' => 'daily'],
            ['name' => 'gallery',    'priority' => '0.6', 'changefreq' => 'weekly'],
            ['name' => 'contact',    'priority' => '0.6', 'changefreq' => 'monthly'],
            ['name' => 'faq',        'priority' => '0.5', 'changefreq' => 'monthly'],
        ];

        $urls = [];

        foreach ($routes as $route) {
            $alternates = [];

            foreach ($locales as $locale) {
                $alternates[$locale] = route($route['name'], ['locale' => $locale]);
            }

            foreach ($locales as $locale) {
                $urls[] = $this->entry(
                    loc: $alternates[$locale],
                    lastmod: $lastmod,
                    changefreq: $route['changefreq'],
                    priority: $route['priority'],
                    alternates: $alternates,
                    images: $route['name'] === 'gallery' ? ($galleryImages[$locale] ?? []) : [],
                    // Kök adres ziyaretçinin dilini kendisi seçip yönlendiriyor;
                    // Google'ın x-default'tan beklediği tam olarak bu. Öteki
                    // sayfaların böyle bir adresi yok, onlarda varsayılan dil
                    // sürümü x-default kalıyor.
                    xDefault: $route['name'] === 'home' && count($locales) > 1 ? route('root') : null,
                );
            }
        }

        return $urls;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pageUrls(): array
    {
        $pages = Page::published()->get(['id', 'locale', 'lang_group_id', 'slug', 'image', 'title', 'updated_at']);

        return $this->groupedUrls(
            $pages,
            fn (Page $page): string => route('pages.show', ['locale' => $page->locale, 'slug' => $page->slug]),
            changefreq: 'monthly',
            priority: '0.5',
            images: fn (Page $page): array => $this->imageOf($page->image, (string) $page->title),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blogCategoryUrls(): array
    {
        $categories = BlogCategory::active()->get(['id', 'locale', 'lang_group_id', 'slug', 'updated_at']);

        return $this->groupedUrls(
            $categories,
            fn (BlogCategory $category): string => route('blog.category', [
                'locale'       => $category->locale,
                'categorySlug' => $category->slug,
            ]),
            changefreq: 'weekly',
            priority: '0.7',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blogPostUrls(): array
    {
        $posts = BlogPost::published()
            ->with('category:id,slug')
            ->get(['id', 'locale', 'lang_group_id', 'blog_category_id', 'slug', 'image', 'title', 'updated_at'])
            // A post whose category was removed has no address to publish.
            ->filter(fn (BlogPost $post): bool => $post->category !== null);

        return $this->groupedUrls(
            $posts,
            fn (BlogPost $post): string => route('blog.show', [
                'locale'       => $post->locale,
                'categorySlug' => $post->category->slug,
                'slug'         => $post->slug,
            ]),
            changefreq: 'weekly',
            priority: '0.7',
            images: fn (BlogPost $post): array => $this->imageOf($post->image, (string) $post->title),
        );
    }

    /**
     * Turn translated rows into one URL each, every one of them carrying the
     * addresses of its siblings.
     *
     * @param EloquentCollection<int, covariant \Illuminate\Database\Eloquent\Model>|Collection<int, covariant \Illuminate\Database\Eloquent\Model> $rows
     * @param callable(mixed): string $url
     * @param (callable(mixed): array<int, array{loc: string, title: string}>)|null $images
     * @return array<int, array<string, mixed>>
     */
    private function groupedUrls(
        EloquentCollection|Collection $rows,
        callable $url,
        string $changefreq,
        string $priority,
        ?callable $images = null,
    ): array {
        $urls = [];

        foreach ($rows->groupBy('lang_group_id') as $group) {
            $alternates = [];

            foreach ($group as $row) {
                $alternates[$row->locale] = $url($row);
            }

            foreach ($group as $row) {
                $urls[] = $this->entry(
                    loc: $alternates[$row->locale],
                    lastmod: $row->updated_at?->toW3cString() ?? now()->toW3cString(),
                    changefreq: $changefreq,
                    priority: $priority,
                    alternates: $alternates,
                    images: $images === null ? [] : $images($row),
                );
            }
        }

        return $urls;
    }

    /**
     * @param array<string, string> $alternates
     * @param array<int, array{loc: string, title: string}> $images
     * @return array<string, mixed>
     */
    private function entry(
        string $loc,
        string $lastmod,
        string $changefreq,
        string $priority,
        array $alternates,
        array $images = [],
        ?string $xDefault = null,
    ): array {
        // Content published in a single language has nothing to point at, and
        // an alternate set of one says nothing a self-referencing link does not.
        $hasTranslations = count($alternates) > 1;

        return [
            'loc'        => $loc,
            'lastmod'    => $lastmod,
            'changefreq' => $changefreq,
            'priority'   => $priority,
            'alternates' => $hasTranslations ? $alternates : [],
            'x_default'  => $xDefault ?? ($hasTranslations
                ? ($alternates[$this->languages->defaultCode()] ?? null)
                : null),
            'images'     => array_slice($images, 0, self::MAX_IMAGES_PER_URL),
        ];
    }

    /**
     * Galeri sayfasının ilk sayfasında görünen fotoğraflar, dil dil.
     *
     * Sayfa artık sayfalanıyor: /galeri adresinde sadece ilk on kare var.
     * Sitemap bütün galeriyi o adresin altında ilan etseydi, işaret ettiği
     * sayfada olmayan görselleri duyururdu.
     *
     * @param array<int, string> $locales
     * @return array<string, array<int, array{loc: string, title: string}>>
     */
    private function galleryImages(array $locales): array
    {
        $result = [];

        foreach ($this->visibleGalleryItems($locales) as $locale => $items) {
            $result[$locale] = $this->imagesOf($items->take(GalleryService::FRONT_PER_PAGE));
        }

        return $result;
    }

    /**
     * Galeri kategorilerinin süzülmüş adresleri.
     *
     * Kategori süzgeci ekranda gerçek bağlantılar üretiyor: kendi başlığı,
     * kendi H1'i ve kendini gösteren canonical'ı olan ayrı sayfalar. Sitemap
     * dışında kalsalardı arama motoru onlara ancak galeri sayfasından
     * gezinerek ulaşırdı.
     *
     * Diller birbirine bağlanıyor: /tr/galeri?kategori=ofis ile
     * /en/galeri?kategori=office aynı kategorinin iki sürümü.
     *
     * @param array<int, string> $locales
     * @return array<int, array<string, mixed>>
     */
    private function galleryCategoryUrls(array $locales): array
    {
        $categories = GalleryCategory::active()->get(['id', 'locale', 'lang_group_id', 'slug', 'updated_at']);

        if ($categories->isEmpty()) {
            return [];
        }

        $itemsByLocale = $this->visibleGalleryItems($locales);
        $urls = [];

        foreach ($categories->groupBy('lang_group_id') as $group) {
            $alternates = [];

            foreach ($group as $category) {
                if (! in_array($category->locale, $locales, true)) {
                    continue;
                }

                $alternates[$category->locale] = route('gallery', [
                    'locale'   => $category->locale,
                    'kategori' => $category->slug,
                ]);
            }

            foreach ($group as $category) {
                if (! isset($alternates[$category->locale])) {
                    continue;
                }

                /** @var Collection<int, GalleryItem> $ownItems */
                $ownItems = ($itemsByLocale[$category->locale] ?? collect())
                    ->filter(fn (GalleryItem $item): bool => $item->categoryGroupId === $category->lang_group_id);

                // İçi boş kategori sitemap'e girmiyor: arama motoruna boş bir
                // sayfa göstermek soft-404 sayılıyor. Kategoriye kare eklenince
                // önbellek düştüğü için adres kendiliğinden geri geliyor.
                if ($ownItems->isEmpty()) {
                    continue;
                }

                $urls[] = $this->entry(
                    loc: $alternates[$category->locale],
                    lastmod: $category->updated_at?->toW3cString() ?? now()->toW3cString(),
                    changefreq: 'weekly',
                    priority: '0.5',
                    alternates: $alternates,
                    images: $this->imagesOf($ownItems->take(GalleryService::FRONT_PER_PAGE)),
                );
            }
        }

        return $urls;
    }

    /**
     * Galeri sayfasının her dilde gösterdiği kareler, ekrandaki sırayla.
     *
     * Sayfa kendi dilinde olmayan kareyi varsayılan dilden düşürerek
     * gösteriyor; sitemap de aynı kümeyi tarif etmeli, yoksa sayfada olmayanı
     * duyurur. Kategori kimliği değil çeviri grubu taşınıyor: süzgeç de öyle
     * çalışıyor.
     *
     * @param array<int, string> $locales
     * @return array<string, Collection<int, GalleryItem>>
     */
    private function visibleGalleryItems(array $locales): array
    {
        $items = GalleryItem::active()
            ->photos()
            ->orderBy('sort_order')
            ->get(['id', 'locale', 'lang_group_id', 'gallery_category_id', 'title', 'image']);

        if ($items->isEmpty()) {
            return array_fill_keys($locales, collect());
        }

        $categoryGroups = GalleryCategory::query()
            ->whereIn('id', $items->pluck('gallery_category_id')->filter()->unique())
            ->pluck('lang_group_id', 'id');

        $items->each(function (GalleryItem $item) use ($categoryGroups): void {
            // Süzgeç kategoriyi çeviri grubuyla eşliyor; karenin hangi gruba
            // ait olduğu burada bir kez çözülüyor, kategori başına yeniden değil.
            $item->categoryGroupId = $categoryGroups->get($item->gallery_category_id);
        });

        $default = $this->languages->defaultCode();
        $byLocale = $items->groupBy('locale');
        $result = [];

        foreach ($locales as $locale) {
            /** @var Collection<int, GalleryItem> $own */
            $own = $byLocale->get($locale, collect());
            $translated = $own->pluck('lang_group_id')->all();

            /** @var Collection<int, GalleryItem> $fallback */
            $fallback = $locale === $default
                ? collect()
                : $byLocale->get($default, collect())
                    ->reject(fn (GalleryItem $item): bool => in_array($item->lang_group_id, $translated, true));

            $result[$locale] = $own->concat($fallback)->sortBy('sort_order')->values();
        }

        return $result;
    }

    /**
     * @param  Collection<int, GalleryItem> $items
     * @return array<int, array{loc: string, title: string}>
     */
    private function imagesOf(Collection $items): array
    {
        return $items
            ->flatMap(fn (GalleryItem $item): array => $this->imageOf($item->image, (string) $item->title))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{loc: string, title: string}>
     */
    private function imageOf(?string $path, string $title): array
    {
        if ($path === null || $path === '') {
            return [];
        }

        return [[
            'loc'   => url(upload_url($path)),
            'title' => $title,
        ]];
    }
}
