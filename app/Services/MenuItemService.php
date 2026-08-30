<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\LocalizedUrlService;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\Concerns\ResolvesLocalizedSlugs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

final class MenuItemService
{
    use ResolvesLocalizedSlugs;

    public function __construct(
        private readonly MenuService $menuService,
    ) {}

    public function create(array $data): MenuItem
    {
        $data['sort_order'] = $data['sort_order'] ?? $this->nextSortOrder(
            (int) $data['menu_id'],
            isset($data['parent_id']) ? (int) $data['parent_id'] : null
        );

        // An item belongs to the language of the menu it sits in, not to
        // whatever language the panel happens to be displayed in.
        $data['locale'] ??= Menu::query()->whereKey($data['menu_id'])->value('locale');

        $item = MenuItem::create($data);
        $this->menuService->clearAllCaches();

        return $item;
    }

    public function update(MenuItem $item, array $data): bool
    {
        $result = $item->update($data);
        $this->menuService->clearAllCaches();

        return $result;
    }

    public function delete(MenuItem $item): void
    {
        $item->children()->each(fn (MenuItem $child) => $this->delete($child));
        $item->delete();
        $this->menuService->clearAllCaches();
    }

    public function restore(int $id): void
    {
        $item = MenuItem::withTrashed()->findOrFail($id);
        $item->restore();
        $this->menuService->clearAllCaches();
    }

    public function reorder(int $menuId, array $tree): void
    {
        DB::transaction(function () use ($menuId, $tree) {
            $this->saveTree($menuId, $tree, null);
        });
        $this->menuService->clearAllCaches();
    }

    public function resolveUrl(MenuItem $item): string
    {
        if ($item->link_type === 'route' && $item->route_name) {
            try {
                if (Route::has($item->route_name)) {
                    return route($item->route_name, $this->routeParams($item));
                }
            } catch (Throwable) {
                return '#';
            }
        }

        // Yönetici dil ön eki yazmıyor; bağlantı ziyaretçinin diline burada
        // taşınıyor. Dış adresler ve çapalar olduğu gibi kalıyor.
        return app(LocalizedUrlService::class)->fromInput($item->url);
    }

    /**
     * Point a page link at the translation the visitor is reading.
     *
     * Two ways a link ends up on the wrong slug: a menu shown as a fallback
     * carries the default language's slugs, and a menu copied into a new
     * language keeps the slugs it was copied from. Both are caught by asking
     * whether the slug itself belongs to the current language rather than
     * trusting the item's own locale.
     *
     * @return array<string, mixed>
     */
    private function routeParams(MenuItem $item): array
    {
        $params = $item->route_params ?? [];

        if ($item->route_name !== 'pages.show') {
            return $params;
        }

        $slug = $params['slug'] ?? null;

        if (! is_string($slug) || $slug === '') {
            return $params;
        }

        $params['slug'] = $this->localizedSlug($slug);

        return $params;
    }


    public function isActive(MenuItem $item): bool
    {
        $currentRoute = request()->route()?->getName() ?? '';
        $currentPath = '/' . trim(request()->path(), '/');
        // Front URLs carry the language as their first segment, so a menu item
        // stored as a plain path (/hakkimizda) still has to match /tr/hakkimizda.
        $pathWithoutLocale = $this->stripLocale($currentPath);

        if ($item->link_type === 'route' && $item->route_name) {
            if ($item->route_name === $currentRoute) {
                if ($item->route_name === 'pages.show') {
                    $expectedSlug = $item->route_params['slug'] ?? null;
                    $actualSlug = request()->route('slug');
                    if ($expectedSlug && $expectedSlug === $actualSlug) {
                        return true;
                    }
                } else {
                    return true;
                }
            }

        }

        if ($item->link_type === 'url' && $item->url) {
            $itemPath = '/' . trim(parse_url($item->url, PHP_URL_PATH) ?? '', '/');
            if ($itemPath !== '/' && ($itemPath === $currentPath || $itemPath === $pathWithoutLocale)) {
                return true;
            }
        }

        foreach ($item->activeChildren as $child) {
            if ($this->isActive($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The same path without its language segment, so stored links written
     * before the prefix — or copied from another language — still match.
     */
    private function stripLocale(string $path): string
    {
        $prefix = '/' . app()->getLocale();

        if ($path === $prefix) {
            return '/';
        }

        return str_starts_with($path, $prefix . '/')
            ? substr($path, strlen($prefix))
            : $path;
    }

    public function getAvailableRoutes(): array
    {
        return [
            'home'           => 'Anasayfa',
            'blog.index'     => 'Blog',
            'gallery'        => 'Galeri',
            'contact'        => 'İletişim',
            'faq'            => 'SSS',
            'pages.show'     => 'Dinamik Sayfa (slug parametresi gerekli)',
            'login'          => 'Giriş',
            'register'       => 'Kayıt',
        ];
    }

    private function nextSortOrder(int $menuId, ?int $parentId): int
    {
        return (int) MenuItem::where('menu_id', $menuId)
            ->where('parent_id', $parentId)
            ->max('sort_order') + 1;
    }

    private function saveTree(int $menuId, array $nodes, ?int $parentId): void
    {
        foreach ($nodes as $index => $node) {
            $id = (int) ($node['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            MenuItem::where('id', $id)
                ->where('menu_id', $menuId)
                ->update([
                    'parent_id'  => $parentId,
                    'sort_order' => $index,
                ]);

            if (! empty($node['children']) && is_array($node['children'])) {
                $this->saveTree($menuId, $node['children'], $id);
            }
        }
    }
}
