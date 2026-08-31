<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Popup;
use App\Models\Slider;
use App\Services\BlogCategoryService;
use App\Services\FaqService;
use App\Services\LanguageService;
use App\Services\MenuService;
use App\Services\PageService;
use App\Services\PopupService;
use App\Services\SliderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Önbellekten geri açılabilecek sınıfların izin listesi.
 *
 * `cache.serializable_classes` daraltıldı: önbellekten okunan veri
 * canlandırılırken yalnız listedeki sınıflar kabul ediliyor. Bunun bedeli,
 * listeye girmeyen bir modeli önbelleğe koyan ekranın önbellek dolduktan
 * sonra patlaması — hem de ilk istekte değil, ikinci istekte.
 *
 * Bu yüzden sınav bütün önbellekli yolları iki kez geziyor: birincisi
 * önbelleği dolduruyor, ikincisi onu geri açıyor. Liste eksikse ikinci geçiş
 * düşüyor.
 *
 * Testler dosya sürücüsüyle koşuyor — array sürücüsü hiç serialize etmiyor ve
 * bu sınamayı sessizce anlamsız kılardı.
 */
class CacheSerializationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);

        config(['cache.default' => 'file']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    private function seedContent(): void
    {
        Slider::create([
            'locale' => 'tr', 'title' => 'Slider', 'image' => 'ornek.webp',
            'is_active' => true, 'sort_order' => 1,
        ]);

        Page::create([
            'locale' => 'tr', 'title' => 'Sayfa', 'slug' => 'sayfa',
            'content' => 'İçerik', 'status' => 'published', 'published_at' => now(),
        ]);

        Faq::create(['locale' => 'tr', 'question' => 'Soru?', 'answer' => 'Cevap', 'is_active' => true]);

        Popup::create([
            'locale' => 'tr', 'title' => 'Duyuru', 'description' => 'Metin',
            'is_active' => true, 'pages' => [\App\Enums\PopupPage::All->value],
            'display_mode' => \App\Enums\PopupDisplayMode::Session->value,
            'size' => \App\Enums\PopupSize::Md->value,
        ]);

        BlogCategory::create(['locale' => 'tr', 'name' => 'Genel', 'slug' => 'genel', 'is_active' => true]);
    }

    /**
     * Her önbellekli servis iki kez çağrılıyor: ilki yazıyor, ikincisi geri
     * açıyor. İzin listesi eksikse ikinci çağrı düşer.
     */
    public function test_every_cached_service_can_read_back_what_it_wrote(): void
    {
        $this->seedContent();

        $calls = [
            'slider'         => fn () => app(SliderService::class)->allActive(),
            'page'           => fn () => app(PageService::class)->allPublished(),
            'faq'            => fn () => app(FaqService::class)->allActive(),
            'popup'          => fn () => app(PopupService::class)->getForPage('home'),
            'blog_category'  => fn () => app(BlogCategoryService::class)->allActive(),
            'language'       => fn () => app(LanguageService::class)->active(),
            'menu'           => fn () => app(MenuService::class)->getByLocation('header'),
        ];

        foreach ($calls as $name => $call) {
            $first = $call();
            $second = $call();

            $this->assertSame(
                $first instanceof \Countable ? count($first) : (int) ($first !== null),
                $second instanceof \Countable ? count($second) : (int) ($second !== null),
                "{$name}: önbellekten okunan sonuç ilkinden farklı",
            );
        }
    }

    /**
     * Ayarlar her istekte okunuyor ve önbellekten geliyor; listede olmazsa
     * sitenin tamamı ikinci istekte düşerdi.
     */
    public function test_settings_survive_a_cache_round_trip(): void
    {
        \App\Models\Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'Deneme', 'group' => 'general', 'type' => 'text']);
        \App\Models\Setting::clearSettingsCache();

        $this->assertSame('Deneme', \App\Models\Setting::getValue('site_name'));
        $this->assertSame('Deneme', \App\Models\Setting::getValue('site_name'));
    }

    /**
     * Bekçi: izin listesi boş bırakılırsa (null) sertleştirme kapanır ve bu
     * testler hiçbir şey ölçmez hâle gelir.
     */
    public function test_the_allow_list_is_actually_configured(): void
    {
        $allowed = config('cache.serializable_classes');

        $this->assertIsArray($allowed);
        $this->assertNotEmpty($allowed);
        $this->assertContains(\Illuminate\Database\Eloquent\Collection::class, $allowed);
    }

    /**
     * Ön yüz, önbellek dolu haldeyken de açılmalı: ilk istek önbelleği
     * dolduruyor, ikincisi onu okuyor.
     */
    public function test_the_front_page_opens_twice(): void
    {
        $this->seedContent();

        $this->get('/tr')->assertOk();
        $this->get('/tr')->assertOk();
    }
}
