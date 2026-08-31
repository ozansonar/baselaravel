<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\TokenAbility;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * API'de veri indirme ve hesap kapatma.
 *
 * Hesap kapatma mağazaların şartı: uygulama içinde hesabını silemeyen bir
 * uygulama App Store ve Play'de yayınlanamıyor. Ama düğmenin kendisi de en
 * yıkıcı düğme, o yüzden jeton tek başına yetmiyor — şifre isteniyor.
 */
class ApiAccountDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        RateLimiter::clear('api-password');
    }

    private function user(string $email = 'mobil@ornek.com'): User
    {
        $user = User::create([
            'first_name' => 'Ozan',
            'last_name'  => 'Sonar',
            'email'      => $email,
            'password'   => 'Gizli*12345',
            'is_active'  => true,
        ]);
        $user->markEmailAsVerified();

        return $user;
    }

    private function tokenFor(User $user, ?array $abilities = null): string
    {
        $abilities ??= array_map(fn (TokenAbility $a): string => $a->value, TokenAbility::cases());

        $this->app['auth']->forgetGuards();

        return $user->createToken('test', $abilities)->plainTextToken;
    }

    public function test_the_export_returns_the_persons_own_data(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/account/export')
            ->assertOk()
            ->assertJsonPath('data.profile.email', $user->email)
            ->assertJsonStructure(['data' => ['profile', 'blog_comments', 'newsletter', 'devices']]);
    }

    public function test_the_export_is_not_cached_by_intermediaries(): void
    {
        $user = $this->user();

        $response = $this->withToken($this->tokenFor($user))->getJson('/api/v1/account/export');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
    }

    public function test_the_export_needs_a_token(): void
    {
        $this->getJson('/api/v1/account/export')->assertStatus(401);
    }

    public function test_closing_needs_the_password(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))
            ->deleteJson('/api/v1/account', ['password' => 'yanlis-sifre'])
            ->assertStatus(422);

        $this->assertNotSoftDeleted('users', ['id' => $user->getKey()]);
    }

    public function test_closing_removes_the_account_and_its_tokens(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))
            ->deleteJson('/api/v1/account', ['password' => 'Gizli*12345'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('users', ['id' => $user->getKey()]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_staff_account_is_refused(): void
    {
        $admin = $this->user('yonetici@ornek.com');
        $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail()->id);

        $this->withToken($this->tokenFor($admin->fresh()))
            ->deleteJson('/api/v1/account', ['password' => 'Gizli*12345'])
            ->assertStatus(403);

        $this->assertNotSoftDeleted('users', ['id' => $admin->getKey()]);
    }

    /**
     * Dar yetkili bir jeton hesabı kapatamamalı: yalnız profil okuması için
     * verilen jeton, en yıkıcı işlemi de yapabilseydi yetki ayrımı sözde
     * kalırdı.
     */
    public function test_a_read_only_token_cannot_close_the_account(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user, [TokenAbility::ProfileRead->value]))
            ->deleteJson('/api/v1/account', ['password' => 'Gizli*12345'])
            ->assertStatus(403);

        $this->assertNotSoftDeleted('users', ['id' => $user->getKey()]);
    }

    // ── Şifre değiştirme ──

    public function test_the_password_can_be_changed_with_the_current_one(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))
            ->putJson('/api/v1/account/password', [
                'current_password'      => 'Gizli*12345',
                'password'              => 'YeniGizli*123',
                'password_confirmation' => 'YeniGizli*123',
            ])
            ->assertOk();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('YeniGizli*123', (string) $user->fresh()?->password));
    }

    public function test_the_password_change_needs_the_current_password(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))
            ->putJson('/api/v1/account/password', [
                'current_password'      => 'yanlis-sifre',
                'password'              => 'YeniGizli*123',
                'password_confirmation' => 'YeniGizli*123',
            ])
            ->assertStatus(422);

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Gizli*12345', (string) $user->fresh()?->password));
    }

    /**
     * Şifresinin ele geçtiğini düşünen kişi öteki cihazları düşürebilmeli —
     * ama kendi oturumundan atılmamalı.
     */
    public function test_the_other_devices_are_dropped_only_when_asked(): void
    {
        $user = $this->user();
        $user->createToken('Eski telefon');
        $token = $this->tokenFor($user);

        $this->withToken($token)->putJson('/api/v1/account/password', [
            'current_password'      => 'Gizli*12345',
            'password'              => 'YeniGizli*123',
            'password_confirmation' => 'YeniGizli*123',
        ])->assertOk();

        // İstenmedi: eski jeton ayakta.
        $this->assertSame(2, $user->tokens()->count());

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->putJson('/api/v1/account/password', [
            'current_password'      => 'YeniGizli*123',
            'password'              => 'UcuncuGizli*123',
            'password_confirmation' => 'UcuncuGizli*123',
            'logout_other_devices'  => true,
        ])->assertOk();

        // İstendi: yalnız bu isteği yapan jeton kaldı.
        $this->assertSame(1, $user->tokens()->count());
    }

    // ── Bildirim tercihleri ──

    public function test_the_preferences_endpoint_describes_every_type(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/account/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.preferences.comment_updates', true)
            ->assertJsonPath('data.newsletter', false)
            ->assertJsonStructure(['data' => ['types' => [['key', 'label', 'description']]]]);
    }

    public function test_a_preference_can_be_turned_off_through_the_api(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))
            ->putJson('/api/v1/account/notification-preferences', [
                'preferences' => ['comment_updates' => false],
            ])
            ->assertOk()
            ->assertJsonPath('data.preferences.comment_updates', false);

        $this->assertFalse(
            app(\App\Services\NotificationPreferenceService::class)
                ->allows($user->fresh(), \App\Enums\NotificationPreference::CommentUpdates)
        );
    }

    /**
     * Tanınmayan anahtar sessizce yutulsaydı istemci "kapattım" sanır, posta
     * gelmeye devam ederdi.
     */
    public function test_an_unknown_preference_key_is_refused(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))
            ->putJson('/api/v1/account/notification-preferences', [
                'preferences' => ['uydurma_tur' => false],
            ])
            ->assertStatus(422);
    }

    public function test_the_newsletter_switch_works_through_the_api(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))
            ->putJson('/api/v1/account/notification-preferences', ['newsletter' => true])
            ->assertOk()
            ->assertJsonPath('data.newsletter', true);
    }
}
