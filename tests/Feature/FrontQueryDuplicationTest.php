<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ön yüzde aynı sorgunun iki kez çalışmaması.
 *
 * Mükerrer sorgular tek bir sayfanın derdi değildi: alt bilgideki "Hakkımızda"
 * bağlantısı yüzünden aynı "hangi çeviri grubundan" sorgusu her ekranda iki kez
 * gidiyordu — adres çevirici ve menü çevirici aynı aramayı ayrı ayrı yapıyordu.
 *
 * Denetim tek sayfaya değil birden çok rotaya bakıyor: ortak bir bileşene
 * (düzen, menü, ayarlar) sızan bir tekrar ancak böyle yakalanır.
 *
 * Mükerrer sayımı Debugbar'ınkiyle aynı: sorgu metni + bağlamalar birlikte
 * karşılaştırılıyor. Yalnız metne bakılsaydı farklı slug'lar için çalışan aynı
 * kalıp yanlışlıkla tekrar sayılırdı.
 *
 * NOT: mail yapılandırmasının artık her istekte değil yalnız mail gönderilirken
 * okunması buradan denetlenemiyor — MailConfigServiceProvider testlerde zaten
 * kendini devre dışı bırakıyor (runningUnitTests), yani böyle bir denetim her
 * hâlükârda geçerdi. O değişiklik tarayıcıda Debugbar ile doğrulandı.
 */
final class FrontQueryDuplicationTest extends TestCase
{
    use RefreshDatabase;

    private BlogPost $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();

        $author = User::factory()->create();

        $category = BlogCategory::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(),
            'name' => 'Duyurular', 'slug' => 'duyurular',
        ]);

        $this->post = BlogPost::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(),
            'blog_category_id' => $category->id, 'user_id' => $author->id,
            'title' => 'Yazı', 'slug' => 'yazi', 'body' => 'Gövde.',
            'status' => ContentStatus::Published, 'published_at' => now()->subDay(),
        ]);

        // Aynı kategoriden ikinci yazı: ilgili içerikler bölümü çalışsın.
        BlogPost::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(),
            'blog_category_id' => $category->id, 'user_id' => $author->id,
            'title' => 'İkinci', 'slug' => 'ikinci', 'body' => 'Gövde.',
            'status' => ContentStatus::Published, 'published_at' => now()->subDays(2),
        ]);

        $page = Page::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(),
            'title' => 'Hakkımızda', 'slug' => 'hakkimizda', 'content' => 'Metin',
            'status' => ContentStatus::Published,
        ]);

        // Menüde sayfaya giden bir bağlantı: çeviri grubu araması buradan da
        // tetikleniyor, asıl mükerrer bu ikilikten doğuyordu.
        $menu = Menu::create(['locale' => 'tr', 'name' => 'Header', 'location' => 'header', 'is_active' => true]);

        MenuItem::create([
            'menu_id' => $menu->id, 'label' => 'Hakkımızda', 'link_type' => 'route',
            'route_name' => 'pages.show', 'route_params' => ['slug' => $page->slug],
            'is_active' => true, 'sort_order' => 1,
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function frontRoutes(): array
    {
        $routes = [
            '/tr',
            '/tr/blog',
            '/tr/blog/duyurular',
            '/tr/blog/duyurular/yazi',
            '/tr/hakkimizda',
            '/tr/galeri',
            '/tr/iletisim',
            '/tr/sikca-sorulan-sorular',
        ];

        return array_combine($routes, array_map(static fn (string $r): array => [$r], $routes));
    }

    /**
     * Ölçümü gerçek bir isteğe benzetir.
     *
     * Testte kap istekler arasında yaşıyor, yani istek başına saklanan
     * değerler (dil listesi, adres çözümleri, çeviri grupları) ikinci isteğe
     * taşınıyor ve sayfa olduğundan az sorgu atıyormuş gibi görünüyor.
     * Gerçekte her istek boş bir kapla başlıyor. Önbellek (dosya/veritabanı)
     * ise gerçekten isteklerin ötesinde yaşadığı için dokunulmuyor.
     */
    private function freshRequestScope(): void
    {
        foreach ([
            \App\Services\TranslationGroupResolver::class,
            \App\Services\LocalizedUrlService::class,
            \App\Services\LanguageService::class,
            \App\Services\MenuService::class,
            \App\Services\MenuItemService::class,
            \App\Services\TranslationService::class,
        ] as $service) {
            $this->app->forgetInstance($service);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('frontRoutes')]
    public function test_no_query_runs_twice_on(string $url): void
    {
        // İlk istek önbellekleri dolduruyor; ölçüm ısınmış önbellek ama taze
        // istek kapsamı üzerinden yapılıyor.
        $this->get($url)->assertOk();
        $this->freshRequestScope();

        $queries = [];

        DB::listen(function ($query) use (&$queries): void {
            // Debugbar'ın saydığı gibi: metin + bağlamalar birlikte.
            $queries[] = $query->sql . ' :: ' . json_encode($query->bindings);
        });

        $this->get($url)->assertOk();

        $counts = array_filter(array_count_values($queries), static fn (int $n): bool => $n > 1);
        arsort($counts);

        $satirlar = [];

        foreach (array_slice($counts, 0, 5, true) as $sql => $n) {
            $satirlar[] = "  {$n}×  " . Str::limit($sql, 130);
        }

        $this->assertSame(
            [],
            $satirlar,
            "{$url} → mükerrer sorgu:\n" . implode("\n", $satirlar),
        );
    }

    /**
     * Aynı slug hem adres çeviricisinden hem menü çeviricisinden soruluyor;
     * ikisi de tek çözücüyü paylaşmalı.
     */
    public function test_the_two_slug_resolvers_share_one_lookup(): void
    {
        $this->get('/tr/hakkimizda')->assertOk();
        $this->freshRequestScope();

        $sayac = 0;

        DB::listen(function ($query) use (&$sayac): void {
            // Yalnız aynı slug sayılıyor: alt bilgide başka sayfalara da
            // bağlantı var ve onların araması ayrı bir soru, tekrar değil.
            if (str_contains($query->sql, 'lang_group_id')
                && str_contains($query->sql, 'pages')
                && in_array('hakkimizda', $query->bindings, true)) {
                $sayac++;
            }
        });

        $this->get('/tr/hakkimizda')->assertOk();

        $this->assertLessThanOrEqual(
            1,
            $sayac,
            "Sayfanın çeviri grubu {$sayac} kez soruldu; iki çözücü ortak çözücüyü kullanmıyor",
        );
    }

    /** Sayfaların içeriği bozulmamalı. */
    public function test_the_pages_still_render_their_content(): void
    {
        $html = (string) $this->get('/tr/blog/duyurular/yazi')->assertOk()->getContent();

        $this->assertStringContainsString('Yazı', $html);
        // İlgili yazı ve kategorisi: kategori artık ana yazıdan paylaşılıyor,
        // ekranda görünmeye devam etmeli.
        $this->assertStringContainsString('İkinci', $html);
        $this->assertStringContainsString('Duyurular', $html);

        $this->assertStringContainsString('Hakkımızda', (string) $this->get('/tr/hakkimizda')->assertOk()->getContent());
    }
}
