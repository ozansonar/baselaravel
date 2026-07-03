<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

final class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $headerMenu = Menu::firstOrCreate(
            ['location' => 'header'],
            ['name' => 'Header Menu', 'is_active' => true]
        );

        if ($headerMenu->items()->exists()) {
            return;
        }

        $items = [
            [
                'label'        => 'Anasayfa',
                'icon'         => 'fa-solid fa-house',
                'link_type'    => 'route',
                'route_name'   => 'home',
                'display_type' => 'link',
            ],
            [
                'label'        => 'Ürünler',
                'icon'         => 'fa-solid fa-basket-shopping',
                'link_type'    => 'route',
                'route_name'   => 'products.all',
                'display_type' => 'mega_menu',
                'children'     => $this->buildCategoryChildren(),
            ],
            [
                'label'        => 'Hakkımızda',
                'icon'         => 'fa-solid fa-tractor',
                'link_type'    => 'route',
                'route_name'   => 'pages.show',
                'route_params' => ['slug' => 'hakkimizda'],
                'display_type' => 'link',
            ],
            [
                'label'        => 'Galeri',
                'icon'         => 'fa-solid fa-images',
                'link_type'    => 'route',
                'route_name'   => 'gallery',
                'display_type' => 'link',
            ],
            [
                'label'        => 'İletişim',
                'icon'         => 'fa-solid fa-paper-plane',
                'link_type'    => 'route',
                'route_name'   => 'contact',
                'display_type' => 'link',
            ],
            [
                'label'        => 'SSS',
                'icon'         => 'fa-solid fa-circle-question',
                'link_type'    => 'route',
                'route_name'   => 'faq',
                'display_type' => 'link',
            ],
        ];

        foreach ($items as $index => $data) {
            $children = $data['children'] ?? [];
            unset($data['children']);

            $item = MenuItem::create(array_merge($data, [
                'menu_id'    => $headerMenu->id,
                'sort_order' => $index,
                'is_active'  => true,
                'target'     => '_self',
            ]));

            foreach ($children as $childIndex => $child) {
                MenuItem::create(array_merge($child, [
                    'menu_id'    => $headerMenu->id,
                    'parent_id'  => $item->id,
                    'sort_order' => $childIndex,
                    'is_active'  => true,
                    'target'     => '_self',
                ]));
            }
        }
    }

    private function buildCategoryChildren(): array
    {
        return Category::active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $cat) => [
                'label'        => $cat->name,
                'icon'         => 'fa-solid fa-seedling',
                'link_type'    => 'route',
                'route_name'   => 'products.index',
                'route_params' => ['categorySlug' => $cat->slug],
                'display_type' => 'link',
            ])
            ->all();
    }
}
