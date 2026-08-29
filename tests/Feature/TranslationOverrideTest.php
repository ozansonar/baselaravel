<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionKey;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Translation;
use App\Models\User;
use App\Services\LanguageService;
use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Interface texts, editable from the panel.
 *
 * The lang/ files stay the shipped defaults; the table holds only what someone
 * changed. That split is what makes a deploy safe — a git pull would otherwise
 * overwrite every edit — and what makes "reset to default" mean something.
 */
class TranslationOverrideTest extends TestCase
{
    use RefreshDatabase;

    private const GROUP = 'site';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();
        app(TranslationService::class)->flush();
    }

    private function service(): TranslationService
    {
        return app(TranslationService::class);
    }

    /**
     * @param array<int, PermissionKey> $permissions
     */
    private function userWith(array $permissions): User
    {
        $role = Role::create(['name' => 'Test', 'slug' => 'test-' . uniqid()]);

        $ids = [];
        foreach ($permissions as $key) {
            $ids[] = Permission::firstOrCreate(
                ['key' => $key->value],
                ['name' => $key->label(), 'group' => $key->group()],
            )->id;
        }

        $role->permissions()->syncWithoutDetaching($ids);

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function editor(): User
    {
        return $this->userWith([PermissionKey::TranslationsView, PermissionKey::TranslationsManage]);
    }

    // ── Çözümleme ──

    public function test_an_untouched_string_comes_from_the_file(): void
    {
        app()->setLocale('tr');

        $this->assertSame('Anasayfa', __('site.nav.home'));
        $this->assertSame(0, Translation::count());
    }

    public function test_an_override_replaces_the_file_value(): void
    {
        app()->setLocale('tr');
        $this->service()->save('tr', self::GROUP, ['nav.home' => 'Ana Sayfam']);

        $this->assertSame('Ana Sayfam', __('site.nav.home'));
    }

    /**
     * The file is still the source of every other key — overriding one string
     * must not hide the rest of the group.
     */
    public function test_the_other_keys_of_the_group_still_resolve(): void
    {
        app()->setLocale('tr');
        $this->service()->save('tr', self::GROUP, ['nav.home' => 'Ana Sayfam']);

        $this->assertSame('İletişim', __('site.nav.contact'));
        $this->assertSame('Tümü', __('site.blog.all'));
        $this->assertSame('Galeri', __('site.gallery.title'));
    }

    public function test_an_override_is_scoped_to_its_language(): void
    {
        $this->service()->save('tr', self::GROUP, ['nav.home' => 'Ana Sayfam']);

        $this->assertSame('Ana Sayfam', __('site.nav.home', [], 'tr'));
        $this->assertSame('Home', __('site.nav.home', [], 'en'));
    }

    public function test_a_placeholder_still_works_after_an_override(): void
    {
        app()->setLocale('tr');
        $this->service()->save('tr', self::GROUP, ['account.welcome' => 'Selam :name!']);

        $this->assertSame('Selam Ahmet!', __('site.account.welcome', ['name' => 'Ahmet']));
    }

    // ── Kaydetme kuralları ──

    /**
     * Storing a value identical to the file would freeze it: a later change to
     * the shipped default would never reach the site.
     */
    public function test_a_value_equal_to_the_default_is_not_stored(): void
    {
        $this->service()->save('tr', self::GROUP, ['nav.home' => 'Anasayfa']);

        $this->assertSame(0, Translation::count());
    }

    public function test_an_empty_value_falls_back_to_the_default(): void
    {
        $this->service()->save('tr', self::GROUP, ['nav.home' => 'Ana Sayfam']);
        $this->assertSame(1, Translation::count());

        $this->service()->save('tr', self::GROUP, ['nav.home' => '']);

        $this->assertSame(0, Translation::count());
        $this->assertSame('Anasayfa', __('site.nav.home', [], 'tr'));
    }

    public function test_only_keys_the_group_defines_can_be_written(): void
    {
        $this->service()->save('tr', self::GROUP, [
            'nav.home'            => 'Ana Sayfam',
            'uydurma.anahtar'     => 'Olmamalı',
            'nav.uydurma'         => 'Bu da olmamalı',
        ]);

        $this->assertSame(1, Translation::count());
        $this->assertDatabaseMissing('translations', ['key' => 'uydurma.anahtar']);
    }

    public function test_saving_reports_only_real_changes(): void
    {
        $service = $this->service();
        $defaults = $service->fileLines('tr', self::GROUP);

        // Submitting the whole form untouched changes nothing.
        $result = $service->save('tr', self::GROUP, $defaults);
        $this->assertSame(['saved' => 0, 'reset' => 0], $result);

        // array_merge, not +: the + operator keeps the left-hand value.
        $result = $service->save('tr', self::GROUP, array_merge($defaults, ['nav.home' => 'Ana Sayfam']));
        $this->assertSame(1, $result['saved']);
        $this->assertSame(0, $result['reset']);

        // Putting it back counts as one reset, not two hundred.
        $result = $service->save('tr', self::GROUP, $defaults);
        $this->assertSame(0, $result['saved']);
        $this->assertSame(1, $result['reset']);
    }

    public function test_resetting_a_language_drops_only_that_language(): void
    {
        $service = $this->service();
        $service->save('tr', self::GROUP, ['nav.home' => 'Ana Sayfam']);
        $service->save('en', self::GROUP, ['nav.home' => 'Homepage']);

        $service->resetGroup('tr', self::GROUP);

        $this->assertSame('Anasayfa', __('site.nav.home', [], 'tr'));
        $this->assertSame('Homepage', __('site.nav.home', [], 'en'));
    }

    // ── Performans ──

    /**
     * The whole point of caching the overrides: rendering a page must not cost
     * a query per translated string, or even a query at all once warm.
     */
    public function test_a_warm_page_render_makes_no_translation_query(): void
    {
        $this->service()->save('tr', self::GROUP, ['nav.home' => 'Ana Sayfam']);

        // Warm the cache the way the first request would.
        $this->get('/tr')->assertOk();

        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'translations')) {
                $queries++;
            }
        });

        $this->get('/tr')->assertOk();

        $this->assertSame(0, $queries, 'Çeviri tablosu her istekte sorgulanıyor');
    }

    public function test_reading_many_strings_costs_one_lookup(): void
    {
        $this->service()->save('tr', self::GROUP, ['nav.home' => 'Ana Sayfam']);
        app(TranslationService::class)->flush();

        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'translations')) {
                $queries++;
            }
        });

        app()->setLocale('tr');
        foreach (['nav.home', 'nav.contact', 'blog.all', 'gallery.title', 'faq.title'] as $key) {
            __('site.' . $key);
        }

        $this->assertLessThanOrEqual(1, $queries, 'Her metin için ayrı sorgu atılıyor');
    }

    // ── Panel ──

    public function test_the_screen_lists_the_strings(): void
    {
        $this->actingAs($this->editor())
            ->get(route('admin.translations.index'))
            ->assertOk()
            ->assertSee('Dil Yazıları')
            ->assertSee('nav.home')
            ->assertSee('Anasayfa');
    }

    public function test_the_screen_can_be_switched_to_another_language(): void
    {
        $this->actingAs($this->editor())
            ->get(route('admin.translations.index', ['locale' => 'en']))
            ->assertOk()
            ->assertSee('Home');
    }

    /**
     * Başka bir dili çevirirken kaynak metin ekranda durmalı; aksi hâlde aslını
     * görmek için ikinci bir sekme gerekiyor.
     */
    public function test_another_language_shows_the_source_text(): void
    {
        $html = $this->actingAs($this->editor())
            ->get(route('admin.translations.index', ['locale' => 'en']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('trn-reference', $html);
        // Varsayılan dildeki karşılık, İngilizce alanın yanında görünüyor.
        $this->assertStringContainsString('Anasayfa', $html);
    }

    /**
     * Varsayılan dilde kaynak metin alanın kendisiyle aynı olurdu; tekrar
     * etmenin anlamı yok.
     */
    public function test_the_default_language_does_not_repeat_the_source_text(): void
    {
        $html = $this->actingAs($this->editor())
            ->get(route('admin.translations.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('trn-reference', $html);
    }

    /**
     * Sayfa yüzlerce alan taşıyor: kaydetme yolu kaydırma boyunca yanında
     * olmalı, ama yalnızca düzenleme yetkisi olana.
     */
    public function test_the_save_bar_is_only_rendered_for_editors(): void
    {
        $this->assertStringContainsString(
            'translationSaveBar',
            $this->actingAs($this->editor())->get(route('admin.translations.index'))->getContent(),
        );

        $this->assertStringNotContainsString(
            'translationSaveBar',
            $this->actingAs($this->userWith([PermissionKey::TranslationsView]))
                ->get(route('admin.translations.index'))
                ->getContent(),
        );
    }

    /**
     * Çevrilmemiş metinler süzülebilmeli: yeni bir dil eklendiğinde asıl iş
     * onları bulup doldurmak.
     */
    public function test_untranslated_strings_are_marked_for_filtering(): void
    {
        $html = $this->actingAs($this->editor())
            ->get(route('admin.translations.index', ['locale' => 'en']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('onlyMissing', $html);
        $this->assertStringContainsString('data-missing=', $html);
    }

    /**
     * The key list comes from the file, so a string added in code appears here
     * without anyone registering it.
     */
    public function test_every_key_in_the_file_is_offered(): void
    {
        $keys = $this->service()->keysFrom(self::GROUP);
        $html = $this->actingAs($this->editor())->get(route('admin.translations.index'))->getContent();

        foreach (['nav.home', 'blog.all', 'newsletter.subscribe', 'errors.404_title'] as $key) {
            $this->assertArrayHasKey($key, $keys);
            $this->assertStringContainsString('values[' . $key . ']', $html);
        }
    }

    public function test_saving_from_the_screen_changes_the_site(): void
    {
        $this->actingAs($this->editor())
            ->put(route('admin.translations.update'), [
                'locale' => 'tr',
                'values' => ['nav.home' => 'Ana Sayfam'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Ana Sayfam', __('site.nav.home', [], 'tr'));

        // The test client sends Accept-Language: en by default, so the locale
        // has to be asked for explicitly.
        //
        // Sayfa anasayfadan galeriye taşındı: nav.home artık anasayfada değil,
        // iç sayfaların kırıntı yolunda basılıyor. Alt bilgi bağlantıları menü
        // modülüne geçince anasayfa bu anahtarı kullanmayı bıraktı.
        $html = $this->get('/tr/galeri')->getContent();
        $this->assertStringContainsString('Ana Sayfam', $html);
    }

    public function test_the_screen_can_reset_a_language(): void
    {
        $this->service()->save('tr', self::GROUP, ['nav.home' => 'Ana Sayfam']);

        $this->actingAs($this->editor())
            ->post(route('admin.translations.reset'), ['locale' => 'tr'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, Translation::count());
    }

    public function test_an_unknown_language_is_refused(): void
    {
        $this->actingAs($this->editor())
            ->put(route('admin.translations.update'), ['locale' => 'zz', 'values' => ['nav.home' => 'X']])
            ->assertNotFound();
    }

    // ── Yetkiler ──

    public function test_viewing_needs_the_view_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.translations.index'))
            ->assertForbidden();
    }

    public function test_editing_needs_the_manage_permission(): void
    {
        $viewer = $this->userWith([PermissionKey::TranslationsView]);

        $this->actingAs($viewer)->get(route('admin.translations.index'))->assertOk();
        $this->actingAs($viewer)
            ->put(route('admin.translations.update'), ['locale' => 'tr', 'values' => ['nav.home' => 'X']])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->post(route('admin.translations.reset'), ['locale' => 'tr'])
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('admin.translations.index'))->assertRedirect();
    }
}
