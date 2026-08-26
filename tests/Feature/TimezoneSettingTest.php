<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionKey;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The site timezone: one value, applied everywhere.
 *
 * It used to be set from a middleware, which meant web requests honoured it and
 * the scheduler did not — backups, campaign send times and analytics were
 * written in a different timezone from the same columns the site wrote.
 */
class TimezoneSettingTest extends TestCase
{
    use RefreshDatabase;

    private function applyTimezone(): void
    {
        // The provider's boot already ran for this test's app instance, so the
        // setting written afterwards is applied by hand — the same method the
        // provider calls.
        $method = new \ReflectionMethod(AppServiceProvider::class, 'applyConfiguredTimezone');
        $method->invoke(app()->getProvider(AppServiceProvider::class));
    }

    public function test_the_setting_drives_the_application_timezone(): void
    {
        Setting::setValue('app_timezone', 'Europe/London', 'general', 'text');
        $this->applyTimezone();

        $this->assertSame('Europe/London', config('app.timezone'));
        $this->assertSame('Europe/London', date_default_timezone_get());
    }

    /**
     * The whole reason it moved out of the middleware.
     */
    public function test_it_applies_in_the_console_where_middleware_never_runs(): void
    {
        Setting::setValue('app_timezone', 'Asia/Tokyo', 'general', 'text');
        $this->applyTimezone();

        $this->artisan('inspire')->assertSuccessful();

        $this->assertSame('Asia/Tokyo', config('app.timezone'), 'Konsolda ayar uygulanmıyor');
    }

    public function test_web_and_console_agree(): void
    {
        Setting::setValue('app_timezone', 'America/New_York', 'general', 'text');
        $this->applyTimezone();

        $fromWeb = $this->get('/tr')->isSuccessful() ? config('app.timezone') : null;
        $fromConsole = config('app.timezone');

        $this->assertSame('America/New_York', $fromWeb);
        $this->assertSame($fromWeb, $fromConsole, 'Web ve konsol farklı saat dilimi kullanıyor');
    }

    public function test_no_setting_leaves_the_config_default_alone(): void
    {
        $default = config('app.timezone');

        Setting::where('key', 'app_timezone')->delete();
        Setting::clearSettingsCache();
        $this->applyTimezone();

        $this->assertSame($default, config('app.timezone'));
    }

    /**
     * A bad value must not take date handling down with it.
     */
    public function test_an_invalid_timezone_is_ignored(): void
    {
        $default = config('app.timezone');

        Setting::setValue('app_timezone', 'Mars/Olympus_Mons', 'general', 'text');
        $this->applyTimezone();

        $this->assertSame($default, config('app.timezone'));
    }

    // ── Panel ──

    /**
     * @param array<int, PermissionKey> $extra
     */
    private function admin(array $extra = []): User
    {
        $role = Role::create(['name' => 'Ayar', 'slug' => 'settings-' . uniqid()]);

        $ids = [];
        foreach ([PermissionKey::SettingsView, PermissionKey::SettingsManage, ...$extra] as $key) {
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

    public function test_the_screen_offers_the_timezone_but_not_a_language(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/settings')->getContent();

        $this->assertStringContainsString('settings[app_timezone]', $html);

        // Language used to sit here as a second dropdown that nothing read; the
        // real default lives on the languages screen.
        $this->assertStringNotContainsString('settings[app_locale]', $html);
    }

    public function test_the_screen_points_at_where_language_is_managed(): void
    {
        $this->actingAs($this->admin([PermissionKey::LanguagesView]))
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee(route('admin.languages.index'), false);
    }

    /**
     * Someone who cannot open the languages screen still gets told where the
     * setting lives — just without a link they would only be refused at.
     */
    public function test_the_pointer_is_plain_text_without_that_permission(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Site dili')
            ->assertDontSee(route('admin.languages.index'), false);
    }

    public function test_the_timezone_can_be_saved_from_the_screen(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), [
                'settings' => ['app_timezone' => 'Europe/Berlin'],
            ])
            ->assertRedirect();

        $this->assertSame('Europe/Berlin', Setting::getValue('app_timezone'));
    }
}
