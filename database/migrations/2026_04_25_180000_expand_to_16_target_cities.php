<?php

declare(strict_types=1);

use App\Models\AiPromptSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hedef şehir listesi 11 → 16'ya genişletiliyor:
 *  + Kocaeli (Tier 2 — Marmara, TR'nin en yüksek hane geliri)
 *  + Mersin (Tier 2 — Akdeniz, liman ticaret)
 *  + Tekirdağ (Tier 3 — Marmara, sanayi + yüksek gelir)
 *  + Denizli (Tier 3 — Ege, tekstil + ticaret)
 *  + Eskişehir (Tier 3 — İç Anadolu, üniversite + premium tüketici)
 *
 * Hem AiPromptSetting.target_cities (blog rotation), hem de
 * city_landing_pages tablosu (statik landing sayfaları) güncellenir.
 *
 * Sonuç: 16 şehir × 6 ürün × 7 topic = 672 kombinasyon (5.6 ay tekrarsız).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) AiPromptSetting singleton kaydının target_cities'ini güncelle.
        if (DB::table('ai_prompt_settings')->where('id', 1)->exists()) {
            DB::table('ai_prompt_settings')
                ->where('id', 1)
                ->update([
                    'target_cities' => json_encode(
                        AiPromptSetting::defaultTargetCities(),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ),
                    'updated_at' => now(),
                ]);
        }

        // 2) Eksik şehir landing page'lerini ekle. Mevcut olanlara dokunma
        //    (admin elle düzenlemiş olabilir) — sadece city_slug yoksa insert.
        $newCities = [
            ['city_name' => 'Kocaeli',   'city_slug' => 'kocaeli',   'region' => 'Marmara',          'tier' => 2, 'sort_order' => 60],
            ['city_name' => 'Mersin',    'city_slug' => 'mersin',    'region' => 'Akdeniz',          'tier' => 2, 'sort_order' => 100],
            ['city_name' => 'Tekirdağ',  'city_slug' => 'tekirdag',  'region' => 'Marmara',          'tier' => 3, 'sort_order' => 130],
            ['city_name' => 'Denizli',   'city_slug' => 'denizli',   'region' => 'Ege',              'tier' => 3, 'sort_order' => 140],
            ['city_name' => 'Eskişehir', 'city_slug' => 'eskisehir', 'region' => 'İç Anadolu',       'tier' => 3, 'sort_order' => 150],

            // Tier 1+2 zaten seeder'la gelmişti, ek olarak Tier 4 yerel kimlik:
            ['city_name' => 'Çorum',     'city_slug' => 'corum',     'region' => 'Karadeniz',        'tier' => 4, 'sort_order' => 160],

            // Tier 2 eksik kalanlar (eğer seeder hiç çalıştırılmadıysa fallback):
            ['city_name' => 'Adana',     'city_slug' => 'adana',     'region' => 'Akdeniz',          'tier' => 2, 'sort_order' => 70],
            ['city_name' => 'Konya',     'city_slug' => 'konya',     'region' => 'İç Anadolu',       'tier' => 2, 'sort_order' => 80],
            ['city_name' => 'Gaziantep', 'city_slug' => 'gaziantep', 'region' => 'Güneydoğu Anadolu','tier' => 2, 'sort_order' => 90],
            ['city_name' => 'Kayseri',   'city_slug' => 'kayseri',   'region' => 'İç Anadolu',       'tier' => 2, 'sort_order' => 110],
            ['city_name' => 'Samsun',    'city_slug' => 'samsun',    'region' => 'Karadeniz',        'tier' => 2, 'sort_order' => 120],
        ];

        $now = now();
        foreach ($newCities as $city) {
            $exists = DB::table('city_landing_pages')
                ->where('city_slug', $city['city_slug'])
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                continue; // admin elle eklemiş olabilir — dokunma
            }

            DB::table('city_landing_pages')->insert([
                'city_name'        => $city['city_name'],
                'city_slug'        => $city['city_slug'],
                'region'           => $city['region'],
                'tier'             => $city['tier'],
                'title'            => "{$city['city_name']} Köy Ürünleri — Doğal Süt, Peynir, Tereyağı",
                'meta_title'       => "{$city['city_name']} Köy Ürünleri | Orhan Babanın Çiftliği",
                'meta_description' => "Çorum köyünden {$city['city_name']}'a soğuk zincir kargo ile doğal köy ürünleri. Taze süt, köy peyniri, tereyağı. {$city['city_name']}'a kapınıza teslimat.",
                'hero_heading'     => "{$city['city_name']}'a Doğal Köy Ürünleri — Çorum'dan Kapınıza",
                'hero_description' => "Çorum Büyük Palabıyık Köyü'nden {$city['city_name']} adresinize soğuk zincir kargo ile doğal köy ürünleri.",
                'content'          => null,
                'shipping_note'    => "Çorum'dan {$city['city_name']}'a soğuk zincir kargo ile 1-2 iş günü içinde teslimat.",
                'delivery_time'    => '1-2 iş günü',
                'is_active'        => true,
                'sort_order'       => $city['sort_order'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only — şehir genişletmesi geri alınmaz.
    }
};
