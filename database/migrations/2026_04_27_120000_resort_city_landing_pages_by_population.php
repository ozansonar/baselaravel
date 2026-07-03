<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Şehir landing sayfalarını yeni sıralamaya göre güncelle:
     * Çorum (merkez) en başta, sonrası nüfusa göre büyükten küçüğe.
     * Eksik şehirler (Sakarya, Trabzon) admin panel üzerinden eklenmiş
     * olabileceği için burada sadece sort_order güncellenir.
     */
    public function up(): void
    {
        $order = [
            'corum'     => ['tier' => 1, 'sort_order' => 10],
            'istanbul'  => ['tier' => 1, 'sort_order' => 20],
            'ankara'    => ['tier' => 1, 'sort_order' => 30],
            'izmir'     => ['tier' => 1, 'sort_order' => 40],
            'bursa'     => ['tier' => 1, 'sort_order' => 50],
            'antalya'   => ['tier' => 1, 'sort_order' => 60],
            'konya'     => ['tier' => 2, 'sort_order' => 70],
            'adana'     => ['tier' => 2, 'sort_order' => 80],
            'gaziantep' => ['tier' => 2, 'sort_order' => 90],
            'kocaeli'   => ['tier' => 2, 'sort_order' => 100],
            'mersin'    => ['tier' => 2, 'sort_order' => 110],
            'kayseri'   => ['tier' => 2, 'sort_order' => 120],
            'samsun'    => ['tier' => 2, 'sort_order' => 130],
            'tekirdag'  => ['tier' => 3, 'sort_order' => 140],
            'sakarya'   => ['tier' => 3, 'sort_order' => 150],
            'denizli'   => ['tier' => 3, 'sort_order' => 160],
            'eskisehir' => ['tier' => 3, 'sort_order' => 170],
            'trabzon'   => ['tier' => 3, 'sort_order' => 180],
        ];

        foreach ($order as $slug => $values) {
            DB::table('city_landing_pages')
                ->where('city_slug', $slug)
                ->update($values);
        }

        Cache::forget('city_landing_pages.active');
        Cache::forget('sitemap.urls');
        Cache::forget('sitemap_page.groups');
    }

    public function down(): void
    {
        // Eski sıralamaya geri al (Çorum sondaydı, İstanbul'dan başlıyordu)
        $oldOrder = [
            'istanbul'  => ['tier' => 1, 'sort_order' => 10],
            'ankara'    => ['tier' => 1, 'sort_order' => 20],
            'izmir'     => ['tier' => 1, 'sort_order' => 30],
            'bursa'     => ['tier' => 1, 'sort_order' => 40],
            'antalya'   => ['tier' => 1, 'sort_order' => 50],
            'kocaeli'   => ['tier' => 2, 'sort_order' => 60],
            'adana'     => ['tier' => 2, 'sort_order' => 70],
            'konya'     => ['tier' => 2, 'sort_order' => 80],
            'gaziantep' => ['tier' => 2, 'sort_order' => 90],
            'mersin'    => ['tier' => 2, 'sort_order' => 100],
            'kayseri'   => ['tier' => 2, 'sort_order' => 110],
            'samsun'    => ['tier' => 2, 'sort_order' => 120],
            'tekirdag'  => ['tier' => 3, 'sort_order' => 130],
            'denizli'   => ['tier' => 3, 'sort_order' => 140],
            'eskisehir' => ['tier' => 3, 'sort_order' => 150],
            'corum'     => ['tier' => 4, 'sort_order' => 160],
        ];

        foreach ($oldOrder as $slug => $values) {
            DB::table('city_landing_pages')
                ->where('city_slug', $slug)
                ->update($values);
        }

        Cache::forget('city_landing_pages.active');
        Cache::forget('sitemap.urls');
        Cache::forget('sitemap_page.groups');
    }
};
