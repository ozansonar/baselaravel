<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Sağlıklı Beslenme', 'slug' => 'saglikli-beslenme', 'icon' => 'fa-solid fa-cheese',    'sort_order' => 1],
            ['name' => 'Çiftlik Hayatı',     'slug' => 'ciftlik-hayati',     'icon' => 'fa-solid fa-cow',       'sort_order' => 2],
            ['name' => 'Tarifler',            'slug' => 'tarifler',           'icon' => 'fa-solid fa-utensils',  'sort_order' => 3],
        ];

        foreach ($categories as $cat) {
            BlogCategory::updateOrCreate(['slug' => $cat['slug']], $cat);
        }

        $author = User::where('email', 'ozansonar1@gmail.com')->first();

        if (! $author) {
            return;
        }

        $posts = [
            [
                'blog_category_id' => BlogCategory::where('slug', 'saglikli-beslenme')->value('id'),
                'user_id'          => $author->id,
                'title'            => 'Gerçek Köy Peyniri Nasıl Anlaşılır?',
                'slug'             => 'gercek-koy-peyniri-nasil-anlasilir',
                'excerpt'          => 'Market raflarında "köy peyniri" etiketi altında satılan ürünlerin çoğu gerçek köy peyniri değil. İşte gerçek köy peynirini ayırt etmenin yolları...',
                'body'             => '<p>Market raflarında "köy peyniri" etiketi altında satılan ürünlerin çoğu gerçek köy peyniri değil. İşte gerçek köy peynirini ayırt etmenin yolları...</p>',
                'is_published'     => true,
                'published_at'     => '2026-01-15 09:00:00',
            ],
            [
                'blog_category_id' => BlogCategory::where('slug', 'ciftlik-hayati')->value('id'),
                'user_id'          => $author->id,
                'title'            => "Çiftliğimizde Bir Gün: Sabah 5'ten Akşama",
                'slug'             => 'ciftligimizde-bir-gun-sabah-5ten-aksama',
                'excerpt'          => 'Merak edenler için çiftliğimizde bir günün nasıl geçtiğini anlatıyoruz. Sabah erken kalkış, sağım, yemleme ve daha fazlası...',
                'body'             => '<p>Merak edenler için çiftliğimizde bir günün nasıl geçtiğini anlatıyoruz. Sabah erken kalkış, sağım, yemleme ve daha fazlası...</p>',
                'is_published'     => true,
                'published_at'     => '2026-01-10 09:00:00',
            ],
            [
                'blog_category_id' => BlogCategory::where('slug', 'tarifler')->value('id'),
                'user_id'          => $author->id,
                'title'            => 'Ev Yapımı Yoğurt Nasıl Mayalanır?',
                'slug'             => 'ev-yapimi-yogurt-nasil-mayalanir',
                'excerpt'          => 'Evde gerçek köy yoğurdu yapmak sandığınız kadar zor değil. Anneannemden öğrendiğim geleneksel tarifi sizlerle paylaşıyorum...',
                'body'             => '<p>Evde gerçek köy yoğurdu yapmak sandığınız kadar zor değil. Anneannemden öğrendiğim geleneksel tarifi sizlerle paylaşıyorum...</p>',
                'is_published'     => true,
                'published_at'     => '2026-01-05 09:00:00',
            ],
        ];

        foreach ($posts as $post) {
            BlogPost::updateOrCreate(['slug' => $post['slug']], $post);
        }
    }
}
