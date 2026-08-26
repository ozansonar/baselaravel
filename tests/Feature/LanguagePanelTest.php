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

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('Deutsch', $html);
        $this->assertStringContainsString('hreflang="de"', $html);
    }

    public function test_switching_a_language_off_removes_it_from_the_switcher(): void
    {
        $this->actingAs($this->manager())
            ->put(route('admin.languages.update', Language::where('code', 'en')->firstOrFail()), [
                'code' => 'en', 'name' => 'İngilizce',
            ]);

        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('hreflang="en"', $html);
    }
}
