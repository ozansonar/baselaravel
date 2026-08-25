<?php

declare(strict_types=1);

namespace Database\Seeders;

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
                'label'        => 'Blog',
                'icon'         => 'fa-solid fa-newspaper',
                'link_type'    => 'route',
                'route_name'   => 'blog.index',
                'display_type' => 'link',
            ],
            [
                'label'        => 'Hakkımızda',
                'icon'         => 'fa-solid fa-circle-info',
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
            MenuItem::create(array_merge($data, [
                'menu_id'    => $headerMenu->id,
                'sort_order' => $index,
                'is_active'  => true,
                'target'     => '_self',
            ]));
        }
    }
}
