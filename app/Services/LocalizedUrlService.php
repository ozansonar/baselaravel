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
    /**
     * İstek içi hatırlatıcılar.
     *
     * Aynı adres bir sayfada defalarca çözülüyor: hreflang etiketleri,
     * dil değiştirici (başlıkta ve mobil menüde iki kez), içeriğin çeviri
     * bağlantısı, kanonik ve alt bilgideki sayfa bağlantıları. Her çözüm
     * iki-üç sorgu demek; blog detayında aynı yazı dokuz kez sorgulanıyordu.
     *
     * Cevaplar bir istek boyunca değişmediği için ilk çözümde saklanıyor.
     * Servis singleton olarak bağlı, yoksa her app() çağrısı yeni bir örnek
     * doğurur ve hatırlatıcı hiç işe yaramazdı.
     *
     * @var array<string, string|null>
     */
    private array $routeMemo = [];

    /** @var array<string, array<string, mixed>|null> */
    private array $parameterMemo = [];

    /** @var array<string, array<string, string>> */
    private array $alternatesMemo = [];

    public function __construct(
        private readonly LanguageService $languages,
        // Grup araması menü bağlantılarında da yapılıyor; iki taraf aynı
        // çözücüyü paylaşmasa sorgu her sayfada iki kez giderdi.
        private readonly TranslationGroupResolver $groups,
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
        $anahtar = $this->memoKey($routeName, $parameters, '*');

        if (isset($this->alternatesMemo[$anahtar])) {
            return $this->alternatesMemo[$anahtar];
        }

        $urls = [];

        foreach ($this->languages->active() as $language) {
            $url = $this->route($routeName, $parameters, $language->code);

            if ($url !== null) {
                $urls[$language->code] = $url;
            }
        }

        return $this->alternatesMemo[$anahtar] = $urls;
    }

    /**
     * The current page in one language, or null when it has no version there.
     */
    public function current(string $locale): ?string
    {
        return $this->alternates()[$locale] ?? null;
    }

    /**
     * Dil değiştiricinin listesi: her dil için bir adres ve o dilde gerçekten
     * bir sürüm olup olmadığı.
     *
     * alternates() ile karıştırılmamalı. Orası hreflang'i besliyor ve çevirisi
     * olmayan dili bilerek dışarıda bırakıyor: olmayan bir sürümü arama
     * motoruna bildirmek, hiçbir şey söylememekten kötü. Değiştirici ise her
     * dili göstermek zorunda — ziyaretçi dili değiştirebilmeli. Aradaki fark
     * "translated" bayrağıyla taşınıyor ki arayüz çevirisi olmayan dili sessizce
     * ana sayfaya atmak yerine bunu söyleyebilsin.
     *
     * @return array<string, array{url: string, translated: bool}>
     */
    public function switcherTargets(): array
    {
        $alternates = $this->alternates();
        $targets = [];

        foreach ($this->languages->active() as $language) {
            $code = $language->code;
            $translated = isset($alternates[$code]);

            $targets[$code] = [
                'url'        => $alternates[$code] ?? route('home', ['locale' => $code]),
                'translated' => $translated,
            ];
        }

        return $targets;
    }

    /**
     * İçeriğin kendi dilindeki adresi.
     *
     * Çevirisi olmayan içerik ziyaretçinin dilinde de kendi diliyle basılıyor
     * (HasTranslations::scopeLocaleWithFallback): Türkçe yazı /en/ altında da
     * açılıyor. Kanonik url()->current() olduğunda aynı metin iki ayrı adreste
     * kendini kanonik ilan ediyor ve arama motoru için kopya içerik doğuyor.
     * Kanonik, metnin gerçekten yazıldığı dile bakmalı.
     *
     * @param array<string, mixed> $parameters
     */
    public function canonical(string $routeName, array $parameters, string $contentLocale): string
    {
        return $this->route($routeName, $parameters, $contentLocale)
            ?? url()->current();
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
    /**
     * Panelden girilen bir bağlantıyı ziyaretçinin diline taşır.
     *
     * Yönetici artık dil ön eki yazmıyor: "iletisim" yazıyor, ziyaretçi hangi
     * dilde geziniyorsa bağlantı oraya gidiyor. Eskiden her dil için ayrı
     * kayıt gerekiyordu ve bu kaçınılmaz olarak unutuluyordu — İngilizce
     * sayfadaki düğme Türkçe sayfaya götürüyordu.
     *
     * Kayıtlı adres zaten bir dil ön eki taşıyorsa (eski veri "/tr/iletisim"
     * diye duruyor) o ön ek atılıp bugünkü dille değiştiriliyor. Böylece
     * geçmiş kayıtlar da düzeliyor, taşıma göçü gerekmiyor.
     *
     * Dışarı çıkan ya da sayfaya gitmeyen adresler olduğu gibi kalıyor:
     * http(s), protokolsüz //, mailto, tel, çapa ve sorgu dizisi.
     */
    public function fromInput(?string $input): string
    {
        $value = trim((string) $input);

        if ($value === '') {
            return '#';
        }

        if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|[#?])~i', $value) === 1) {
            return $value;
        }

        $path = ltrim($value, '/');
        $locale = app()->getLocale();
        $segments = explode('/', $path, 2);

        // Baştaki parça bir dil koduysa yerine bugünkü dil geçiyor.
        if ($this->languages->isSupported($segments[0])) {
            $path = $segments[1] ?? '';
        }

        return $path === '' ? url($locale) : url($locale . '/' . $path);
    }

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

        $anahtar = $this->memoKey($routeName, $parameters, $locale);

        if (array_key_exists($anahtar, $this->routeMemo)) {
            return $this->routeMemo[$anahtar];
        }

        // Çeviri araması sorguya gidiyor; sonucu ayrıca saklamak, aynı
        // adresi başka bir yerden isteyen çağrının da sorgusuz dönmesini
        // sağlıyor (kanonik ile hreflang aynı yazıyı soruyor).
        if (array_key_exists($anahtar, $this->parameterMemo)) {
            $translated = $this->parameterMemo[$anahtar];
        } else {
            $translated = match ($routeName) {
                'pages.show'    => $this->pageParameters($parameters, $locale),
                'blog.show'     => $this->postParameters($parameters, $locale),
                'blog.category' => $this->categoryParameters($parameters, $locale),
                default         => $parameters,
            };

            $this->parameterMemo[$anahtar] = $translated;
        }

        if ($translated === null) {
            return $this->routeMemo[$anahtar] = null;
        }

        return $this->routeMemo[$anahtar] = route($routeName, array_merge(['locale' => $locale], $translated));
    }

    /**
     * Hatırlatıcı anahtarı: rota adı + parametreler + dil.
     *
     * @param array<string, mixed> $parameters
     */
    private function memoKey(string $routeName, array $parameters, string $locale): string
    {
        unset($parameters['locale']);
        ksort($parameters);

        return $routeName . '|' . $locale . '|' . json_encode($parameters);
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

        $group = $this->groups->resolve(Page::class, $slug);

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

        $group = $this->groups->resolve(BlogPost::class, $slug);

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

        $group = $this->groups->resolve(BlogCategory::class, $slug);

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
