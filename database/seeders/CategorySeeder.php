<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'             => 'Süt Ürünleri',
                'slug'             => 'sut-urunleri',
                'description'      => 'Çiftliğimizden taze süt, yoğurt, peynir ve tereyağı',
                'sort_order'       => 1,
                'is_active'        => true,
                'meta_title'       => 'Taze Süt Ürünleri | Orhan Baba\'nın Çiftliği',
                'meta_description' => 'Doğal ve katkısız süt ürünleri. Günlük taze süt, ev yapımı yoğurt, köy peyniri ve tereyağı.',
                'children' => [
                    ['name' => 'Süt',       'slug' => 'sut',       'description' => 'Günlük taze çiğ süt ve pastörize süt',       'sort_order' => 1],
                    ['name' => 'Yoğurt',    'slug' => 'yogurt',    'description' => 'Ev yapımı yoğurt çeşitleri',                  'sort_order' => 2],
                    ['name' => 'Peynir',    'slug' => 'peynir',    'description' => 'Köy peyniri, tulum peyniri, kaşar',            'sort_order' => 3],
                    ['name' => 'Tereyağı',  'slug' => 'tereyagi',  'description' => 'Ev yapımı tereyağı ve sadeyağ',               'sort_order' => 4],
                ],
            ],
            [
                'name'             => 'Yumurta',
                'slug'             => 'yumurta',
                'description'      => 'Serbest gezen tavuklardan doğal köy yumurtası',
                'sort_order'       => 2,
                'is_active'        => true,
                'meta_title'       => 'Köy Yumurtası | Orhan Baba\'nın Çiftliği',
                'meta_description' => 'Serbest gezen tavuklardan günlük toplanan doğal köy yumurtası.',
                'children' => [],
            ],
            [
                'name'             => 'Et Ürünleri',
                'slug'             => 'et-urunleri',
                'description'      => 'Doğal beslenmiş hayvanlardan taze et ve et ürünleri',
                'sort_order'       => 3,
                'is_active'        => true,
                'meta_title'       => 'Doğal Et Ürünleri | Orhan Baba\'nın Çiftliği',
                'meta_description' => 'Doğal beslenmiş hayvanlardan taze kırmızı et, tavuk ve et ürünleri.',
                'children' => [
                    ['name' => 'Kırmızı Et',  'slug' => 'kirmizi-et',  'description' => 'Dana ve kuzu eti çeşitleri',   'sort_order' => 1],
                    ['name' => 'Tavuk',        'slug' => 'tavuk',       'description' => 'Köy tavuğu ve parça tavuk',     'sort_order' => 2],
                    ['name' => 'Şarküteri',    'slug' => 'sarkuteri',   'description' => 'Sucuk, pastırma, kavurma',       'sort_order' => 3],
                ],
            ],
            [
                'name'             => 'Bal & Reçel',
                'slug'             => 'bal-recel',
                'description'      => 'Organik çiçek balı, süzme bal ve ev yapımı reçeller',
                'sort_order'       => 4,
                'is_active'        => true,
                'meta_title'       => 'Doğal Bal ve Reçel | Orhan Baba\'nın Çiftliği',
                'meta_description' => 'Organik çiçek balı, süzme bal, karakovan balı ve ev yapımı reçel çeşitleri.',
                'children' => [
                    ['name' => 'Bal',    'slug' => 'bal',    'description' => 'Süzme bal, karakovan balı, çiçek balı', 'sort_order' => 1],
                    ['name' => 'Reçel',  'slug' => 'recel',  'description' => 'Ev yapımı mevsim reçelleri',            'sort_order' => 2],
                ],
            ],
            [
                'name'             => 'Sebze & Meyve',
                'slug'             => 'sebze-meyve',
                'description'      => 'Mevsiminde taze sebze ve meyve',
                'sort_order'       => 5,
                'is_active'        => true,
                'meta_title'       => 'Taze Sebze Meyve | Orhan Baba\'nın Çiftliği',
                'meta_description' => 'Çiftliğimizden mevsiminde doğal sebze ve meyve çeşitleri.',
                'children' => [
                    ['name' => 'Sebze',  'slug' => 'sebze',  'description' => 'Mevsim sebzeleri',  'sort_order' => 1],
                    ['name' => 'Meyve',  'slug' => 'meyve',  'description' => 'Mevsim meyveleri',  'sort_order' => 2],
                ],
            ],
            [
                'name'             => 'Kuru Gıda',
                'slug'             => 'kuru-gida',
                'description'      => 'Bakliyat, kuruyemiş ve kurutulmuş ürünler',
                'sort_order'       => 6,
                'is_active'        => true,
                'meta_title'       => 'Kuru Gıda Ürünleri | Orhan Baba\'nın Çiftliği',
                'meta_description' => 'Doğal bakliyat, kuruyemiş ve kurutulmuş gıda ürünleri.',
                'children' => [],
            ],
        ];

        foreach ($categories as $categoryData) {
            $children = $categoryData['children'];
            unset($categoryData['children']);

            $parent = Category::updateOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData,
            );

            foreach ($children as $childData) {
                $childData['parent_id'] = $parent->id;
                $childData['is_active'] = true;
                Category::updateOrCreate(
                    ['slug' => $childData['slug']],
                    $childData,
                );
            }
        }
    }
}
