<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CustomRouteType;
use App\Models\CustomRoute;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomRouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Panelden yönetilen adresler.
 *
 * Rotalar koda gömülüydü: yeni bir adres açmak ya da bir sayfaya ikinci bir
 * adres vermek geliştirici işiydi. Çok dilli sitede iki kat zor — "iletisim"
 * ve "contact" aynı sayfaya bakmalı ama her biri kendi dilinde.
 *
 * Tablo iki yönde de kullanılıyor ve ikisi birden olmadan sistem yarım
 * kalırdı: /en/contact açılır ama menüdeki bağlantı hâlâ /en/iletisim derdi.
 */
final class CustomRouteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
        $this->seedAuthorization();

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

        $this->service()->clearCache();
    }

    private function service(): CustomRouteService
    {
        return app(CustomRouteService::class);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function route(array $overrides = []): CustomRoute
    {
        $route = CustomRoute::create(array_merge([
            'locale'       => 'en',
            'slug'         => 'contact',
            'target_route' => 'contact',
            'type'         => CustomRouteType::Render,
            'is_active'    => true,
        ], $overrides));

        $this->service()->clearCache();

        return $route;
    }

    // ── Gelen istek ──

    public function test_an_address_opened_in_the_panel_answers(): void
    {
        $this->route();

        $this->get('/en/contact')->assertOk()->assertSee('Contact', false);
    }

    /** Adres çubuğu değişmemeli: içerik bu adreste basılıyor. */
    public function test_the_address_stays_in_the_bar(): void
    {
        $this->route();

        $this->get('/en/contact')->assertOk()->assertDontSee('Redirecting');
    }

    public function test_the_original_address_keeps_working(): void
    {
        $this->route();

        $this->get('/en/iletisim')->assertOk();
        $this->get('/tr/iletisim')->assertOk();
    }

    /** Dile özgü kayıt yalnız o dilde geçerli. */
    public function test_an_address_opened_for_one_language_is_not_open_in_another(): void
    {
        $this->route(['locale' => 'en', 'slug' => 'contact']);

        $this->get('/en/contact')->assertOk();
        $this->get('/tr/contact')->assertNotFound();
    }

    public function test_an_address_without_a_language_answers_everywhere(): void
    {
        $this->route(['locale' => null, 'slug' => 'bize-ulas']);

        $this->get('/tr/bize-ulas')->assertOk();
        $this->get('/en/bize-ulas')->assertOk();
    }

    /** Dile özgü kayıt, tüm dilleri kapsayandan önce gelmeli. */
    public function test_a_language_specific_address_wins_over_the_general_one(): void
    {
        $this->route(['locale' => null, 'slug' => 'yardim', 'target_route' => 'contact']);
        $this->route(['locale' => 'en', 'slug' => 'yardim', 'target_route' => 'faq']);

        $this->assertSame('faq', $this->service()->resolve('en', 'yardim')['route']);
        $this->assertSame('contact', $this->service()->resolve('tr', 'yardim')['route']);
    }

    public function test_a_passive_address_does_not_answer(): void
    {
        $this->route(['slug' => 'kapali', 'is_active' => false]);

        $this->get('/en/kapali')->assertNotFound();
    }

    public function test_a_deleted_address_does_not_answer(): void
    {
        $route = $this->route(['slug' => 'silinecek']);

        $this->service()->delete($route);

        $this->get('/en/silinecek')->assertNotFound();
    }

    /** Eşleşme yoksa istek normal akışına devam etmeli. */
    public function test_an_unmatched_address_is_left_to_the_normal_routes(): void
    {
        $this->route();

        $this->get('/tr/galeri')->assertOk();
        $this->get('/tr/boyle-bir-sey-yok')->assertNotFound();
    }

    public function test_a_target_that_needs_parameters_works(): void
    {
        \App\Models\Page::create([
            'locale' => 'tr', 'title' => 'Hakkımızda', 'slug' => 'hakkimizda',
            'content' => 'Metin', 'status' => 'published',
        ]);

        $this->route([
            'locale' => 'tr', 'slug' => 'kurumsal',
            'target_route' => 'pages.show', 'target_params' => ['slug' => 'hakkimizda'],
        ]);

        $this->get('/tr/kurumsal')->assertOk()->assertSee('Hakkımızda', false);
    }

    // ── Yönlendirme ──

    public function test_a_permanent_redirect_moves_the_visitor(): void
    {
        $this->route(['locale' => 'tr', 'slug' => 'eski-iletisim', 'type' => CustomRouteType::MovedPermanently]);

        $this->get('/tr/eski-iletisim')
            ->assertStatus(301)
            ->assertRedirect(route('contact', ['locale' => 'tr']));
    }

    public function test_a_temporary_redirect_uses_its_own_code(): void
    {
        $this->route(['locale' => 'tr', 'slug' => 'gecici', 'type' => CustomRouteType::Found]);

        $this->get('/tr/gecici')->assertStatus(302);
    }

    // ── Giden bağlantı ──

    /**
     * Menüdeki bağlantı açılmış adresi kullanmalı.
     *
     * Bu olmadan sistem yarım kalırdı: /en/contact açılır ama menü hâlâ
     * /en/iletisim derdi — kullanıcının bildirdiği sorun tam da buydu.
     */
    public function test_the_menu_link_uses_the_address_that_was_opened(): void
    {
        $this->route();
        app(\App\Services\MenuService::class)->clearAllCaches();

        $en = (string) $this->get('/en')->assertOk()->getContent();

        $this->assertStringContainsString('/en/contact', $en);
    }

    public function test_a_redirect_record_is_not_used_for_writing_links(): void
    {
        // Yönlendirme, bir bağlantının yazılacağı yer değil: eski bir adresin
        // yenisine taşınması.
        $this->route(['locale' => 'en', 'slug' => 'eski', 'type' => CustomRouteType::MovedPermanently]);

        $this->assertNull($this->service()->slugFor('contact', 'en'));
    }

    /**
     * Aynı sayfaya iki yoldan gidilebiliyor; ikisi de kendini gösteren bir
     * canonical basarsa arama motoru hangisini alacağını kendi seçer.
     */
    public function test_both_addresses_point_at_the_same_canonical(): void
    {
        $this->route();

        foreach (['/en/contact', '/en/iletisim'] as $adres) {
            $html = (string) $this->get($adres)->assertOk()->getContent();

            $this->assertStringContainsString('rel="canonical" href="' . url('en/contact') . '"', $html, "{$adres} yanlış canonical");
        }
    }

    // ── Önbellek ──

    public function test_the_map_is_read_once_not_on_every_lookup(): void
    {
        $this->route();

        $sorgu = 0;

        DB::listen(function ($query) use (&$sorgu): void {
            if (str_contains($query->sql, 'custom_routes')) {
                $sorgu++;
            }
        });

        $service = $this->service();

        for ($i = 0; $i < 20; $i++) {
            $service->resolve('en', 'contact');
            $service->slugFor('contact', 'en');
        }

        $this->assertLessThanOrEqual(1, $sorgu, "Harita her aramada yeniden okunuyor ({$sorgu} sorgu)");
    }

    public function test_saving_from_the_panel_refreshes_the_map(): void
    {
        $this->service()->map();

        $this->actingAs($this->admin)->post(route('admin.custom-routes.store'), [
            'slug' => 'yeni-adres', 'locale' => 'tr', 'target_route' => 'contact',
            'type' => 'render', 'is_active' => 1,
        ])->assertRedirect(route('admin.custom-routes.index'));

        $this->get('/tr/yeni-adres')->assertOk();
    }

    // ── Panel ──

    public function test_the_panel_lists_and_creates(): void
    {
        $this->route(['slug' => 'listede']);

        $this->actingAs($this->admin)
            ->get(route('admin.custom-routes.index'))
            ->assertOk()
            ->assertSee('listede', false);
    }

    /** Hedef listeden seçiliyor: uydurma bir rota kaydedilememeli. */
    public function test_a_target_outside_the_list_is_refused(): void
    {
        $this->actingAs($this->admin)->post(route('admin.custom-routes.store'), [
            'slug' => 'kotu', 'locale' => 'tr', 'target_route' => 'admin.dashboard',
            'type' => 'render', 'is_active' => 1,
        ])->assertSessionHasErrors('target_route');
    }

    /** Eksik parametreyle kaydedilen adres çalışmaz; hata kaydederken çıkmalı. */
    public function test_a_target_that_needs_a_parameter_cannot_be_saved_without_it(): void
    {
        $this->actingAs($this->admin)->post(route('admin.custom-routes.store'), [
            'slug' => 'parametresiz', 'locale' => 'tr', 'target_route' => 'pages.show',
            'type' => 'render', 'is_active' => 1,
        ])->assertSessionHasErrors('target_params.slug');
    }

    public function test_the_same_address_cannot_be_opened_twice_in_one_language(): void
    {
        $this->route(['locale' => 'tr', 'slug' => 'tekrar']);

        $this->actingAs($this->admin)->post(route('admin.custom-routes.store'), [
            'slug' => 'tekrar', 'locale' => 'tr', 'target_route' => 'faq',
            'type' => 'render', 'is_active' => 1,
        ])->assertSessionHasErrors('slug');
    }

    /**
     * Yönetici alışkanlıkla dil ön eki yazabilir; kayıt onsuz durmalı, yoksa
     * adres "/tr/tr/bize-ulas" olurdu.
     */
    public function test_a_language_prefix_typed_by_habit_is_stripped(): void
    {
        $this->actingAs($this->admin)->post(route('admin.custom-routes.store'), [
            'slug' => '/tr/bize-ulas', 'locale' => 'tr', 'target_route' => 'contact',
            'type' => 'render', 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('custom_routes', ['slug' => 'bize-ulas']);
        $this->get('/tr/bize-ulas')->assertOk();
    }

    public function test_the_delete_confirmation_carries_no_markup(): void
    {
        $html = (string) $this->actingAs($this->admin)
            ->get(route('admin.custom-routes.index'))->assertOk()->getContent();

        $this->assertStringContainsString('detailTitle: slug', $html);
        $this->assertStringNotContainsString("'<strong>' + slug", $html);
    }

    public function test_a_visitor_cannot_reach_the_panel(): void
    {
        $this->get(route('admin.custom-routes.index'))->assertRedirect();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.custom-routes.index'))
            ->assertForbidden();
    }
}
