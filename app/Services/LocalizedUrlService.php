<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * The address of the page you are on, in every other language.
 *
 * Slugs are per language, so the English version of /tr/hakkimizda is not the
 * same path with another prefix — it is /en/about-us. The language switcher,
 * the hreflang tags and the canonical URL all need that mapping, and all three
 * must agree, so it lives in one place.
 *
 * A language the content has not been translated into is left out rather than
 * pointed at the default: telling a search engine a page exists in English when
 * it does not is worse than saying nothing.
 */
final class LocalizedUrlService
{
    public function __construct(
        private readonly LanguageService $languages,
    ) {}

    /**
     * The current page in every language it is published in.
     *
     * @return array<string, string> locale => absolute URL
     */
    public function alternates(): array
    {
        $route = request()->route();

        if (! $route instanceof Route || ! is_string($route->getName())) {
            return [];
        }

        // Language-agnostic endpoints (sitemap, the switcher itself) have no
        // per-language address to advertise.
        if (! $this->isLocalized($route)) {
            return [];
        }

        return $this->alternatesFor($route->getName(), $route->parameters());
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, string> locale => absolute URL
     */
    public function alternatesFor(string $routeName, array $parameters): array
    {
        $urls = [];

        foreach ($this->languages->active() as $language) {
            $url = $this->route($routeName, $parameters, $language->code);

            if ($url !== null) {
                $urls[$language->code] = $url;
            }
        }

        return $urls;
    }

    /**
     * The current page in one language, or null when it has no version there.
     */
    public function current(string $locale): ?string
    {
        return $this->alternates()[$locale] ?? null;
    }

    /**
     * Map any internal URL onto its counterpart in another language.
     *
     * Used when the visitor switches language from a link that was not built by
     * the switcher itself — the referer of /dil/en, for instance.
     */
    public function fromUrl(?string $url, string $locale): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        // Never bounce a visitor off-site on the strength of a header.
        if ($host !== null && $host !== request()->getHost()) {
            return null;
        }

        try {
            $route = RouteFacade::getRoutes()->match(Request::create($url));
        } catch (\Throwable) {
            return null;
        }

        $name = $route->getName();

        if (! is_string($name) || ! $this->isLocalized($route)) {
            return null;
        }

        return $this->route($name, $route->parameters(), $locale);
    }

    /**
     * A dynamic page's address in the current language.
     *
     * The slug given is whichever language it was written in; what comes back
     * is the translation the visitor is reading, or the original when that
     * page has no version in their language.
     */
    public function page(string $slug): string
    {
        $locale = app()->getLocale();

        return $this->route('pages.show', ['slug' => $slug], $locale)
            ?? route('pages.show', ['locale' => $locale, 'slug' => $slug]);
    }

    /**
     * Build one route in one language, translating the slugs it carries.
     *
     * @param array<string, mixed> $parameters
     */
    public function route(string $routeName, array $parameters, string $locale): ?string
    {
        if (! RouteFacade::has($routeName)) {
            return null;
        }

        unset($parameters['locale']);

        $translated = match ($routeName) {
            'pages.show'    => $this->pageParameters($parameters, $locale),
            'blog.show'     => $this->postParameters($parameters, $locale),
            'blog.category' => $this->categoryParameters($parameters, $locale),
            default         => $parameters,
        };

        if ($translated === null) {
            return null;
        }

        return route($routeName, array_merge(['locale' => $locale], $translated));
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>|null
     */
    private function pageParameters(array $parameters, string $locale): ?array
    {
        $slug = $this->slug($parameters, 'slug');

        if ($slug === null) {
            return null;
        }

        $group = Page::query()
            ->where('slug', $slug)
            ->orderByRaw('case when locale = ? then 0 else 1 end', [app()->getLocale()])
            ->value('lang_group_id');

        if ($group === null) {
            return null;
        }

        $translated = Page::query()
            ->published()
            ->where('lang_group_id', $group)
            ->where('locale', $locale)
            ->value('slug');

        return is_string($translated) ? ['slug' => $translated] : null;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>|null
     */
    private function postParameters(array $parameters, string $locale): ?array
    {
        $slug = $this->slug($parameters, 'slug');

        if ($slug === null) {
            return null;
        }

        $group = BlogPost::query()
            ->where('slug', $slug)
            ->orderByRaw('case when locale = ? then 0 else 1 end', [app()->getLocale()])
            ->value('lang_group_id');

        if ($group === null) {
            return null;
        }

        $translated = BlogPost::query()
            ->published()
            ->with('category:id,slug')
            ->where('lang_group_id', $group)
            ->where('locale', $locale)
            ->first(['id', 'blog_category_id', 'slug']);

        if ($translated === null || $translated->category === null) {
            return null;
        }

        return [
            'categorySlug' => $translated->category->slug,
            'slug'         => $translated->slug,
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>|null
     */
    private function categoryParameters(array $parameters, string $locale): ?array
    {
        $slug = $this->slug($parameters, 'categorySlug');

        if ($slug === null) {
            return null;
        }

        $group = BlogCategory::query()
            ->where('slug', $slug)
            ->orderByRaw('case when locale = ? then 0 else 1 end', [app()->getLocale()])
            ->value('lang_group_id');

        if ($group === null) {
            return null;
        }

        $translated = BlogCategory::query()
            ->active()
            ->where('lang_group_id', $group)
            ->where('locale', $locale)
            ->value('slug');

        return is_string($translated) ? ['categorySlug' => $translated] : null;
    }

    /**
     * Routes under the {locale} prefix are the ones with a per-language
     * address; SetLocale drops the parameter itself once it has read it, so the
     * URI is what is left to recognise them by.
     */
    private function isLocalized(Route $route): bool
    {
        return str_starts_with($route->uri(), '{locale}');
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function slug(array $parameters, string $key): ?string
    {
        $value = $parameters[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
