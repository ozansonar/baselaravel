<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogCategory;
use App\Models\BlogPost;
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
            'x_default'  => $hasTranslations
                ? ($alternates[$this->languages->defaultCode()] ?? null)
                : null,
            'images'     => array_slice($images, 0, self::MAX_IMAGES_PER_URL),
        ];
    }

    /**
     * The photos the gallery page shows, per language.
     *
     * The page falls back to the default language for anything not translated,
     * so the sitemap has to describe the same set — otherwise it would announce
     * images that are not on the page it points at.
     *
     * @param array<int, string> $locales
     * @return array<string, array<int, array{loc: string, title: string}>>
     */
    private function galleryImages(array $locales): array
    {
        $items = GalleryItem::active()
            ->photos()
            ->orderBy('sort_order')
            ->get(['id', 'locale', 'lang_group_id', 'title', 'image']);

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

            $result[$locale] = $own->concat($fallback)
                ->flatMap(fn (GalleryItem $item): array => $this->imageOf($item->image, (string) $item->title))
                ->values()
                ->all();
        }

        return $result;
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
