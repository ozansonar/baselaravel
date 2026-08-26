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
            ['name' => 'Duyurular',  'slug' => 'duyurular',  'icon' => 'fa-solid fa-bullhorn',       'sort_order' => 1],
            ['name' => 'Rehberler',  'slug' => 'rehberler',  'icon' => 'fa-solid fa-book-open',      'sort_order' => 2],
            ['name' => 'Teknoloji',  'slug' => 'teknoloji',  'icon' => 'fa-solid fa-microchip',      'sort_order' => 3],
        ];

        foreach ($categories as $cat) {
            BlogCategory::updateOrCreate(['slug' => $cat['slug']], $cat);
        }

        $author = User::where('email', 'admin@example.com')->first()
            ?? User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->first()
            ?? User::first();

        if (! $author) {
            return;
        }

        $body = '<p>Bu, Laravel Base kiti ile gelen örnek bir blog içeriğidir. Admin panelindeki '
            . '<strong>İçerikler</strong> bölümünden bu yazıyı düzenleyebilir veya silebilirsiniz.</p>'
            . '<h2>Alt Başlık</h2>'
            . '<p>Buraya kendi içeriğinizi yazın. Zengin metin editörü ile başlıklar, listeler, '
            . 'bağlantılar ve görseller ekleyebilirsiniz.</p>'
            . '<ul><li>Birinci madde</li><li>İkinci madde</li><li>Üçüncü madde</li></ul>'
            . '<p>İçerik oluşturma sürecinizde kolaylıklar dileriz.</p>';

        $posts = [
            ['cat' => 'duyurular', 'title' => 'Yeni Web Sitemiz Yayında', 'excerpt' => 'Yenilenen kurumsal web sitemizle daha hızlı, modern ve kullanıcı dostu bir deneyim sunuyoruz.', 'days' => 1],
            ['cat' => 'rehberler', 'title' => 'Başlangıç Rehberi: İlk Adımlar', 'excerpt' => 'Platformu en verimli şekilde kullanmanız için hazırladığımız kapsamlı başlangıç rehberi.', 'days' => 4],
            ['cat' => 'teknoloji', 'title' => 'Modern Web Teknolojileri', 'excerpt' => 'Günümüz web geliştirme dünyasında öne çıkan teknolojilere ve trendlere kısa bir bakış.', 'days' => 7],
            ['cat' => 'rehberler', 'title' => 'Verimliliğinizi Artıracak 5 İpucu', 'excerpt' => 'Günlük iş akışınızı iyileştirecek, pratik ve uygulanabilir öneriler bir arada.', 'days' => 11],
            ['cat' => 'duyurular', 'title' => 'Ekibimize Yeni Katılımlar', 'excerpt' => 'Büyüyen ekibimize katılan yeni yetenekleri sizlerle tanıştırmaktan mutluluk duyuyoruz.', 'days' => 15],
            ['cat' => 'teknoloji', 'title' => 'Güvenlik: Bilmeniz Gerekenler', 'excerpt' => 'Dijital dünyada güvende kalmanız için temel güvenlik prensiplerini bir araya getirdik.', 'days' => 20],
        ];

        foreach ($posts as $p) {
            BlogPost::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($p['title'])],
                [
                    'blog_category_id' => BlogCategory::where('slug', $p['cat'])->value('id'),
                    'user_id'          => $author->id,
                    'title'            => $p['title'],
                    'excerpt'          => $p['excerpt'],
                    'body'             => $body,
                    'status'           => \App\Enums\ContentStatus::Published,
                    'published_at'     => now()->subDays($p['days']),
                    'views'            => random_int(20, 400),
                ],
            );
        }
    }
}
