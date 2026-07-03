<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // Süt
            ['category' => 'sut', 'name' => 'Günlük Çiğ Süt', 'slug' => 'gunluk-cig-sut', 'short_description' => 'Her sabah taze sağılmış çiğ inek sütü', 'price' => 45.00, 'stock' => 50, 'unit' => 'litre', 'status' => 'active', 'is_featured' => true, 'sort_order' => 1, 'meta_title' => 'Günlük Çiğ Süt | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Her sabah taze sağılmış, katkısız doğal çiğ inek sütü.'],
            ['category' => 'sut', 'name' => 'Keçi Sütü', 'slug' => 'keci-sutu', 'short_description' => 'Taze keçi sütü, doğal ve saf', 'price' => 60.00, 'stock' => 20, 'unit' => 'litre', 'status' => 'active', 'is_featured' => false, 'sort_order' => 2, 'meta_title' => 'Keçi Sütü | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Doğal ve taze keçi sütü, çiftliğimizden sofranıza.'],

            // Yoğurt
            ['category' => 'yogurt', 'name' => 'Ev Yapımı Yoğurt', 'slug' => 'ev-yapimi-yogurt', 'short_description' => 'Geleneksel mayalama ile hazırlanmış ev yoğurdu', 'price' => 80.00, 'stock' => 30, 'unit' => 'kg', 'status' => 'active', 'is_featured' => true, 'sort_order' => 1, 'meta_title' => 'Ev Yapımı Yoğurt | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Geleneksel yöntemlerle mayalanmış, katkısız ev yapımı yoğurt.'],
            ['category' => 'yogurt', 'name' => 'Süzme Yoğurt', 'slug' => 'suzme-yogurt', 'short_description' => 'Tülbentten süzülmüş koyu kıvamlı yoğurt', 'price' => 120.00, 'stock' => 20, 'unit' => 'kg', 'status' => 'active', 'is_featured' => false, 'sort_order' => 2, 'meta_title' => 'Süzme Yoğurt | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Tülbent süzme, koyu kıvamlı doğal süzme yoğurt.'],

            // Peynir
            ['category' => 'peynir', 'name' => 'Köy Peyniri', 'slug' => 'koy-peyniri', 'short_description' => 'Geleneksel yöntemle yapılmış beyaz köy peyniri', 'price' => 200.00, 'stock' => 25, 'unit' => 'kg', 'status' => 'active', 'is_featured' => true, 'sort_order' => 1, 'meta_title' => 'Köy Peyniri | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Doğal sütten geleneksel yöntemle yapılmış beyaz köy peyniri.'],
            ['category' => 'peynir', 'name' => 'Tulum Peyniri', 'slug' => 'tulum-peyniri', 'short_description' => 'Doğal tulumda olgunlaştırılmış peynir', 'price' => 350.00, 'stock' => 15, 'unit' => 'kg', 'status' => 'active', 'is_featured' => false, 'sort_order' => 2, 'meta_title' => 'Tulum Peyniri | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Doğal tulumda minimum 3 ay olgunlaştırılmış tulum peyniri.'],
            ['category' => 'peynir', 'name' => 'Kaşar Peyniri', 'slug' => 'kasar-peyniri', 'short_description' => 'Taze kaşar peyniri, tam yağlı', 'price' => 280.00, 'stock' => 20, 'unit' => 'kg', 'status' => 'active', 'is_featured' => false, 'sort_order' => 3, 'meta_title' => 'Kaşar Peyniri | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Tam yağlı, doğal sütten üretilmiş taze kaşar peyniri.'],

            // Tereyağı
            ['category' => 'tereyagi', 'name' => 'Köy Tereyağı', 'slug' => 'koy-tereyagi', 'short_description' => 'Yayıkta dövülmüş saf köy tereyağı', 'price' => 400.00, 'stock' => 15, 'unit' => 'kg', 'status' => 'active', 'is_featured' => true, 'sort_order' => 1, 'meta_title' => 'Köy Tereyağı | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Yayıkta dövülmüş, %100 saf köy tereyağı.'],

            // Yumurta
            ['category' => 'yumurta', 'name' => 'Köy Yumurtası (30\'lu)', 'slug' => 'koy-yumurtasi-30lu', 'short_description' => 'Serbest gezen tavuklardan günlük köy yumurtası', 'price' => 150.00, 'stock' => 40, 'unit' => 'adet', 'status' => 'active', 'is_featured' => true, 'sort_order' => 1, 'meta_title' => 'Köy Yumurtası | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Serbest gezen tavuklardan günlük toplanan doğal köy yumurtası, 30\'lu paket.'],
            ['category' => 'yumurta', 'name' => 'Köy Yumurtası (15\'li)', 'slug' => 'koy-yumurtasi-15li', 'short_description' => 'Serbest gezen tavuklardan günlük köy yumurtası', 'price' => 80.00, 'stock' => 60, 'unit' => 'adet', 'status' => 'active', 'is_featured' => false, 'sort_order' => 2, 'meta_title' => 'Köy Yumurtası 15\'li | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Serbest gezen tavuklardan günlük toplanan doğal köy yumurtası, 15\'li paket.'],

            // Bal
            ['category' => 'bal', 'name' => 'Süzme Çiçek Balı', 'slug' => 'suzme-cicek-bali', 'short_description' => 'Yaylalardan toplanan doğal süzme çiçek balı', 'price' => 500.00, 'stock' => 30, 'unit' => 'kg', 'status' => 'active', 'is_featured' => true, 'sort_order' => 1, 'meta_title' => 'Süzme Çiçek Balı | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Yaylalardan toplanan %100 doğal süzme çiçek balı.'],
            ['category' => 'bal', 'name' => 'Karakovan Balı', 'slug' => 'karakovan-bali', 'short_description' => 'Geleneksel karakovan yöntemiyle üretilen bal', 'price' => 800.00, 'stock' => 10, 'unit' => 'kg', 'status' => 'active', 'is_featured' => false, 'sort_order' => 2, 'meta_title' => 'Karakovan Balı | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Geleneksel karakovan yöntemiyle üretilen organik bal.'],

            // Reçel
            ['category' => 'recel', 'name' => 'Çilek Reçeli', 'slug' => 'cilek-receli', 'short_description' => 'Ev yapımı doğal çilek reçeli', 'price' => 120.00, 'stock' => 25, 'unit' => 'kg', 'status' => 'active', 'is_featured' => false, 'sort_order' => 1, 'meta_title' => 'Çilek Reçeli | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Taze çileklerden ev yapımı doğal çilek reçeli.'],
            ['category' => 'recel', 'name' => 'Kayısı Reçeli', 'slug' => 'kayisi-receli', 'short_description' => 'Ev yapımı doğal kayısı reçeli', 'price' => 110.00, 'stock' => 25, 'unit' => 'kg', 'status' => 'active', 'is_featured' => false, 'sort_order' => 2, 'meta_title' => 'Kayısı Reçeli | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Taze kayısılardan ev yapımı doğal kayısı reçeli.'],

            // Kırmızı Et
            ['category' => 'kirmizi-et', 'name' => 'Dana Kıyma', 'slug' => 'dana-kiyma', 'short_description' => 'Doğal beslenmiş danadan taze kıyma', 'price' => 350.00, 'stock' => 20, 'unit' => 'kg', 'status' => 'active', 'is_featured' => false, 'sort_order' => 1, 'meta_title' => 'Dana Kıyma | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Doğal beslenmiş danadan çekilmiş taze dana kıyma.'],
            ['category' => 'kirmizi-et', 'name' => 'Kuzu But', 'slug' => 'kuzu-but', 'short_description' => 'Taze kuzu but eti', 'price' => 450.00, 'stock' => 10, 'unit' => 'kg', 'status' => 'active', 'is_featured' => false, 'sort_order' => 2, 'meta_title' => 'Kuzu But | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Doğal otlak kuzu but eti, taze ve lezzetli.'],

            // Sucuk
            ['category' => 'sarkuteri', 'name' => 'Ev Yapımı Sucuk', 'slug' => 'ev-yapimi-sucuk', 'short_description' => 'Geleneksel baharatlarla hazırlanmış ev sucuğu', 'price' => 300.00, 'stock' => 20, 'unit' => 'kg', 'status' => 'active', 'is_featured' => true, 'sort_order' => 1, 'meta_title' => 'Ev Yapımı Sucuk | Orhan Baba\'nın Çiftliği', 'meta_description' => 'Geleneksel baharatlarla hazırlanmış, katkısız ev yapımı sucuk.'],
        ];

        foreach ($products as $productData) {
            $category = Category::where('slug', $productData['category'])->first();

            if (!$category) {
                continue;
            }

            unset($productData['category']);
            $productData['category_id'] = $category->id;

            Product::updateOrCreate(
                ['slug' => $productData['slug']],
                $productData,
            );
        }
    }
}
