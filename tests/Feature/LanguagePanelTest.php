<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionKey;
use App\Models\Language;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The panel screen for languages.
 *
 * LanguageManagementTest covers the rules at the service level; this covers the
 * screen an admin actually uses — and the rules that must survive being driven
 * over HTTP, above all "exactly one default".
 */
class LanguagePanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();
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

    private function manager(): User
    {
        return $this->userWith([PermissionKey::LanguagesView, PermissionKey::LanguagesManage]);
    }

    // ── Listeleme ──

    public function test_the_screen_lists_every_language(): void
    {
        $this->actingAs($this->manager())
            ->get(route('admin.languages.index'))
            ->assertOk()
            ->assertSee('Türkçe')
            ->assertSee('English')
            ->assertSee('Deutsch')
            ->assertSee('Varsayılan');
    }

    /**
     * A language with no lang/{code} directory still works — the interface
     * falls back — but the screen has to say so, otherwise it looks translated
     * when it is not.
     */
    public function test_the_screen_flags_languages_without_interface_files(): void
    {
        $html = $this->actingAs($this->manager())
            ->get(route('admin.languages.index'))
            ->getContent();

        // tr and en ship with lang/ directories; de does not.
        $this->assertStringContainsString('lang/de/ klasörü yok', $html);
    }

    public function test_the_list_can_be_searched(): void
    {
        $this->actingAs($this->manager())
            ->get(route('admin.languages.index', ['search' => 'deu']))
            ->assertOk()
            ->assertSee('Deutsch')
            ->assertDontSee('Italiano');
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        $this->actingAs($this->manager())
            ->get(route('admin.languages.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Deutsch')
            ->assertDontSee('>Türkçe<', false);
    }

    /**
     * "Has interface files" is a filesystem fact, not a column, so the filter
     * has to work off the codes found on disk.
     */
    public function test_the_list_can_be_filtered_by_interface_files(): void
    {
        $withFiles = $this->actingAs($this->manager())
            ->get(route('admin.languages.index', ['files' => 'yes']))
            ->getContent();

        $withoutFiles = $this->actingAs($this->manager())
            ->get(route('admin.languages.index', ['files' => 'no']))
            ->getContent();

        // tr and en ship with lang/ directories; de, fr and it do not.
        $this->assertStringContainsString('Türkçe', $withFiles);
        $this->assertStringNotContainsString('Italiano', $withFiles);

        $this->assertStringContainsString('Italiano', $withoutFiles);
        $this->assertStringNotContainsString('>English<', $withoutFiles);
    }

    /**
     * The counters describe the whole list, not the filtered slice — otherwise
     * filtering would look like languages had disappeared.
     */
    public function test_the_counters_ignore_the_filter(): void
    {
        $this->actingAs($this->manager())
            ->get(route('admin.languages.index', ['search' => 'deu']))
            ->assertOk()
            ->assertSee('data-count="5"', false);
    }

    /**
     * İçerik sayısı da sütun değil; dokuz tablodan toplanıyor. Süzgeç o
     * toplamın sıfırdan büyük olduğu diller üzerinden çalışmalı.
     */
    public function test_the_list_can_be_filtered_by_content(): void
    {
        \App\Models\Page::factory()->create(['locale' => 'de']);

        $withContent = $this->actingAs($this->manager())
            ->get(route('admin.languages.index', ['content' => 'yes']))
            ->getContent();

        $withoutContent = $this->actingAs($this->manager())
            ->get(route('admin.languages.index', ['content' => 'no']))
            ->getContent();

        $this->assertStringContainsString('Deutsch', $withContent);
        $this->assertStringNotContainsString('Italiano', $withContent);
        $this->assertStringContainsString('Italiano', $withoutContent);
    }

    public function test_the_list_is_paginated(): void
    {
        // Seeder beş dil kuruyor; sayfa başına iki kayıtla üç sayfa eder.
        $response = $this->actingAs($this->manager())
            ->get(route('admin.languages.index', ['per_page' => 10]))
            ->assertOk();

        $languages = $response->viewData('languages');

        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $languages);
        $this->assertSame(10, $languages->perPage());
        $this->assertSame(5, $languages->total());
    }

    /**
     * Sayfa başına değer istekten geliyor; izin verilenler dışında bir sayı
     * gönderilirse varsayılana düşmeli.
     */
    public function test_an_unknown_page_size_falls_back_to_the_default(): void
    {
        $response = $this->actingAs($this->manager())
            ->get(route('admin.languages.index', ['per_page' => 999]))
            ->assertOk();

        $this->assertSame(10, $response->viewData('languages')->perPage());
    }

    public function test_the_list_can_be_sorted(): void
    {
        $codes = fn (string $sort): array => $this->actingAs($this->manager())
            ->get(route('admin.languages.index', ['sort' => $sort]))
            ->viewData('languages')
            ->pluck('code')
            ->all();

        // Ada göre: Almanca, Fransızca, İngilizce, İtalyanca, Türkçe.
        $this->assertSame('de', $codes('name')[0]);
        $this->assertSame(['de', 'en', 'fr', 'it', 'tr'], $codes('code'));

        // Varsayılan sıralamada varsayılan dil hep başta.
        $this->assertSame('tr', $codes('order')[0]);
    }

    /**
     * Uydurulmuş bir sıralama sütun adı olarak sorguya girmemeli.
     */
    public function test_an_unknown_sort_is_ignored(): void
    {
        $this->actingAs($this->manager())
            ->get(route('admin.languages.index', ['sort' => 'code); drop table languages;--']))
            ->assertOk();

        $this->assertSame(5, \App\Models\Language::count());
    }

    /**
     * Sayfa değiştirirken açık süzgeç korunmalı, yoksa kullanıcı ikinci sayfada
     * kendini süzülmemiş listede bulur.
     */
    public function test_the_pagination_links_carry_the_open_filters(): void
    {
        // Seeder'ın beş dili tek sayfaya sığıyor; ikinci sayfa için liste
        // kalabalıklaştırılıyor.
        foreach (range(1, 12) as $i) {
            \App\Models\Language::create([
                'code'        => 'x' . $i,
                'name'        => 'Test Dili ' . $i,
                'native_name' => 'Test ' . $i,
                'is_active'   => false,
                'is_default'  => false,
                'sort_order'  => 100 + $i,
            ]);
        }

        $languages = $this->actingAs($this->manager())
            ->get(route('admin.languages.index', ['per_page' => 10, 'status' => 'inactive']))
            ->assertOk()
            ->viewData('languages');

        $this->assertTrue($languages->hasPages(), 'İki sayfa oluşmalı');
        $this->assertStringContainsString('status=inactive', $languages->nextPageUrl() ?? '');
        $this->assertStringContainsString('per_page=10', $languages->nextPageUrl() ?? '');
    }

    // ── Sayfalar ──

    public function test_the_create_page_opens(): void
    {
        $this->actingAs($this->manager())
            ->get(route('admin.languages.create'))
            ->assertOk()
            ->assertSee('Yeni Dil')
            ->assertSee('name="code"', false);
    }

    /**
     * A language already defined must not be offered as a quick fill.
     */
    /**
     * Hazır liste elle kod aramayı gereksiz kılmalı: kapsam dar kalırsa
     * kullanıcı yine ISO kodu aramaya gider.
     */
    public function test_the_create_page_offers_a_wide_set_of_ready_languages(): void
    {
        $suggestions = $this->actingAs($this->manager())
            ->get(route('admin.languages.create'))
            ->assertOk()
            ->viewData('suggestions');

        // Seeder'ın beş dili düşüldükten sonra bile geriye yirmi beşten fazla kalmalı.
        $this->assertGreaterThanOrEqual(25, count($suggestions));

        $codes = array_column($suggestions, 'code');
        $this->assertSame($codes, array_unique($codes), 'Aynı kod iki kez önerilmemeli');

        // Hepsi ISO 639-1: form iki harfli koddan başkasını kabul etmiyor.
        foreach ($suggestions as $row) {
            $this->assertMatchesRegularExpression('/^[a-z]{2}$/', $row['code']);
            $this->assertNotSame('', $row['name']);
            $this->assertNotSame('', $row['native']);
            $this->assertNotSame('', $row['flag']);
        }
    }

    /**
     * Hazır listeden gelen değerler formun kendi kurallarından geçmeli; aksi
     * hâlde tek tıkla dolan form kaydedilemez.
     */
    public function test_a_ready_language_can_be_saved_as_is(): void
    {
        $suggestion = collect(
            $this->actingAs($this->manager())->get(route('admin.languages.create'))->viewData('suggestions')
        )->firstWhere('code', 'ja');

        $this->assertNotNull($suggestion, 'Japonca hazır listede olmalı');

        $this->actingAs($this->manager())
            ->post(route('admin.languages.store'), [
                'code'        => $suggestion['code'],
                'name'        => $suggestion['name'],
                'native_name' => $suggestion['native'],
                'flag'        => $suggestion['flag'],
                'sort_order'  => 0,
                'is_active'   => '1',
            ])
            ->assertRedirect(route('admin.languages.index'))
            ->assertSessionHasNoErrors();

        $language = \App\Models\Language::where('code', 'ja')->firstOrFail();
        $this->assertSame('日本語', $language->native_name);
        $this->assertSame('🇯🇵', $language->flag);
    }

    public function test_the_create_page_only_suggests_languages_not_yet_added(): void
    {
        $html = $this->actingAs($this->manager())->get(route('admin.languages.create'))->getContent();

        $this->assertStringContainsString('data-code="es"', $html);
        $this->assertStringNotContainsString('data-code="tr"', $html);
    }

    public function test_the_edit_page_opens_with_the_current_values(): void
    {
        $german = Language::where('code', 'de')->firstOrFail();

        $this->actingAs($this->manager())
            ->get(route('admin.languages.edit', $german))
            ->assertOk()
            ->assertSee('Deutsch')
            ->assertSee('value="de"', false);
    }

    public function test_editing_needs_the_manage_permission(): void
    {
        $viewer = $this->userWith([PermissionKey::LanguagesView]);

        $this->actingAs($viewer)->get(route('admin.languages.create'))->assertForbidden();
        $this->actingAs($viewer)
            ->get(route('admin.languages.edit', Language::where('code', 'de')->firstOrFail()))
            ->assertForbidden();
    }

    // ── Ekleme ──

    public function test_a_language_can_be_added(): void
    {
        $this->actingAs($this->manager())
            ->post(route('admin.languages.store'), [
                'code'        => 'es',
                'name'        => 'İspanyolca',
                'native_name' => 'Español',
                'flag'        => '🇪🇸',
                'sort_order'  => 5,
                'is_active'   => '1',
            ])
            ->assertRedirect(route('admin.languages.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('languages', [
            'code'       => 'es',
            'native_name' => 'Español',
            'is_active'  => true,
            'is_default' => false,
        ]);
    }

    public function test_adding_a_language_warns_when_its_interface_files_are_missing(): void
    {
        $this->actingAs($this->manager())
            ->post(route('admin.languages.store'), ['code' => 'es', 'name' => 'İspanyolca'])
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'lang/es/'));
    }

    public function test_a_duplicate_code_is_rejected(): void
    {
        $this->actingAs($this->manager())
            ->post(route('admin.languages.store'), ['code' => 'tr', 'name' => 'Türkçe 2'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Language::where('code', 'tr')->count());
    }

    public function test_the_code_must_be_two_letters(): void
    {
        foreach (['türkçe', 'e', 'eng', 'e1'] as $bad) {
            $this->actingAs($this->manager())
                ->post(route('admin.languages.store'), ['code' => $bad, 'name' => 'Test'])
                ->assertSessionHasErrors('code');
        }
    }

    public function test_an_uppercase_code_is_accepted_and_stored_lowercase(): void
    {
        $this->actingAs($this->manager())
            ->post(route('admin.languages.store'), ['code' => 'ES', 'name' => 'İspanyolca'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('languages', ['code' => 'es']);
    }

    // ── Güncelleme ──

    public function test_a_language_can_be_updated(): void
    {
        $german = Language::where('code', 'de')->firstOrFail();

        $this->actingAs($this->manager())
            ->put(route('admin.languages.update', $german), [
                'code'        => 'de',
                'name'        => 'Almanca',
                'native_name' => 'Deutsch',
                'flag'        => '🇩🇪',
                'sort_order'  => 9,
                'is_active'   => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $german->refresh();
        $this->assertTrue($german->is_active);
        $this->assertSame(9, $german->sort_order);
    }

    public function test_a_language_can_be_switched_off(): void
    {
        $english = Language::where('code', 'en')->firstOrFail();

        $this->actingAs($this->manager())
            ->put(route('admin.languages.update', $english), [
                'code' => 'en',
                'name' => 'İngilizce',
                // is_active omitted → off
            ]);

        $this->assertFalse($english->refresh()->is_active);
    }

    /**
     * Switching off the default would leave the site with no fallback, so the
     * service refuses it even when the request asks.
     */
    public function test_the_default_language_stays_on_however_it_is_posted(): void
    {
        $turkish = Language::where('code', 'tr')->firstOrFail();
        $this->assertTrue($turkish->is_default);

        $this->actingAs($this->manager())
            ->put(route('admin.languages.update', $turkish), [
                'code' => 'tr',
                'name' => 'Türkçe',
                // is_active omitted → would switch it off
            ]);

        $this->assertTrue($turkish->refresh()->is_active, 'Varsayılan dil pasife alındı');
    }

    // ── Varsayılan ──

    public function test_making_a_language_default_clears_the_previous_one(): void
    {
        $english = Language::where('code', 'en')->firstOrFail();

        $this->actingAs($this->manager())
            ->post(route('admin.languages.default', $english))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($english->refresh()->is_default);
        $this->assertFalse(Language::where('code', 'tr')->firstOrFail()->is_default);
    }

    /**
     * The rule the whole feature hangs on.
     */
    public function test_exactly_one_language_is_default_after_any_change(): void
    {
        $manager = $this->manager();

        foreach (['en', 'de', 'fr', 'tr'] as $code) {
            $this->actingAs($manager)
                ->post(route('admin.languages.default', Language::where('code', $code)->firstOrFail()));

            $this->assertSame(1, Language::where('is_default', true)->count(), "{$code} sonrası varsayılan sayısı 1 değil");
        }

        $this->assertSame('tr', Language::where('is_default', true)->firstOrFail()->code);
    }

    public function test_making_an_inactive_language_default_also_publishes_it(): void
    {
        $french = Language::where('code', 'fr')->firstOrFail();
        $this->assertFalse($french->is_active);

        $this->actingAs($this->manager())->post(route('admin.languages.default', $french));

        $french->refresh();
        $this->assertTrue($french->is_default);
        $this->assertTrue($french->is_active, 'Varsayılan yapılan dil yayına alınmadı');
    }

    // ── Silme ──

    public function test_a_language_can_be_deleted(): void
    {
        $italian = Language::where('code', 'it')->firstOrFail();

        $this->actingAs($this->manager())
            ->delete(route('admin.languages.destroy', $italian))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('languages', ['id' => $italian->id]);
    }

    public function test_the_default_language_cannot_be_deleted(): void
    {
        $turkish = Language::where('code', 'tr')->firstOrFail();

        $this->actingAs($this->manager())
            ->delete(route('admin.languages.destroy', $turkish))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('languages', ['id' => $turkish->id]);
    }

    public function test_the_last_language_cannot_be_deleted(): void
    {
        $manager = $this->manager();
        $turkish = Language::where('code', 'tr')->firstOrFail();

        foreach (Language::where('id', '!=', $turkish->id)->get() as $language) {
            $this->actingAs($manager)->delete(route('admin.languages.destroy', $language));
        }

        $this->assertSame(1, Language::count());

        $this->actingAs($manager)
            ->delete(route('admin.languages.destroy', $turkish))
            ->assertSessionHas('error');

        $this->assertSame(1, Language::count());
    }

    // ── Yetkiler ──

    public function test_viewing_needs_the_view_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.languages.index'))
            ->assertForbidden();
    }

    public function test_changing_anything_needs_the_manage_permission(): void
    {
        $viewer = $this->userWith([PermissionKey::LanguagesView]);
        $german = Language::where('code', 'de')->firstOrFail();

        $this->actingAs($viewer)->get(route('admin.languages.index'))->assertOk();

        $this->actingAs($viewer)->post(route('admin.languages.store'), ['code' => 'es', 'name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.languages.update', $german), ['code' => 'de', 'name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.languages.default', $german))->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.languages.destroy', $german))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('admin.languages.index'))->assertRedirect();
    }

    // ── Ön yüze yansıma ──

    public function test_a_newly_published_language_appears_in_the_switcher(): void
    {
        $this->actingAs($this->manager())
            ->put(route('admin.languages.update', Language::where('code', 'de')->firstOrFail()), [
                'code' => 'de', 'name' => 'Almanca', 'native_name' => 'Deutsch', 'flag' => '🇩🇪', 'is_active' => '1',
            ]);

        $html = $this->followingRedirects()->get('/')->getContent();

        $this->assertStringContainsString('Deutsch', $html);
        $this->assertStringContainsString('hreflang="de"', $html);
    }

    public function test_switching_a_language_off_removes_it_from_the_switcher(): void
    {
        $this->actingAs($this->manager())
            ->put(route('admin.languages.update', Language::where('code', 'en')->firstOrFail()), [
                'code' => 'en', 'name' => 'İngilizce',
            ]);

        $html = $this->followingRedirects()->get('/')->getContent();

        $this->assertStringNotContainsString('hreflang="en"', $html);
    }
}
