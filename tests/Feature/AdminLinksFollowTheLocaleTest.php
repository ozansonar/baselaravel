<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Popup;
use App\Models\Slider;
use App\Services\LocalizedUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Panelden girilen bağlantılar ziyaretçinin dilini izler.
 *
 * Yönetici bağlantıyı tam adresiyle yazmak zorundaydı — "/tr/iletisim" — ve
 * her dil için ayrı kayıt gerekiyordu. Bu kaçınılmaz olarak unutuluyordu:
 * İngilizce sayfadaki düğme ziyaretçiyi Türkçe sayfaya götürüyordu.
 *
 * Artık yönetici yalnız "iletisim" yazıyor; ön eki sistem koyuyor. Kayıtlı
 * eski adresler de düzeliyor: baştaki dil kodu atılıp bugünkü dille
 * değiştiriliyor, taşıma göçü gerekmiyor.
 */
final class AdminLinksFollowTheLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
    }

    private function resolve(string $input, string $locale = 'tr'): string
    {
        app()->setLocale($locale);

        return app(LocalizedUrlService::class)->fromInput($input);
    }

    // ── Zincirin kendisi ──

    public function test_a_bare_link_gets_the_current_language(): void
    {
        // Dil kaydı olmayan bir yol olduğu gibi, ön ekiyle çıkıyor.
        $this->assertSame(url('tr/kampanya'), $this->resolve('kampanya', 'tr'));
        $this->assertSame(url('en/kampanya'), $this->resolve('kampanya', 'en'));
    }

    /**
     * Yerleşik bir sayfanın adresi yazıldığında bağlantı o sayfanın bu
     * dildeki adresine gidiyor.
     *
     * Yönetici "iletisim" yazıyor; Türkçe sayfada /tr/iletisim, İngilizce
     * sayfada /en/contact çıkıyor. Zahmet ortadan kalkan tam olarak bu:
     * her dil için ayrı kayıt tutmak gerekmiyor.
     */
    public function test_a_built_in_page_link_lands_on_that_languages_address(): void
    {
        $this->assertSame(url('tr/iletisim'), $this->resolve('iletisim', 'tr'));
        $this->assertSame(url('en/contact'), $this->resolve('iletisim', 'en'));
    }

    public function test_a_leading_slash_changes_nothing(): void
    {
        $this->assertSame(url('tr/iletisim'), $this->resolve('/iletisim', 'tr'));
    }

    /** Eski kayıtlar "/tr/iletisim" diye duruyor; ön ek bugünküyle değişmeli. */
    public function test_an_old_record_with_its_own_prefix_still_follows_the_visitor(): void
    {
        $this->assertSame(url('en/contact'), $this->resolve('/tr/iletisim', 'en'));
        $this->assertSame(url('tr/iletisim'), $this->resolve('/en/iletisim', 'tr'));
        // Yerleşik olmayan bir yolda yalnız ön ek değişiyor.
        $this->assertSame(url('en/kampanya'), $this->resolve('/tr/kampanya', 'en'));
    }

    public function test_a_deep_path_keeps_its_shape(): void
    {
        $this->assertSame(url('en/blog/teknoloji/bir-yazi'), $this->resolve('/tr/blog/teknoloji/bir-yazi', 'en'));
    }

    public function test_the_bare_language_root_is_reachable(): void
    {
        $this->assertSame(url('en'), $this->resolve('/tr', 'en'));
        $this->assertSame(url('tr'), $this->resolve('/', 'tr'));
    }

    /**
     * Siteden çıkan ya da sayfaya gitmeyen adreslere dokunulmamalı.
     */
    public function test_links_that_leave_the_site_are_left_alone(): void
    {
        foreach ([
            'https://ornek.com/sayfa',
            'http://ornek.com',
            '//cdn.ornek.com/dosya.pdf',
            'mailto:bilgi@ornek.com',
            'tel:+905551112233',
            '#bolum',
            '?sayfa=2',
        ] as $adres) {
            $this->assertSame($adres, $this->resolve($adres, 'en'), "{$adres} değiştirildi");
        }
    }

    public function test_an_empty_link_is_harmless(): void
    {
        $this->assertSame('#', $this->resolve('', 'tr'));
        $this->assertSame('#', $this->resolve('   ', 'tr'));
    }

    /**
     * "tr" ile başlayan ama dil kodu olmayan bir yol kesilmemeli.
     */
    public function test_a_path_that_merely_starts_like_a_language_is_not_trimmed(): void
    {
        // "trafik" bir dil kodu değil; olduğu gibi kalmalı.
        $this->assertSame(url('tr/trafik-raporu'), $this->resolve('trafik-raporu', 'tr'));
        // Sitede yayınlanmayan bir dil kodu da yol sayılıyor.
        $this->assertSame(url('tr/de/sayfa'), $this->resolve('de/sayfa', 'tr'));
    }

    // ── Ekranlarda ──

    public function test_a_slider_button_follows_the_visitor(): void
    {
        Slider::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(),
            'title' => 'Bir slayt', 'button_text' => 'Bize ulaşın',
            'button_url' => 'iletisim', 'is_active' => true, 'sort_order' => 0,
        ]);

        $this->assertStringContainsString('/tr/iletisim', (string) $this->get('/tr')->assertOk()->getContent());
        // İngilizce sayfada düğme İngilizce adrese gidiyor.
        $this->assertStringContainsString('/en/contact', (string) $this->get('/en')->assertOk()->getContent());
    }

    public function test_a_popup_button_follows_the_visitor(): void
    {
        Popup::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(),
            'title' => 'Duyuru', 'button_text' => 'Detay', 'button_url' => 'hakkimizda',
            'size' => 'md', 'pages' => ['all'], 'is_active' => true, 'sort_order' => 0,
        ]);

        $this->assertStringContainsString('/en/hakkimizda', (string) $this->get('/en')->assertOk()->getContent());
    }

    public function test_a_custom_menu_link_follows_the_visitor(): void
    {
        $menu = Menu::where('location', 'header')->where('locale', 'tr')->firstOrFail();

        MenuItem::create([
            'locale' => 'tr', 'menu_id' => $menu->id, 'label' => 'Kampanya',
            'link_type' => 'url', 'url' => 'kampanya',
            'display_type' => 'link', 'is_active' => true, 'sort_order' => 99,
        ]);

        app(\App\Services\MenuService::class)->clearAllCaches();

        $this->assertStringContainsString('/tr/kampanya', (string) $this->get('/tr')->assertOk()->getContent());

        app(\App\Services\MenuService::class)->clearAllCaches();

        $this->assertStringContainsString('/en/kampanya', (string) $this->get('/en')->assertOk()->getContent());
    }

    public function test_an_external_menu_link_is_not_rewritten(): void
    {
        $menu = Menu::where('location', 'header')->where('locale', 'tr')->firstOrFail();

        MenuItem::create([
            'locale' => 'tr', 'menu_id' => $menu->id, 'label' => 'Dis Baglanti',
            'link_type' => 'url', 'url' => 'https://ornek.com/duyuru',
            'display_type' => 'link', 'is_active' => true, 'sort_order' => 98,
        ]);

        app(\App\Services\MenuService::class)->clearAllCaches();

        $html = (string) $this->get('/tr')->assertOk()->getContent();

        $this->assertStringContainsString('https://ornek.com/duyuru', $html);
        $this->assertStringNotContainsString('/tr/https:', $html);
    }
}
