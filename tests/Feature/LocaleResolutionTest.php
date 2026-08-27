<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\Language;
use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which language a visitor lands in.
 *
 * Preference order is: the language they picked, then the best match from their
 * browser, then the site default. Only active languages count.
 */
class LocaleResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();
    }

    /**
     * Symfony's test request helpfully defaults Accept-Language to
     * "en-us,en;q=0.5", so a case that means "no preference at all" has to send
     * the header empty on purpose.
     */
    private function localeAfterVisit(array $headers = []): string
    {
        $this->withHeaders($headers + ['Accept-Language' => ''])->get('/')->assertRedirect();

        return app()->getLocale();
    }

    public function test_a_visitor_without_a_preference_gets_the_default(): void
    {
        $this->assertSame('tr', $this->localeAfterVisit(['Accept-Language' => '']));
    }

    public function test_an_english_browser_gets_english(): void
    {
        $this->assertSame('en', $this->localeAfterVisit(['Accept-Language' => 'en-US,en;q=0.9']));
    }

    /**
     * A regional variant still counts as its base language, otherwise a de-AT
     * visitor would be pushed to the default for no reason.
     */
    public function test_a_regional_variant_matches_the_base_language(): void
    {
        Language::where('code', 'de')->update(['is_active' => true]);
        app(LanguageService::class)->clearCache();

        $this->assertSame('de', $this->localeAfterVisit(['Accept-Language' => 'de-AT,de;q=0.9']));
    }

    public function test_an_unsupported_browser_language_falls_back_to_the_default(): void
    {
        $this->assertSame('tr', $this->localeAfterVisit(['Accept-Language' => 'ja-JP,ja;q=0.9']));
    }

    /**
     * German is seeded but switched off, so it must not win even when the
     * browser asks for it.
     */
    public function test_an_inactive_language_is_not_used(): void
    {
        $this->assertSame('tr', $this->localeAfterVisit(['Accept-Language' => 'de-DE,de;q=0.9']));
    }

    public function test_the_highest_quality_supported_language_wins(): void
    {
        // Japanese is preferred but unsupported; English is next and supported.
        $this->assertSame(
            'en',
            $this->localeAfterVisit(['Accept-Language' => 'ja;q=0.9,en;q=0.8,tr;q=0.7']),
        );
    }

    public function test_the_default_wins_when_it_is_the_best_supported_match(): void
    {
        $this->assertSame(
            'tr',
            $this->localeAfterVisit(['Accept-Language' => 'tr;q=0.9,en;q=0.8']),
        );
    }

    public function test_switching_language_sticks_across_requests(): void
    {
        $this->get(route('locale.switch', 'en'))->assertRedirect();

        $this->assertSame('en', session(SetLocale::SESSION_KEY));

        // The browser still says Turkish; the explicit choice has to win.
        $this->assertSame('en', $this->localeAfterVisit(['Accept-Language' => 'tr,tr-TR;q=0.9']));
    }

    public function test_switching_to_an_unsupported_language_is_ignored(): void
    {
        $this->get(route('locale.switch', 'ja'))->assertRedirect();

        $this->assertNull(session(SetLocale::SESSION_KEY));
        $this->assertSame('tr', $this->localeAfterVisit(['Accept-Language' => '']));
    }

    public function test_a_language_switched_off_later_stops_being_used(): void
    {
        $this->get(route('locale.switch', 'en'));
        $this->assertSame('en', $this->localeAfterVisit());

        Language::where('code', 'en')->update(['is_active' => false]);
        app(LanguageService::class)->clearCache();

        $this->assertSame('tr', $this->localeAfterVisit(), 'Kapatılan dil hâlâ kullanılıyor');
    }

    public function test_the_switcher_lists_the_active_languages(): void
    {
        $html = $this->followingRedirects()->get('/')->getContent();

        // The switcher links straight to the page in the other language rather
        // than to a switching endpoint, so the link is the address itself.
        $this->assertStringContainsString('href="' . route('home', ['locale' => 'en']) . '"', $html);
        $this->assertStringContainsString('Türkçe', $html);
        $this->assertStringContainsString('English', $html);
        $this->assertStringNotContainsString(route('home', ['locale' => 'de']) . '"', $html, 'Pasif dil seçicide görünüyor');
    }

    public function test_the_page_declares_the_active_language(): void
    {
        $html = $this->followingRedirects()->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])->get('/')->getContent();

        $this->assertStringContainsString('<html lang="en"', $html);
        $this->assertStringContainsString('hreflang="tr"', $html);
        $this->assertStringContainsString('hreflang="en"', $html);
        $this->assertStringContainsString('hreflang="x-default"', $html);
    }

    /**
     * With nothing to switch between the control is noise, so it is not shown.
     */
    public function test_the_switcher_is_hidden_when_only_one_language_is_active(): void
    {
        Language::where('code', '!=', 'tr')->update(['is_active' => false]);
        app(LanguageService::class)->clearCache();

        $html = $this->followingRedirects()->get('/')->getContent();

        $this->assertStringNotContainsString('lang-switcher', $html);
    }
}
