<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionKey;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\CampaignDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gönderim limitleri: saatlik tavan, tur başına adet ve yeniden deneme.
 *
 * Bu üç değer gönderen hesabın kotasıyla ilgili, kampanyayla değil — bu yüzden
 * kampanya formunda değil mail ayarlarında duruyorlar. Veritabanında zaten
 * vardılar ama panelden düzenlenemiyordu; buradaki testler artık
 * düzenlenebildiğini ve dağıtıcının okuduğunu doğruluyor.
 */
class MailSendingLimitSettingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seedAuthorization();

        $user = User::create([
            'first_name' => 'Ayar',
            'last_name'  => 'Yöneticisi',
            'email'      => 'limit-admin@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $role = Role::where('slug', 'admin')->firstOrFail();
        $role->permissions()->syncWithoutDetaching(
            Permission::whereIn('key', [PermissionKey::SettingsView->value, PermissionKey::SettingsManage->value])
                ->pluck('id')
                ->all(),
        );

        $user->roles()->attach($role);

        return $user;
    }

    public function test_the_settings_page_offers_the_sending_limits(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Gönderim Limitleri')
            ->assertSee('settings[mail_hourly_limit]', false)
            ->assertSee('settings[mail_batch_max]', false)
            ->assertSee('settings[mail_max_attempts]', false);
    }

    public function test_the_hourly_limit_is_saved_and_used_by_the_dispatcher(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), [
                'settings' => ['mail_hourly_limit' => '250'],
            ])
            ->assertRedirect();

        $this->assertSame('250', Setting::where('key', 'mail_hourly_limit')->value('value'));
        $this->assertSame(250, app(CampaignDispatcher::class)->hourlyLimit());
    }

    /**
     * Tur başına adet boş bırakıldığında saatlik limit turlara bölünür; bu
     * varsayılan davranış limit değişince de geçerli kalmalı.
     */
    public function test_the_per_run_quota_follows_the_hourly_limit(): void
    {
        Setting::setValue('mail_hourly_limit', '240', 'mail', 'text');
        Setting::setValue('mail_batch_max', '0', 'mail', 'text');

        $runsPerHour = (int) (60 / CampaignDispatcher::RUN_INTERVAL_MINUTES);

        $this->assertSame(intdiv(240, $runsPerHour), app(CampaignDispatcher::class)->perRunQuota());
    }

    /**
     * Metin girilirse tavan sıfıra düşer ve kampanyalar sessizce durur; bu
     * yüzden sayı olmayan değer kaydedilmemeli.
     */
    public function test_a_non_numeric_limit_is_rejected(): void
    {
        Setting::setValue('mail_hourly_limit', '100', 'mail', 'text');

        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), [
                'settings' => ['mail_hourly_limit' => 'çok'],
            ])
            ->assertSessionHasErrors('settings.mail_hourly_limit');

        $this->assertSame('100', Setting::where('key', 'mail_hourly_limit')->value('value'));
    }

    public function test_the_retry_count_is_bounded(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), [
                'settings' => ['mail_max_attempts' => '99'],
            ])
            ->assertSessionHasErrors('settings.mail_max_attempts');
    }
}
