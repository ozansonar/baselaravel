<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

final class MenuItemService
{
    public function __construct(
        private readonly MenuService $menuService,
    ) {}

    public function create(array $data): MenuItem
    {
        $data['sort_order'] = $data['sort_order'] ?? $this->nextSortOrder(
            (int) $data['menu_id'],
            isset($data['parent_id']) ? (int) $data['parent_id'] : null
        );

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
                    return route($item->route_name, $item->route_params ?? []);
                }
            } catch (Throwable) {
                return '#';
            }
        }

        return $item->url ?: '#';
    }

    public function isActive(MenuItem $item): bool
    {
        $currentRoute = request()->route()?->getName() ?? '';
        $currentPath = '/' . trim(request()->path(), '/');

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

            // Mega menu / dropdown items: parent active if any product page is shown
            if ($item->display_type !== 'link' && str_starts_with($currentRoute, 'products.')
                && str_starts_with($item->route_name, 'products.')) {
                return true;
            }
        }

        if ($item->link_type === 'url' && $item->url) {
            $itemPath = '/' . trim(parse_url($item->url, PHP_URL_PATH) ?? '', '/');
            if ($itemPath !== '/' && $itemPath === $currentPath) {
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

    public function getAvailableRoutes(): array
    {
        return [
            'home'           => 'Anasayfa',
            'products.all'   => 'Tüm Ürünler',
            'products.index' => 'Kategori Sayfası (slug parametresi gerekli)',
            'products.show'  => 'Ürün Detayı (slug parametresi gerekli)',
            'gallery'        => 'Galeri',
            'contact'        => 'İletişim',
            'faq'            => 'SSS',
            'blog.index'     => 'Blog',
            'pages.show'     => 'Dinamik Sayfa (slug parametresi gerekli)',
            'cart.index'     => 'Sepet',
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
