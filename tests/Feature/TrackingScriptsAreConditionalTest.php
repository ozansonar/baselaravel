<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConsentCategory;
use App\Models\Language;
use App\Models\Setting;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * İzleme betikleri yalnız gerektiğinde basılıyor.
 *
 * İki koşul birden aranıyor ve ikisi de zorunlu:
 *
 *  1. **Panelde bir kimlik girilmiş olmalı.** Boşsa betik sayfaya hiç
 *     eklenmiyor — gereksiz istek gitmiyor, kaynak kodu şişmiyor.
 *  2. **Ziyaretçi izin vermiş olmalı.** Betiği sayfaya koyup "çalışmasın"
 *     demek yeterli değil: etiket yüklendiği anda istek atıyor ve çerezini
 *     kuruyor.
 *
 * Değerler `.env`'den değil veritabanından okunuyor, yani panelden değiştirip
 * kaydetmek anında geçerli oluyor; ayar önbelleği kayıtla birlikte düşüyor.
 */
final class TrackingScriptsAreConditionalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::firstOrCreate(
            ['code' => 'tr'],
            ['name' => 'Türkçe', 'native_name' => 'Türkçe', 'is_active' => true, 'is_default' => true],
        );

        Setting::clearSettingsCache();
    }

    /**
     * İzin çerezi: ziyaretçi analitik ve pazarlamaya evet demiş.
     *
     * @param list<ConsentCategory> $allowed
     */
    private function withConsent(array $allowed): static
    {
        return $this->withCookies([
            ConsentService::COOKIE => (string) json_encode([
                'version'    => ConsentService::VERSION,
                'token'      => '11111111-1111-4111-8111-111111111111',
                'categories' => array_map(static fn (ConsentCategory $c): string => $c->value, $allowed),
            ]),
        ]);
    }

    // ── Google Analytics 4 ──

    public function test_analytics_is_not_rendered_when_no_id_is_set(): void
    {
        $this->withConsent([ConsentCategory::Analytics, ConsentCategory::Marketing])
            ->get('/tr')
            ->assertOk()
            ->assertDontSee('googletagmanager.com/gtag/js', false);
    }

    public function test_analytics_is_rendered_when_the_panel_has_an_id(): void
    {
        Setting::setValue('google_analytics_id', 'G-SINAMA123');

        $this->withConsent([ConsentCategory::Analytics])
            ->get('/tr')
            ->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id=G-SINAMA123', false);
    }

    /**
     * Kimlik girilmiş olsa bile izin yoksa basılmıyor.
     */
    public function test_analytics_needs_consent_too(): void
    {
        Setting::setValue('google_analytics_id', 'G-SINAMA123');

        $this->withConsent([ConsentCategory::Marketing])
            ->get('/tr')
            ->assertOk()
            ->assertDontSee('gtag/js', false);
    }

    /**
     * Panelden değiştirmek anında geçerli olmalı: ayar önbelleği kayıtla
     * birlikte düşüyor, sunucuya dokunmak gerekmiyor.
     */
    public function test_changing_the_id_takes_effect_immediately(): void
    {
        Setting::setValue('google_analytics_id', 'G-ESKI111');

        $this->withConsent([ConsentCategory::Analytics])
            ->get('/tr')->assertOk()->assertSee('G-ESKI111', false);

        Setting::setValue('google_analytics_id', 'G-YENI222');

        $this->withConsent([ConsentCategory::Analytics])
            ->get('/tr')->assertOk()
            ->assertSee('G-YENI222', false)
            ->assertDontSee('G-ESKI111', false);
    }

    // ── Tag Manager ──

    public function test_tag_manager_is_not_rendered_when_no_id_is_set(): void
    {
        $this->withConsent([ConsentCategory::Analytics, ConsentCategory::Marketing])
            ->get('/tr')
            ->assertOk()
            ->assertDontSee('gtm.js?id=', false);
    }

    public function test_tag_manager_is_rendered_when_the_panel_has_an_id(): void
    {
        Setting::setValue('google_tag_manager_id', 'GTM-SINAMA');

        $this->withConsent([ConsentCategory::Marketing])
            ->get('/tr')
            ->assertOk()
            ->assertSee('GTM-SINAMA', false);
    }

    // ── Meta Pixel ──

    /**
     * Bu alan panelde vardı ama hiçbir kod okumuyordu: yönetici kimliği
     * giriyor, hiçbir şey olmuyor ve sebebini göremiyordu.
     */
    public function test_the_pixel_is_rendered_when_the_panel_has_an_id(): void
    {
        Setting::setValue('facebook_pixel_id', '1234567890123456');

        $this->withConsent([ConsentCategory::Marketing])
            ->get('/tr')
            ->assertOk()
            ->assertSee('connect.facebook.net', false)
            ->assertSee('1234567890123456', false);
    }

    public function test_the_pixel_is_not_rendered_when_no_id_is_set(): void
    {
        $this->withConsent([ConsentCategory::Marketing])
            ->get('/tr')
            ->assertOk()
            ->assertDontSee('connect.facebook.net', false);
    }

    public function test_the_pixel_needs_marketing_consent(): void
    {
        Setting::setValue('facebook_pixel_id', '1234567890123456');

        $this->withConsent([ConsentCategory::Analytics])
            ->get('/tr')
            ->assertOk()
            ->assertDontSee('connect.facebook.net', false);
    }

    /**
     * Kullanılmayan bir alan adı CSP'de sürekli açık durmamalı.
     */
    public function test_the_pixel_host_is_only_allowed_when_configured(): void
    {
        $header = (string) $this->get('/tr')->assertOk()->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('connect.facebook.net', $header);

        Setting::setValue('facebook_pixel_id', '1234567890123456');

        $header = (string) $this->get('/tr')->assertOk()->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('connect.facebook.net', $header);
    }
}
