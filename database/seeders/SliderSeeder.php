<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Slider;
use Illuminate\Database\Seeder;

class SliderSeeder extends Seeder
{
    public function run(): void
    {
        $sliders = [
            [
                'title'       => 'Çiftlikten Sofranıza Doğal Lezzetler',
                'subtitle'    => 'Katkısız, hormonsuz, doğal ürünlerimizle sağlıklı yaşamın tadını çıkarın.',
                'image'       => 'sliders/slider-1.webp',
                'button_text' => 'Ürünleri Keşfet',
                'button_url'  => '/urunler',
                'sort_order'  => 1,
                'is_active'   => true,
            ],
            [
                'title'       => 'Taze Süt Ürünleri Her Gün Kapınızda',
                'subtitle'    => 'Günlük sağılan sütlerden yapılan peynir, yoğurt ve tereyağı.',
                'image'       => 'sliders/slider-2.webp',
                'button_text' => 'Süt Ürünleri',
                'button_url'  => '/kategori/sut-urunleri',
                'sort_order'  => 2,
                'is_active'   => true,
            ],
            [
                'title'       => 'Organik Bal & Ev Yapımı Reçeller',
                'subtitle'    => 'Yaylalardan sofranıza, doğanın en tatlı armağanları.',
                'image'       => 'sliders/slider-3.webp',
                'button_text' => 'Bal & Reçel',
                'button_url'  => '/kategori/bal-recel',
                'sort_order'  => 3,
                'is_active'   => true,
            ],
        ];

        foreach ($sliders as $slider) {
            Slider::updateOrCreate(
                ['sort_order' => $slider['sort_order']],
                $slider,
            );
        }
    }
}
