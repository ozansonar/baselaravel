<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\Concerns\LocalizedCache;
use App\Services\Concerns\ResolvesLocalizedSlugs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

final class MenuService
{
    use LocalizedCache;
    use ResolvesLocalizedSlugs;

    private const CACHE_TTL = 3600;

    /**
     * The navigation for a location in the visitor's language.
     *
     * A language that has no menu of its own falls back to the default one, so
     * activating a language never leaves the site without navigation.
     */
    /**
     * Yönetim listesindeki menüler.
     *
     * Ekran da dışa aktarma da bu sorguyu kullanır; sıralama menüleri konum ve
     * dil bazında bir arada tutar.
     *
     * @return Builder<Menu>
     */
    public function listQuery(): Builder
    {
        return Menu::withCount('items')
            ->orderBy('location')
            ->orderBy('locale');
    }

    public function getByLocation(string $location): ?Menu
    {
        return Cache::remember(
            $this->localeCacheKey($this->cacheKey($location)),
            self::CACHE_TTL,
            function () use ($location): ?Menu {
                $locale = app()->getLocale();
                $fallback = app(LanguageService::class)->defaultCode();

                return $this->queryLocation($location, $locale)
                    ?? ($locale === $fallback ? null : $this->queryLocation($location, $fallback));
            },
        );
    }

    /**
     * Copy a menu — including its whole item tree — into another language.
     *
     * Without this, activating a language would mean rebuilding the navigation
     * by hand. The copy keeps the structure and links every item to its source
     * by lang_group_id, so the translator only edits the labels.
     */
    public function copyToLocale(Menu $menu, string $locale): Menu
    {
        $existing = Menu::query()
            ->where('lang_group_id', $menu->lang_group_id)
            ->where('locale', $locale)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $copy = DB::transaction(function () use ($menu, $locale): Menu {
            $copy = Menu::create([
                'locale'        => $locale,
                'lang_group_id' => $menu->lang_group_id,
                'name'          => $menu->name,
                'location'      => $menu->location,
                'is_active'     => $menu->is_active,
            ]);

            $this->copyItems($menu, $copy, $locale, null, null);

            return $copy;
        });

        $this->clearAllCaches();

        return $copy;
    }

    /**
     * Yayında menüsü olan konumlar.
     *
     * Konum serbest bir metin: panelden 'header' ve 'footer' dışında bir ad da
     * verilebiliyor. API'nin listesi bu yüzden sabit bir diziden değil
     * veritabanından geliyor — yeni bir konum açıldığında uç onu kendiliğinden
     * yayınlıyor.
     *
     * @return array<int, string>
     */
    public function activeLocations(): array
    {
        return Cache::remember(
            $this->localeCacheKey('menus.locations'),
            self::CACHE_TTL,
            fn (): array => Menu::active()
                ->distinct()
                ->orderBy('location')
                ->pluck('location')
                ->all(),
        );
    }

    public function clearCache(string $location): void
    {
        $this->forgetLocalized($this->cacheKey($location));

        // Alt bilgi menü sütunlarını çizilmiş hâliyle saklıyor; menü değişip de
        // o parça yerinde kalırsa ziyaretçi bir saat boyunca eski bağlantıları
        // görür — hata vermeden.
        app(\App\Services\CachePurger::class)->forgetPrefix(\App\Support\CacheKeys::PREFIX_FRAGMENT);
    }

    public function clearAllCaches(): void
    {
        foreach (['header', 'footer', 'custom'] as $location) {
            $this->clearCache($location);
        }

        $this->forgetLocalized('menus.locations');
    }

    private function queryLocation(string $location, string $locale): ?Menu
    {
        return Menu::active()
            ->byLocation($location)
            ->where('locale', $locale)
            ->with(['rootItems' => function ($query): void {
                $query->where('is_active', true)->with('activeChildren');
            }])
            ->first();
    }

    /**
     * Walk the source tree depth-first so a child is always created after the
     * parent it points at.
     */
    private function copyItems(Menu $source, Menu $target, string $locale, ?int $sourceParentId, ?int $targetParentId): void
    {
        $children = MenuItem::query()
            ->where('menu_id', $source->id)
            ->where('parent_id', $sourceParentId)
            ->orderBy('sort_order')
            ->get();

        foreach ($children as $item) {
            $copy = MenuItem::create([
                'locale'        => $locale,
                'lang_group_id' => $item->lang_group_id ?: (string) Str::uuid(),
                'menu_id'       => $target->id,
                'parent_id'     => $targetParentId,
                'label'         => $item->label,
                'icon'          => $item->icon,
                'link_type'     => $item->link_type,
                'route_name'    => $item->route_name,
                'route_params'  => $this->translateRouteParams($item, $locale),
                'url'           => $item->url,
                'target'        => $item->target,
                'display_type'  => $item->display_type,
                'sort_order'    => $item->sort_order,
                'is_active'     => $item->is_active,
            ]);

            $this->copyItems($source, $target, $locale, $item->id, $copy->id);
        }
    }

    /**
     * A copied page link should open the target language's page, not the one it
     * was copied from — where that translation already exists.
     *
     * @return array<string, mixed>|null
     */
    private function translateRouteParams(MenuItem $item, string $locale): ?array
    {
        $params = $item->route_params;

        if ($item->route_name !== 'pages.show' || ! is_array($params)) {
            return $params;
        }

        $slug = $params['slug'] ?? null;

        if (! is_string($slug) || $slug === '') {
            return $params;
        }

        $params['slug'] = $this->localizedSlug($slug, $locale);

        return $params;
    }

    private function cacheKey(string $location): string
    {
        return "menu.{$location}";
    }
}
