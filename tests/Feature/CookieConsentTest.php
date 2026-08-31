<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConsentCategory;
use App\Models\Consent;
use App\Models\PageView;
use App\Models\Setting;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Çerez rızası.
 *
 * Öncesinde hiçbir rıza mekanizması yoktu: Google Analytics ve Tag Manager
 * ayar doluysa koşulsuz yükleniyor, projenin kendi ziyaret kaydı da ilk
 * istekten itibaren IP ve oturum kimliği yazıyordu. IP maskeleme vardı ama
 * 90 gün *sonra* devreye giriyordu — yani veri önce toplanıp sonra
 * anonimleştiriliyordu.
 */
class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
    }

    private function withTracking(): void
    {
        Setting::setValue('google_analytics_id', 'G-SINAMA123');
        Setting::setValue('google_tag_manager_id', 'GTM-SINAMA');
        Setting::clearSettingsCache();
    }

    /**
     * Çerez düz metin veriliyor; `withCookies()` onu uygulamanın beklediği
     * biçimde şifreleyerek gönderiyor.
     *
     * @param list<string> $categories
     * @return array<string, string>
     */
    private function consentCookie(array $categories, int $version = ConsentService::VERSION): array
    {
        return [ConsentService::COOKIE => (string) json_encode([
            'version'    => $version,
            'token'      => '11111111-1111-4111-8111-111111111111',
            'categories' => $categories,
        ])];
    }

    // ── Rıza alınmadan hiçbir şey çalışmıyor ─────────────────────

    public function test_the_banner_is_shown_until_a_choice_is_made(): void
    {
        $this->get('/tr')
            ->assertOk()
            ->assertSee('cookieConsent')
            ->assertDontSee('cc-banner--hidden');
    }

    public function test_google_scripts_are_not_emitted_before_consent(): void
    {
        $this->withTracking();

        $html = $this->get('/tr')->assertOk()->getContent();

        $this->assertStringNotContainsString('G-SINAMA123', $html);
        $this->assertStringNotContainsString('GTM-SINAMA', $html);
    }

    public function test_the_tracker_script_is_not_emitted_before_consent(): void
    {
        $this->assertStringNotContainsString(
            'analytics-tracker.js',
            $this->get('/tr')->assertOk()->getContent(),
        );
    }

    /**
     * Betik rıza olmadan yüklenmiyor ama uç nokta herkese açık; doğrudan
     * istek atan biri kaydı yine de oluşturabilirdi.
     */
    public function test_the_tracking_endpoint_records_nothing_before_consent(): void
    {
        $this->postJson('/api/analytics/track', [
            'url'  => 'https://ornek.test/tr/blog',
            'path' => '/tr/blog',
        ])->assertStatus(202)->assertJsonPath('skipped', 'consent');

        $this->assertSame(0, PageView::count());
    }

    // ── Rıza verildikten sonra ───────────────────────────────────

    public function test_analytics_consent_turns_on_google_analytics_and_the_tracker(): void
    {
        $this->withTracking();

        $html = $this->withCookies($this->consentCookie(['analytics']))
            ->get('/tr')->assertOk()->getContent();

        $this->assertStringContainsString('G-SINAMA123', $html);
        $this->assertStringContainsString('analytics-tracker.js', $html);

        // Pazarlama ayrı bir karar; analitik onu açmıyor.
        $this->assertStringNotContainsString('GTM-SINAMA', $html);
    }

    public function test_marketing_consent_turns_on_the_tag_manager(): void
    {
        $this->withTracking();

        $html = $this->withCookies($this->consentCookie(['marketing']))
            ->get('/tr')->assertOk()->getContent();

        $this->assertStringContainsString('GTM-SINAMA', $html);
        $this->assertStringNotContainsString('G-SINAMA123', $html);
    }

    public function test_the_tracking_endpoint_records_once_analytics_is_allowed(): void
    {
        // JSON isteklerinde çerezler yalnızca `withCredentials()` ile
        // gönderiliyor; tarayıcı da aynı kaynağa `credentials: 'same-origin'`
        // ile gidiyor (bkz. public/js/analytics-tracker.js).
        $this->withCredentials()
            ->withCookies($this->consentCookie(['analytics']))
            ->postJson('/api/analytics/track', [
                'url'  => 'https://ornek.test/tr/blog',
                'path' => '/tr/blog',
            ])->assertStatus(202);

        $this->assertSame(1, PageView::count());
    }

    public function test_the_banner_is_hidden_once_a_choice_is_made(): void
    {
        $this->withCookies($this->consentCookie([]))
            ->get('/tr')
            ->assertOk()
            ->assertSee('cc-banner--hidden');
    }

    // ── Tercihin kaydedilmesi ────────────────────────────────────

    public function test_accepting_everything_records_every_optional_category(): void
    {
        $this->from('/tr')->post('/cerez-tercihi', ['choice' => 'all'])->assertRedirect('/tr');

        $consent = Consent::latest('id')->first();

        $this->assertNotNull($consent);
        $this->assertSame(['analytics', 'marketing'], $consent->categories);
        $this->assertSame(ConsentService::VERSION, $consent->version);
    }

    /**
     * Reddetmek de bir karardır ve kaydedilmelidir: ispat yükü "izin verdi"
     * kadar "vermedi" için de geçerli.
     */
    public function test_refusing_everything_is_recorded_as_a_decision(): void
    {
        $this->from('/tr')->post('/cerez-tercihi', ['choice' => 'necessary'])->assertRedirect('/tr');

        $consent = Consent::latest('id')->first();

        $this->assertNotNull($consent);
        $this->assertSame([], $consent->categories);
    }

    /**
     * Betiksiz durumda "Tümünü kabul et" düğmesi yalnızca o an işaretli
     * kutuları gönderirdi — yani hiçbirini. Kararı sunucunun vermesi bu
     * yüzden.
     */
    public function test_the_choice_wins_over_the_checkboxes(): void
    {
        $this->from('/tr')->post('/cerez-tercihi', [
            'choice'     => 'necessary',
            'categories' => ['analytics', 'marketing'],
        ])->assertRedirect();

        $this->assertSame([], Consent::latest('id')->first()?->categories);
    }

    public function test_a_custom_choice_records_only_what_was_ticked(): void
    {
        $this->from('/tr')->post('/cerez-tercihi', [
            'choice'     => 'custom',
            'categories' => ['analytics'],
        ])->assertRedirect();

        $this->assertSame(['analytics'], Consent::latest('id')->first()?->categories);
    }

    public function test_an_unknown_category_is_refused(): void
    {
        $this->from('/tr')->post('/cerez-tercihi', [
            'choice'     => 'custom',
            'categories' => ['analytics', 'uydurma'],
        ])->assertSessionHasErrors('categories.1');

        $this->assertSame(0, Consent::count());
    }

    public function test_a_missing_choice_is_refused(): void
    {
        $this->from('/tr')->post('/cerez-tercihi', [])->assertSessionHasErrors('choice');
    }

    /**
     * Zorunlu kategori bir karar değil; kayda "seçilmiş" gibi girerse kayıt
     * neyi kapsadığı konusunda yanıltıcı olur.
     */
    public function test_the_necessary_category_is_never_stored_as_a_choice(): void
    {
        $this->from('/tr')->post('/cerez-tercihi', [
            'choice'     => 'custom',
            'categories' => ['necessary', 'analytics'],
        ])->assertSessionHasErrors('categories.0');
    }

    public function test_the_record_carries_what_is_needed_to_prove_it(): void
    {
        $this->from('/tr')->post('/cerez-tercihi', ['choice' => 'all']);

        $consent = Consent::latest('id')->first();

        $this->assertNotNull($consent);
        $this->assertNotNull($consent->created_at);
        $this->assertNotEmpty($consent->token);
        $this->assertNotEmpty($consent->ip_address);
    }

    /**
     * Tercihi değiştirmek eski kaydı silmiyor: rızanın geçmişi de kayıttır.
     */
    public function test_changing_the_choice_adds_a_record_instead_of_replacing_it(): void
    {
        $this->from('/tr')->post('/cerez-tercihi', ['choice' => 'all']);
        $this->from('/tr')->post('/cerez-tercihi', ['choice' => 'necessary']);

        $this->assertSame(2, Consent::count());
    }

    // ── Sürüm ────────────────────────────────────────────────────

    /**
     * Metin değişirse eski rıza yeni metne verilmiş sayılmaz.
     */
    public function test_a_consent_for_an_older_version_is_asked_again(): void
    {
        $this->withTracking();

        $html = $this->withCookies($this->consentCookie(['analytics'], version: ConsentService::VERSION - 1))
            ->get('/tr')->assertOk()->getContent();

        $this->assertStringNotContainsString('cc-banner--hidden', $html);
        $this->assertStringNotContainsString('G-SINAMA123', $html);
    }

    public function test_a_corrupt_cookie_is_treated_as_no_decision(): void
    {
        $service = app(ConsentService::class);

        $this->withCookies([ConsentService::COOKIE => 'bu json degil'])->get('/tr')->assertOk();

        $this->assertFalse($service->allows(ConsentCategory::Analytics, request()));
    }

    public function test_the_necessary_category_is_always_allowed(): void
    {
        $this->assertTrue(app(ConsentService::class)->allows(ConsentCategory::Necessary, request()));
    }
}
