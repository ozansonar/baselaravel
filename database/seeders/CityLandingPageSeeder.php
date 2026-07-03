<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CityLandingPage;
use Illuminate\Database\Seeder;

/**
 * Türkiye'nin SEO açısından öncelikli 18 şehri için landing page iskeleti.
 * Sıralama: Çorum (merkez) en başta, sonrası nüfusa göre büyükten küçüğe.
 * AI içerik admin panelinden ("AI ile İçerik Üret" butonu) sonradan üretilir;
 * bu seeder sadece URL'leri canlı yapan iskeleti yazar.
 */
class CityLandingPageSeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            // Merkez — Çorum (üretim noktası)
            ['city_name' => 'Çorum',     'city_slug' => 'corum',     'region' => 'Karadeniz',        'tier' => 1, 'sort_order' => 10],

            // Tier 1 — Büyük metropoller (nüfusa göre)
            ['city_name' => 'İstanbul',  'city_slug' => 'istanbul',  'region' => 'Marmara',          'tier' => 1, 'sort_order' => 20],
            ['city_name' => 'Ankara',    'city_slug' => 'ankara',    'region' => 'İç Anadolu',       'tier' => 1, 'sort_order' => 30],
            ['city_name' => 'İzmir',     'city_slug' => 'izmir',     'region' => 'Ege',              'tier' => 1, 'sort_order' => 40],
            ['city_name' => 'Bursa',     'city_slug' => 'bursa',     'region' => 'Marmara',          'tier' => 1, 'sort_order' => 50],
            ['city_name' => 'Antalya',   'city_slug' => 'antalya',   'region' => 'Akdeniz',          'tier' => 1, 'sort_order' => 60],

            // Tier 2 — Büyük şehirler / yüksek gelir (nüfusa göre)
            ['city_name' => 'Konya',     'city_slug' => 'konya',     'region' => 'İç Anadolu',       'tier' => 2, 'sort_order' => 70],
            ['city_name' => 'Adana',     'city_slug' => 'adana',     'region' => 'Akdeniz',          'tier' => 2, 'sort_order' => 80],
            ['city_name' => 'Gaziantep', 'city_slug' => 'gaziantep', 'region' => 'Güneydoğu Anadolu','tier' => 2, 'sort_order' => 90],
            ['city_name' => 'Kocaeli',   'city_slug' => 'kocaeli',   'region' => 'Marmara',          'tier' => 2, 'sort_order' => 100],
            ['city_name' => 'Mersin',    'city_slug' => 'mersin',    'region' => 'Akdeniz',          'tier' => 2, 'sort_order' => 110],
            ['city_name' => 'Kayseri',   'city_slug' => 'kayseri',   'region' => 'İç Anadolu',       'tier' => 2, 'sort_order' => 120],
            ['city_name' => 'Samsun',    'city_slug' => 'samsun',    'region' => 'Karadeniz',        'tier' => 2, 'sort_order' => 130],

            // Tier 3 — Orta ölçek / premium tüketici (nüfusa göre)
            ['city_name' => 'Tekirdağ',  'city_slug' => 'tekirdag',  'region' => 'Marmara',          'tier' => 3, 'sort_order' => 140],
            ['city_name' => 'Sakarya',   'city_slug' => 'sakarya',   'region' => 'Marmara',          'tier' => 3, 'sort_order' => 150],
            ['city_name' => 'Denizli',   'city_slug' => 'denizli',   'region' => 'Ege',              'tier' => 3, 'sort_order' => 160],
            ['city_name' => 'Eskişehir', 'city_slug' => 'eskisehir', 'region' => 'İç Anadolu',       'tier' => 3, 'sort_order' => 170],
            ['city_name' => 'Trabzon',   'city_slug' => 'trabzon',   'region' => 'Karadeniz',        'tier' => 3, 'sort_order' => 180],
        ];

        foreach ($cities as $city) {
            CityLandingPage::updateOrCreate(
                ['city_slug' => $city['city_slug']],
                [
                    'city_name'        => $city['city_name'],
                    'region'           => $city['region'],
                    'tier'             => $city['tier'],
                    'title'            => "{$city['city_name']} Köy Ürünleri — Doğal Süt, Peynir, Tereyağı",
                    'meta_title'       => "{$city['city_name']} Köy Ürünleri | Orhan Babanın Çiftliği",
                    'meta_description' => "Çorum köyünden {$city['city_name']}'a soğuk zincir kargo ile doğal köy ürünleri. Taze süt, köy peyniri, tereyağı. {$city['city_name']}'a kapınıza teslimat.",
                    'hero_heading'     => "{$city['city_name']}'a Doğal Köy Ürünleri — Çorum'dan Kapınıza",
                    'hero_description' => "Çorum Büyük Palabıyık Köyü'nden {$city['city_name']} adresinize soğuk zincir kargo ile doğal köy ürünleri. Taze süt, peynir, tereyağı, yumurta ve bal.",
                    'content'          => null,
                    'shipping_note'    => "Çorum'dan {$city['city_name']}'a soğuk zincir kargo ile 1-2 iş günü içinde teslimat.",
                    'delivery_time'    => '1-2 iş günü',
                    'is_active'        => true,
                    'sort_order'       => $city['sort_order'],
                ],
            );
        }
    }
}
