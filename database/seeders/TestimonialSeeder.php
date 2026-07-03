<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            [
                'name'       => 'Ayşe Yılmaz',
                'initials'   => 'AY',
                'location'   => 'İstanbul',
                'body'       => 'Yıllardır marketlerden aldığım peynirlerin tadını unutmuştum. Orhan Baba\'nın peynirini tattığımda çocukluğuma döndüm. Artık başka peynir almıyorum!',
                'rating'     => 5.0,
                'sort_order' => 1,
            ],
            [
                'name'       => 'Mehmet Kaya',
                'initials'   => 'MK',
                'location'   => 'Ankara',
                'body'       => 'Çocuklarıma güvenle yedirebileceğim ürünler arıyordum. Orhan Baba\'nın çiftliğini keşfetmem hayatımı değiştirdi. Süt, yoğurt, yumurta... Hepsi muhteşem!',
                'rating'     => 5.0,
                'sort_order' => 2,
            ],
            [
                'name'       => 'Zeynep Demir',
                'initials'   => 'ZD',
                'location'   => 'İzmir',
                'body'       => 'Tereyağının tadı inanılmaz! Annemin yaptığı kahvaltıların tadını aldım yıllar sonra. Teslimat da çok hızlı, soğuk zincir bozulmadan geliyor.',
                'rating'     => 4.5,
                'sort_order' => 3,
            ],
        ];

        foreach ($testimonials as $testimonial) {
            Testimonial::updateOrCreate(
                ['name' => $testimonial['name']],
                $testimonial,
            );
        }
    }
}
