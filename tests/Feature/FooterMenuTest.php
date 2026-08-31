<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\LanguageService;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Alt bilginin bağlantıları panelden yönetiliyor.
 *
 * Bağlantılar Blade'e yazılıydı: yöneticinin alt bilgiye bir bağlantı eklemesi
 * için koda dokunmak gerekiyordu. Menü modülü "footer" konumunu zaten
 * tanıyordu, orada bir menü yoktu.
 *
 * Sütunlar ağacın kendisinden geliyor: kök öğe sütun başlığı, çocukları o
 * sütunun bağlantıları. Böylece sütun sayısı da yöneticinin elinde.
 */
final class FooterMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();
        app(MenuService::class)->clearAllCaches();
    }

    private function footerMenu(string $locale = 'tr'): Menu
    {
        return Menu::where('location', 'footer')->where('locale', $locale)->firstOrFail();
    }

    private function column(string $label, string $locale = 'tr'): MenuItem
    {
        return $this->footerMenu($locale)->rootItems->firstWhere('label', $label)
            ?? $this->fail("Alt bilgide '{$label}' sütunu yok");
    }

    /**
     * @return list<string>
     */
    private function footerLinks(string $url = '/tr'): array
    {
        $html = (string) $this->get($url)->assertOk()->getContent();

        $footer = substr($html, (int) strpos($html, '<footer'));

        preg_match_all('/class="footer-link"[^>]*>([^<]+)</', $footer, $matches);

        return array_map(trim(...), $matches[1]);
    }

    // ── Göç: sayfa değişmemeli ──

    /**
     * Göçten sonra alt bilgi eskisiyle aynı görünmeli. Tohumlanan bağlantılar
     * o güne kadar Blade'in bastıklarının birebir aynısı.
     */
    public function test_the_seeded_footer_shows_exactly_what_it_used_to_show(): void
    {
        $this->assertSame(
            ['Anasayfa', 'İçerikler', 'Galeri', 'SSS', 'İletişim',
                'Hakkımızda', 'Gizlilik Politikası', 'Kullanım Koşulları'],
            $this->footerLinks(),
        );
    }

    public function test_the_columns_keep_their_headings(): void
    {
        $html = (string) $this->get('/tr')->assertOk()->getContent();
        $footer = substr($html, (int) strpos($html, '<footer'));

        $this->assertStringContainsString('<h5>Menü</h5>', $footer);
        $this->assertStringContainsString('<h5>Kurumsal</h5>', $footer);
    }

    // ── Panelden yönetim ──

    public function test_a_link_added_in_the_panel_shows_up_in_the_footer(): void
    {
        MenuItem::create([
            'locale'       => 'tr',
            'menu_id'      => $this->footerMenu()->id,
            'parent_id'    => $this->column('Kurumsal')->id,
            'label'        => 'KVKK Aydinlatma',
            'link_type'    => 'url',
            'url'          => '/tr/kvkk',
            'display_type' => 'link',
            'is_active'    => true,
            'sort_order'   => 9,
        ]);

        app(MenuService::class)->clearAllCaches();

        $this->assertContains('KVKK Aydinlatma', $this->footerLinks());
    }

    public function test_a_link_switched_off_disappears(): void
    {
        $link = $this->column('Kurumsal')->activeChildren->firstWhere('label', 'Hakkımızda');
        $link->update(['is_active' => false]);

        app(MenuService::class)->clearAllCaches();

        $this->assertNotContains('Hakkımızda', $this->footerLinks());
        // Öteki bağlantılar yerinde kalmalı.
        $this->assertContains('Gizlilik Politikası', $this->footerLinks());
    }

    public function test_a_deleted_link_disappears(): void
    {
        $this->column('Menü')->activeChildren->firstWhere('label', 'Galeri')->delete();

        app(MenuService::class)->clearAllCaches();

        $this->assertNotContains('Galeri', $this->footerLinks());
    }

    public function test_reordering_the_links_reorders_the_column(): void
    {
        $column = $this->column('Kurumsal');

        // Sıra sütunu `unsignedInteger`: eksi bir değer SQLite'ta sessizce
        // kabul edilir ama üretimdeki MySQL onu reddeder. Öne almak için
        // sıralama baştan, geçerli değerlerle yazılıyor.
        foreach (['Kullanım Koşulları' => 1, 'Hakkımızda' => 2, 'Gizlilik Politikası' => 3] as $label => $position) {
            $column->activeChildren->firstWhere('label', $label)?->update(['sort_order' => $position]);
        }

        app(MenuService::class)->clearAllCaches();

        $links = $this->footerLinks();

        $this->assertSame('Kullanım Koşulları', $links[array_search('Hakkımızda', $links, true) - 1]);
    }

    /** Sütunun kendisi kapatılırsa o sütun hiç basılmamalı. */
    public function test_switching_off_a_column_removes_the_whole_column(): void
    {
        $this->column('Kurumsal')->update(['is_active' => false]);

        app(MenuService::class)->clearAllCaches();

        $html = (string) $this->get('/tr')->assertOk()->getContent();
        $footer = substr($html, (int) strpos($html, '<footer'));

        $this->assertStringNotContainsString('<h5>Kurumsal</h5>', $footer);
        $this->assertStringContainsString('<h5>Menü</h5>', $footer);
    }

    /** Bağlantısı kalmayan bir sütun başlık olarak ekranda asılı kalmamalı. */
    public function test_a_column_with_no_links_left_is_not_printed(): void
    {
        foreach ($this->column('Kurumsal')->activeChildren as $link) {
            $link->delete();
        }

        app(MenuService::class)->clearAllCaches();

        $html = (string) $this->get('/tr')->assertOk()->getContent();
        $footer = substr($html, (int) strpos($html, '<footer'));

        $this->assertStringNotContainsString('<h5>Kurumsal</h5>', $footer);
    }

    public function test_a_new_column_becomes_a_new_footer_block(): void
    {
        $column = MenuItem::create([
            'locale' => 'tr', 'menu_id' => $this->footerMenu()->id, 'parent_id' => null,
            'label' => 'Destek', 'link_type' => 'url', 'url' => '#',
            'display_type' => 'link', 'is_active' => true, 'sort_order' => 5,
        ]);

        MenuItem::create([
            'locale' => 'tr', 'menu_id' => $this->footerMenu()->id, 'parent_id' => $column->id,
            'label' => 'Yardim Merkezi', 'link_type' => 'url', 'url' => '/tr/yardim',
            'display_type' => 'link', 'is_active' => true, 'sort_order' => 0,
        ]);

        app(MenuService::class)->clearAllCaches();

        $html = (string) $this->get('/tr')->assertOk()->getContent();
        $footer = substr($html, (int) strpos($html, '<footer'));

        $this->assertStringContainsString('<h5>Destek</h5>', $footer);
        $this->assertContains('Yardim Merkezi', $this->footerLinks());
    }

    // ── Diller ──

    /**
     * Göç, uygulamanın gönderdiği her dil için menü kuruyor; her dil kendi
     * etiketlerini göstermeli.
     */
    public function test_each_language_shows_its_own_footer_menu(): void
    {
        $this->assertContains('Hakkımızda', $this->footerLinks('/tr'));
        $this->assertContains('About Us', $this->footerLinks('/en'));

        $this->assertNotContains('About Us', $this->footerLinks('/tr'));
        $this->assertNotContains('Hakkımızda', $this->footerLinks('/en'));
    }

    /**
     * Menüsü olmayan dil varsayılan dilinkine düşmeli; alt bilgi boş
     * kalmamalı. Yönetici menü modülünün "başka dile kopyala" akışıyla
     * kendi sürümünü kurana kadar site bağlantısız kalmıyor.
     */
    public function test_a_language_without_its_own_footer_menu_falls_back(): void
    {
        $english = Menu::where('location', 'footer')->where('locale', 'en')->firstOrFail();

        MenuItem::where('menu_id', $english->id)->forceDelete();
        $english->forceDelete();

        app(MenuService::class)->clearAllCaches();

        // Türkçe menüye düşüyor: bağlantılar Türkçe ama alt bilgi dolu.
        $this->assertContains('Hakkımızda', $this->footerLinks('/en'));
    }

    /** Bir dilde yapılan değişiklik ötekini etkilememeli. */
    public function test_editing_one_language_leaves_the_other_alone(): void
    {
        $this->column('Corporate', 'en')
            ->activeChildren->firstWhere('label', 'About Us')
            ->update(['label' => 'Who We Are']);

        app(MenuService::class)->clearAllCaches();

        $this->assertContains('Who We Are', $this->footerLinks('/en'));
        $this->assertContains('Hakkımızda', $this->footerLinks('/tr'));
    }

    /**
     * Sayfa bağlantısı ziyaretçinin okuduğu çeviriye gitmeli: aynı sayfanın
     * İngilizce sürümünün slug'ı farklı.
     */
    public function test_a_page_link_points_at_the_slug_of_the_language_being_read(): void
    {
        $turkish = Page::create([
            'locale' => 'tr', 'title' => 'Hakkımızda', 'slug' => 'hakkimizda',
            'content' => 'Metin', 'status' => 'published',
        ]);

        Page::create([
            'locale' => 'en', 'lang_group_id' => $turkish->lang_group_id,
            'title' => 'About Us', 'slug' => 'about-us',
            'content' => 'Text', 'status' => 'published',
        ]);

        $html = (string) $this->get('/en')->assertOk()->getContent();
        $footer = substr($html, (int) strpos($html, '<footer'));

        $this->assertStringContainsString('/en/about-us', $footer);
        $this->assertStringNotContainsString('/en/hakkimizda', $footer);
    }

    // ── Dayanıklılık ve maliyet ──

    /** Menü hiç yoksa alt bilgi çökmemeli; markanın ve iletişimin yeri duruyor. */
    public function test_the_footer_survives_with_no_menu_at_all(): void
    {
        DB::table('menu_items')->whereIn(
            'menu_id',
            DB::table('menus')->where('location', 'footer')->pluck('id'),
        )->delete();

        DB::table('menus')->where('location', 'footer')->delete();

        app(MenuService::class)->clearAllCaches();

        $html = (string) $this->get('/tr')->assertOk()->getContent();

        $this->assertStringContainsString('<footer', $html);
        $this->assertStringContainsString('site-footer', $html);
    }

    /**
     * Bağlantı sayısı sorgu sayısını değiştirmemeli: değiştiriyorsa bağlantı
     * başına sorgu atılıyor demektir.
     */
    public function test_more_links_do_not_mean_more_queries(): void
    {
        $this->get('/tr')->assertOk();

        $az = $this->countQueries('/tr');

        $column = $this->column('Menü');

        foreach (range(1, 12) as $i) {
            MenuItem::create([
                'locale' => 'tr', 'menu_id' => $this->footerMenu()->id, 'parent_id' => $column->id,
                'label' => "Bag {$i}", 'link_type' => 'url', 'url' => "/tr/bag-{$i}",
                'display_type' => 'link', 'is_active' => true, 'sort_order' => 10 + $i,
            ]);
        }

        app(MenuService::class)->clearAllCaches();
        $this->get('/tr')->assertOk();

        $this->assertSame($az, $this->countQueries('/tr'), 'Bağlantı başına sorgu atılıyor (N+1)');
    }

    private function countQueries(string $url): int
    {
        $count = 0;

        DB::listen(function () use (&$count): void {
            $count++;
        });

        $this->get($url)->assertOk();

        return $count;
    }
}
