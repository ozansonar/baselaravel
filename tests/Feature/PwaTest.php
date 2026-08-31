<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\PwaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Siteyi telefona kurulabilir yapan üç adres.
 *
 * Kurulabilirliğin sınavı görsel değil, biçimsel: Chrome manifest'i okuyor,
 * bildirilen ikon ölçüsüyle dosyanın gerçek ölçüsünü karşılaştırıyor ve
 * tutmuyorsa kurulumu sessizce reddediyor. Buradaki sınamalar tam da o
 * kontrolleri taklit ediyor.
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        $this->seed(\Database\Seeders\SettingSeeder::class);
        Setting::clearSettingsCache();
    }

    // ── Manifest ──

    public function test_the_manifest_is_served_with_the_right_content_type(): void
    {
        $response = $this->get('/site.webmanifest');

        $response->assertOk();
        $this->assertStringContainsString('application/manifest+json', (string) $response->headers->get('content-type'));
    }

    public function test_the_manifest_carries_what_the_browser_needs_to_install(): void
    {
        $data = $this->get('/site.webmanifest')->assertOk()->json();

        $this->assertSame('/', $data['start_url']);
        $this->assertSame('standalone', $data['display']);
        $this->assertNotEmpty($data['name']);
        $this->assertNotEmpty($data['icons']);
    }

    public function test_the_manifest_name_follows_the_panel(): void
    {
        Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'Deneme Kurumsal', 'group' => 'general', 'type' => 'text']);
        Setting::clearSettingsCache();

        $this->get('/site.webmanifest')->assertOk()->assertJsonPath('name', 'Deneme Kurumsal');
    }

    /**
     * Kısa ad 12 karakteri geçerse Android onu kırpıyor; kırpmayı bize
     * bırakmak, ikonun altında yarım kelime görünmesinden iyi.
     */
    public function test_the_short_name_is_kept_short(): void
    {
        Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'Çok Uzun Bir Kurumsal Site Adı', 'group' => 'general', 'type' => 'text']);
        Setting::clearSettingsCache();

        $short = (string) $this->get('/site.webmanifest')->json('short_name');

        $this->assertLessThanOrEqual(12, mb_strlen($short));
    }

    /**
     * Chrome kurulum için 192 ve 512 ölçülerini arıyor ve bildirilen ölçü
     * dosyanın gerçek ölçüsüyle aynı olmalı.
     */
    public function test_the_icons_exist_and_are_exactly_the_declared_size(): void
    {
        $icons = (array) $this->get('/site.webmanifest')->json('icons');

        $declared = [];

        foreach ($icons as $icon) {
            $declared[] = $icon['sizes'];

            // Dosya, testte ayrı bir dizine yazılıyor (phpunit.xml →
            // UPLOADS_PATH). public_path üzerinden bakmak, geliştiricinin
            // kendi makinesinde duran gerçek ikonu bulup sınavı yalancı
            // yeşile boyardı.
            $path = rtrim((string) config('uploads.path'), '/')
                . '/' . ltrim(str_replace('/uploads/', '', (string) $icon['src']), '/');

            $this->assertTrue(File::exists($path), "İkon dosyası yok: {$icon['src']}");

            $info = getimagesize($path);

            $this->assertNotFalse($info);
            $this->assertSame($icon['sizes'], $info[0] . 'x' . $info[1]);
            $this->assertSame('image/png', $info['mime']);
        }

        $this->assertContains('192x192', $declared);
        $this->assertContains('512x512', $declared);
    }

    /**
     * Android ikonu kendi maskesiyle kırpıyor; bunu bildirmeyen ikonlar beyaz
     * bir karenin içinde küçücük görünüyor.
     */
    public function test_a_maskable_icon_is_declared(): void
    {
        $icons = (array) $this->get('/site.webmanifest')->json('icons');

        $purposes = array_column($icons, 'purpose');

        $this->assertContains('maskable', $purposes);
    }

    // ── Servis çalışanı ──

    public function test_the_service_worker_is_served_from_the_root(): void
    {
        $response = $this->get('/sw.js');

        $response->assertOk();
        $this->assertStringContainsString('javascript', (string) $response->headers->get('content-type'));

        // Kökten sunulmazsa kapsamı bulunduğu dizinle sınırlı kalır.
        $this->assertSame('/', $response->headers->get('Service-Worker-Allowed'));
    }

    /**
     * Tarayıcı yeni sürümü, bu dosyanın içeriği değiştiği için fark ediyor.
     * Önbelleklenirse yeni çalışan hiç kurulmaz.
     */
    public function test_the_service_worker_is_never_cached(): void
    {
        $header = (string) $this->get('/sw.js')->headers->get('cache-control');

        $this->assertStringContainsString('no-cache', $header);
    }

    public function test_the_cache_name_changes_when_the_assets_change(): void
    {
        $service = app(PwaService::class);

        $before = $service->assetVersion();

        touch(public_path('css/app.css'), time() + 60);
        clearstatcache();

        $this->assertNotSame($before, $service->assetVersion());
    }

    public function test_the_shell_and_the_offline_page_are_precached(): void
    {
        $body = (string) $this->get('/sw.js')->getContent();

        $this->assertStringContainsString('/offline', $body);
        $this->assertStringContainsString('/css/app.css', $body);
    }

    /**
     * Panel ve hesap alanı önbelleğe girmemeli: biri kişiye özel veri taşıyor,
     * öteki her istekte taze olmalı.
     */
    public function test_the_private_areas_are_left_out_of_the_cache(): void
    {
        $body = (string) $this->get('/sw.js')->getContent();

        $this->assertStringContainsString('admin', $body);
        $this->assertStringContainsString('hesabim', $body);
    }

    // ── Çevrimdışı sayfası ──

    public function test_the_offline_page_renders(): void
    {
        $this->get('/offline')
            ->assertOk()
            ->assertSee(__('site.offline.title'));
    }

    // ── Kapatma ──

    /**
     * Kapalı bir özelliğin adresleri açık kalırsa tarayıcı eski servis
     * çalışanını çalıştırmaya devam eder.
     */
    public function test_everything_is_gone_when_the_feature_is_switched_off(): void
    {
        Setting::updateOrCreate(['key' => 'pwa_enabled'], ['value' => '0', 'group' => 'appearance', 'type' => 'boolean']);
        Setting::clearSettingsCache();

        $this->get('/site.webmanifest')->assertNotFound();
        $this->get('/sw.js')->assertNotFound();
    }

    public function test_the_page_links_to_the_manifest_only_while_it_is_on(): void
    {
        $this->get('/tr')->assertOk()->assertSee('rel="manifest"', false);

        Setting::updateOrCreate(['key' => 'pwa_enabled'], ['value' => '0', 'group' => 'appearance', 'type' => 'boolean']);
        Setting::clearSettingsCache();

        $this->get('/tr')->assertOk()->assertDontSee('rel="manifest"', false);
    }
}
