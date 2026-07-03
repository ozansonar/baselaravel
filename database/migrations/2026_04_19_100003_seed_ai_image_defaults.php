<?php

declare(strict_types=1);

use App\Models\AiImagePrompt;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // ── Settings (Admin → Ayarlar → AI) ──
        $settings = [
            ['key' => 'ai_image_model',   'value' => 'gemini-2.5-flash-image-preview', 'group' => 'ai', 'type' => 'text'],
            ['key' => 'ai_image_timeout', 'value' => '120',                            'group' => 'ai', 'type' => 'text'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }

        Setting::clearSettingsCache();

        // ── Default prompt templates ──
        $prompts = [
            [
                'name'        => 'Çiftlik Ürün Fotoğrafı',
                'description' => 'Doğal ortamda profesyonel ürün fotoğrafı',
                'prompt'      => 'Rustik ahşap masada taze çiftlik ürünü, sabah güneş ışığı, doğal gölge geçişleri, yumuşak bokeh arka plan, arka planda yeşil çayır, profesyonel yemek ve ürün fotoğrafı stili, sıcak ton, yüksek detay, 35mm objektif görünümü.',
                'sort_order'  => 10,
                'is_active'   => true,
            ],
            [
                'name'        => 'Blog Kapak Görseli',
                'description' => 'Makaleler için dikkat çekici kapak',
                'prompt'      => 'Minimalist blog kapak görseli, modern kompozisyon, pastel renk paleti, yumuşak degrade arka plan, ortada konu vurgusu, boş negatif alan, Unsplash stili editoryal fotoğraf, yüksek kontrast, profesyonel görünüm.',
                'sort_order'  => 20,
                'is_active'   => true,
            ],
            [
                'name'        => 'Çiftlik Manzara',
                'description' => 'Doğa ve kırsal yaşam temalı geniş plan',
                'prompt'      => 'Anadolu kırsalında güneş doğumu, altın saat ışığı, çiftlik evi ve yeşil tarlalar, uzakta dağlar, sabah sisi, pastoral atmosfer, sıcak renk tonları, geniş açı manzara fotoğrafı, gerçekçi detaylar, derin perspektif.',
                'sort_order'  => 30,
                'is_active'   => true,
            ],
            [
                'name'        => 'Sosyal Medya Post',
                'description' => 'Instagram/Facebook için kare paylaşım',
                'prompt'      => 'Sosyal medya için göz alıcı kare görsel, canlı renkler, merkezî kompozisyon, modern typography boşluğu, çağdaş grafik tasarım estetiği, marka vurgusu, temiz ve okunaklı düzen, yüksek engagement potansiyeli.',
                'sort_order'  => 40,
                'is_active'   => true,
            ],
        ];

        foreach ($prompts as $prompt) {
            AiImagePrompt::updateOrCreate(
                ['name' => $prompt['name']],
                $prompt,
            );
        }
    }

    public function down(): void
    {
        Setting::whereIn('key', [
            'ai_image_model',
            'ai_image_timeout',
        ])->delete();

        Setting::clearSettingsCache();

        AiImagePrompt::whereIn('name', [
            'Çiftlik Ürün Fotoğrafı',
            'Blog Kapak Görseli',
            'Çiftlik Manzara',
            'Sosyal Medya Post',
        ])->delete();
    }
};
