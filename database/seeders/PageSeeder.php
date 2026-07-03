<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'title'            => 'Hakkımızda',
                'slug'             => 'hakkimizda',
                'excerpt'          => 'Bu sayfa örnek bir "Hakkımızda" içeriğidir. Kendi kurumsal metninizle değiştirin.',
                'content'          => '<h2>Hakkımızda</h2>'
                    . '<p>Bu, Laravel Base kiti ile gelen örnek bir sayfadır. Admin panelinden '
                    . '(Sayfalar) içeriği düzenleyerek kendi kurumsal metninizi ekleyebilirsiniz.</p>'
                    . '<p>Buraya vizyonunuzu, misyonunuzu ve kısa hikâyenizi yazın.</p>',
                'sections'         => null,
                'status'           => 'published',
                'sort_order'       => 1,
                'meta_title'       => 'Hakkımızda',
                'meta_description' => 'Örnek Hakkımızda sayfası.',
                'published_at'     => now(),
            ],
            [
                'title'            => 'Gizlilik Politikası',
                'slug'             => 'gizlilik-politikasi',
                'excerpt'          => 'Kişisel verilerin korunmasına ilişkin örnek gizlilik politikası metni.',
                'content'          => '<h2>Gizlilik Politikası</h2>'
                    . '<p>Bu örnek metni kendi gizlilik politikanızla değiştirin. KVKK/GDPR kapsamında '
                    . 'hangi verileri topladığınızı, nasıl kullandığınızı ve kullanıcı haklarını burada açıklayın.</p>',
                'sections'         => null,
                'status'           => 'published',
                'sort_order'       => 2,
                'meta_title'       => 'Gizlilik Politikası',
                'meta_description' => 'Kişisel verilerin korunması ve çerez politikası.',
                'published_at'     => now(),
            ],
            [
                'title'            => 'Kullanım Koşulları',
                'slug'             => 'kullanim-kosullari',
                'excerpt'          => 'Web sitesi kullanım koşullarına ilişkin örnek metin.',
                'content'          => '<h2>Kullanım Koşulları</h2>'
                    . '<p>Bu örnek metni kendi kullanım koşullarınızla değiştirin. Siteyi kullanan '
                    . 'ziyaretçilerin uyması gereken kuralları ve fikri mülkiyet haklarını burada belirtin.</p>',
                'sections'         => null,
                'status'           => 'published',
                'sort_order'       => 3,
                'meta_title'       => 'Kullanım Koşulları',
                'meta_description' => 'Web sitesi kullanım koşulları.',
                'published_at'     => now(),
            ],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(
                ['slug' => $page['slug']],
                $page,
            );
        }
    }
}
