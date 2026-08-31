<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CustomRoute;
use App\Models\Language;
use App\Services\CustomRouteService;
use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * robots.txt.
 *
 * It used to be a file in public/, which made it wrong in two different ways.
 * It travelled with the repository, so every project cloned from this base kit
 * pointed search engines at another site's sitemap and went on disallowing
 * paths from modules that had been removed. And it was written by hand, while
 * the addresses it is supposed to describe are opened in the panel — so it fell
 * behind them the moment anyone used that screen.
 *
 * The list is now derived from the routes themselves.
 */
class RobotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();
        app(CustomRouteService::class)->clearCache();
    }

    private function inProduction(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
    }

    private function body(): string
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        return $response->getContent();
    }

    /**
     * The whole fix depends on the route being reached. A file in public/ is
     * served by the web server before PHP ever runs, so if one comes back the
     * route below becomes dead code and nobody finds out.
     */
    public function test_no_static_file_shadows_the_route(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }

    public function test_a_non_production_copy_is_closed_to_search_engines(): void
    {
        $body = $this->body();

        $this->assertStringContainsString('Disallow: /', $body);
        $this->assertStringNotContainsString('Allow: /', $body);
        $this->assertStringNotContainsString('Sitemap:', $body);
    }

    public function test_the_sitemap_line_names_this_site(): void
    {
        $this->inProduction();

        $this->assertStringContainsString('Sitemap: ' . url('/sitemap.xml'), $this->body());
    }

    /**
     * The exact string the base kit shipped with. Worth naming: it is what
     * makes this a bug rather than a preference.
     */
    public function test_no_domain_from_another_project_survives(): void
    {
        $this->inProduction();

        $this->assertStringNotContainsString('orhanbabaninciftligi', $this->body());
    }

    public function test_paths_from_removed_modules_are_gone(): void
    {
        $this->inProduction();

        $body = $this->body();

        $this->assertStringNotContainsString('sepet', $body);
        $this->assertStringNotContainsString('siparis', $body);
    }

    public function test_the_panel_is_disallowed(): void
    {
        $this->inProduction();

        $this->assertStringContainsString('Disallow: /admin/', $this->body());
    }

    public function test_every_published_language_gets_its_own_private_paths(): void
    {
        $this->inProduction();

        $body = $this->body();

        foreach (['tr', 'en'] as $locale) {
            $this->assertStringContainsString("Disallow: /{$locale}/giris", $body);
            $this->assertStringContainsString("Disallow: /{$locale}/hesabim", $body);
            $this->assertStringContainsString("Disallow: /{$locale}/kayit", $body);
        }
    }

    /**
     * Publishing a language is a panel action, not a deploy — the file used to
     * need a developer to keep up.
     */
    public function test_publishing_a_language_extends_the_list(): void
    {
        $this->inProduction();

        $this->assertStringNotContainsString('Disallow: /de/giris', $this->body());

        Language::where('code', 'de')->update(['is_active' => true]);
        app(LanguageService::class)->clearCache();

        $this->assertStringContainsString('Disallow: /de/giris', $this->body());
    }

    /**
     * Built-in paths are Turkish, so their translated addresses come from the
     * custom route table. Disallowing the real path and leaving its alias open
     * would achieve nothing.
     */
    public function test_an_address_opened_onto_a_private_page_is_disallowed_too(): void
    {
        $this->inProduction();

        CustomRoute::create([
            'locale'       => 'en',
            'slug'         => 'login',
            'target_route' => 'login',
            'type'         => 'render',
            'is_active'    => true,
        ]);
        app(CustomRouteService::class)->clearCache();

        $this->assertStringContainsString('Disallow: /en/login', $this->body());
    }

    public function test_an_address_opened_onto_a_public_page_stays_crawlable(): void
    {
        $this->inProduction();

        // Seeded by the migration that gave the built-in pages English
        // addresses; it must not end up in the disallow list.
        $this->assertStringNotContainsString('Disallow: /en/contact', $this->body());
    }

    public function test_addresses_from_before_the_language_prefix_are_disallowed(): void
    {
        $this->inProduction();

        $body = $this->body();

        $this->assertStringContainsString("\nDisallow: /giris", $body);
        $this->assertStringContainsString("\nDisallow: /hesabim", $body);
    }

    /**
     * Two endpoints that carry no language and produce no content: the
     * language switcher, and the unsubscribe link — a state-changing GET that
     * needs no login, which is the last thing a crawler should follow.
     */
    public function test_the_language_switcher_and_unsubscribe_link_are_disallowed(): void
    {
        $this->inProduction();

        $body = $this->body();

        $this->assertStringContainsString('Disallow: /dil/', $body);
        $this->assertStringContainsString('Disallow: /bulten/cikis/', $body);
    }

    /**
     * Robots rules match on prefix, so a line already covered by a shorter one
     * is noise.
     */
    public function test_a_path_already_covered_by_a_shorter_one_is_not_repeated(): void
    {
        $this->inProduction();

        $this->assertStringNotContainsString('Disallow: /tr/hesabim/profil', $this->body());
    }

    /**
     * The paths are read off the route definitions, not typed out. Asserting
     * against the route's own URI means this test follows a renamed path
     * instead of pinning the old spelling — and fails if the list ever goes
     * back to being hand-written.
     */
    public function test_the_paths_are_read_from_the_routes_themselves(): void
    {
        $this->inProduction();

        $uri = \Illuminate\Support\Facades\Route::getRoutes()->getByName('login')?->uri();

        $this->assertNotNull($uri);
        $this->assertStringStartsWith('{locale}/', $uri);

        $this->assertStringContainsString(
            'Disallow: /' . str_replace('{locale}', 'tr', $uri),
            $this->body(),
        );
    }
}
