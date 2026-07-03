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
                'title'       => 'Laravel Base Kit',
                'subtitle'    => 'Yeniden kullanılabilir başlangıç altyapısı.',
                'image'       => 'sliders/slider-1.webp',
                'button_text' => 'Blog',
                'button_url'  => '/blog',
                'sort_order'  => 1,
                'is_active'   => true,
            ],
            [
                'title'       => 'İçerik Yönetimi Hazır',
                'subtitle'    => 'Blog, sayfa, galeri ve menü yönetimi kutudan çıkar çıkmaz.',
                'image'       => 'sliders/slider-2.webp',
                'button_text' => 'Galeri',
                'button_url'  => '/galeri',
                'sort_order'  => 2,
                'is_active'   => true,
            ],
            [
                'title'       => 'Bize Ulaşın',
                'subtitle'    => 'Sorularınız için iletişim formunu kullanın.',
                'image'       => 'sliders/slider-3.webp',
                'button_text' => 'İletişim',
                'button_url'  => '/iletisim',
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
