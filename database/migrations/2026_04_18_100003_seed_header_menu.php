<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('menus')->where('location', 'header')->exists()) {
            return;
        }

        $now = now();

        $menuId = DB::table('menus')->insertGetId([
            'name'       => 'Header Menu',
            'location'   => 'header',
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $items = [
            [
                'label'        => 'Anasayfa',
                'icon'         => 'fa-solid fa-house',
                'route_name'   => 'home',
                'route_params' => null,
                'display_type' => 'link',
                'children'     => [],
            ],
            [
                'label'        => 'Blog',
                'icon'         => 'fa-solid fa-newspaper',
                'route_name'   => 'blog.index',
                'route_params' => null,
                'display_type' => 'link',
                'children'     => [],
            ],
            [
                'label'        => 'Hakkımızda',
                'icon'         => 'fa-solid fa-circle-info',
                'route_name'   => 'pages.show',
                'route_params' => json_encode(['slug' => 'hakkimizda']),
                'display_type' => 'link',
                'children'     => [],
            ],
            [
                'label'        => 'Galeri',
                'icon'         => 'fa-solid fa-images',
                'route_name'   => 'gallery',
                'route_params' => null,
                'display_type' => 'link',
                'children'     => [],
            ],
            [
                'label'        => 'İletişim',
                'icon'         => 'fa-solid fa-paper-plane',
                'route_name'   => 'contact',
                'route_params' => null,
                'display_type' => 'link',
                'children'     => [],
            ],
            [
                'label'        => 'SSS',
                'icon'         => 'fa-solid fa-circle-question',
                'route_name'   => 'faq',
                'route_params' => null,
                'display_type' => 'link',
                'children'     => [],
            ],
        ];

        foreach ($items as $sortOrder => $data) {
            $children = $data['children'];
            unset($data['children']);

            $itemId = DB::table('menu_items')->insertGetId([
                'menu_id'      => $menuId,
                'parent_id'    => null,
                'label'        => $data['label'],
                'icon'         => $data['icon'],
                'link_type'    => 'route',
                'route_name'   => $data['route_name'],
                'route_params' => $data['route_params'],
                'url'          => null,
                'target'       => '_self',
                'display_type' => $data['display_type'],
                'sort_order'   => $sortOrder,
                'is_active'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            foreach ($children as $childIndex => $child) {
                DB::table('menu_items')->insert([
                    'menu_id'      => $menuId,
                    'parent_id'    => $itemId,
                    'label'        => $child['label'],
                    'icon'         => $child['icon'],
                    'link_type'    => 'route',
                    'route_name'   => $child['route_name'],
                    'route_params' => $child['route_params'],
                    'url'          => null,
                    'target'       => '_self',
                    'display_type' => 'link',
                    'sort_order'   => $childIndex,
                    'is_active'    => true,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menus')->where('location', 'header')->value('id');
        if ($menuId) {
            DB::table('menu_items')->where('menu_id', $menuId)->delete();
            DB::table('menus')->where('id', $menuId)->delete();
        }
    }
};
