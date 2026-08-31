<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Sanctum jetonuyla kimlik doğrulama.
 *
 * Buradaki sınamalar "çalışıyor mu"dan çok "yanlış durumda ne oluyor"a bakıyor:
 * yanlış şifre hangi kodu döndürüyor, kapatılan hesabın elindeki jeton hâlâ
 * geçiyor mu, çıkış yapan cihaz ötekileri de düşürüyor mu.
 */
class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        // Hız sınırı sayaçları test edenler dışında yolda durmasın: array cache
        // testler arasında sıfırlanıyor ama aynı test içinde art arda atılan
        // istekler birikiyor.
        RateLimiter::clear('api-login');
    }

    /**
     * Jetonla istek atmadan önce guard'ların istek içi belleğini boşaltır.
     *
     * Gerçek bir istekte her seferinde yeni bir uygulama örneği doğuyor; testte
     * ise aynı örnek bütün istekler boyunca yaşıyor ve Sanctum'un guard'ı ilk
     * çözdüğü kullanıcıyı belleğinde tutuyor. Boşaltılmazsa iptal edilmiş bir
     * jeton "hâlâ geçerli" görünür — ürünün değil, sınama düzeneğinin kusuru.
     */
    private function asToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_register_creates_a_user_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name'            => 'Ozan',
            'last_name'             => 'Sonar',
            'email'                 => 'yeni@ornek.com',
            'password'              => 'Gizli*12345',
            'password_confirmation' => 'Gizli*12345',
            'device_name'           => 'iPhone 15',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'yeni@ornek.com')
            ->assertJsonStructure(['success', 'message', 'data' => ['user', 'token', 'token_type', 'expires_at']]);

        $this->assertDatabaseHas('users', ['email' => 'yeni@ornek.com']);

        // Jeton cihaz adıyla etiketlenmeli: kullanıcı "cihazlarım" listesinde
        // hangi oturumu iptal ettiğini bilmeli.
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'iPhone 15']);
    }

    public function test_register_never_leaks_the_password_hash(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name'            => 'Ozan',
            'last_name'             => 'Sonar',
            'email'                 => 'gizli@ornek.com',
            'password'              => 'Gizli*12345',
            'password_confirmation' => 'Gizli*12345',
        ]);

        $response->assertCreated();

        $user = $response->json('data.user');

        $this->assertArrayNotHasKey('password', $user);
        $this->assertArrayNotHasKey('remember_token', $user);
    }

    public function test_register_is_refused_when_registration_is_closed(): void
    {
        Setting::setValue('registration_enabled', '0');

        $this->postJson('/api/v1/auth/register', [
            'first_name'            => 'Ozan',
            'last_name'             => 'Sonar',
            'email'                 => 'kapali@ornek.com',
            'password'              => 'Gizli*12345',
            'password_confirmation' => 'Gizli*12345',
        ])->assertForbidden()->assertJsonPath('success', false);

        $this->assertDatabaseMissing('users', ['email' => 'kapali@ornek.com']);
    }

    public function test_register_rejects_an_email_already_in_use(): void
    {
        User::factory()->create(['email' => 'dolu@ornek.com']);

        $this->postJson('/api/v1/auth/register', [
            'first_name'            => 'Ozan',
            'last_name'             => 'Sonar',
            'email'                 => 'dolu@ornek.com',
            'password'              => 'Gizli*12345',
            'password_confirmation' => 'Gizli*12345',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    /**
     * Soft delete ile silinen bir hesabın adresi sonsuza dek işgal edilmiş
     * olmamalı — kural {@see \App\Rules\UserEmail::unique()} içinde ve API'nin
     * de aynı kuralı kullandığı burada doğrulanıyor.
     */
    public function test_register_accepts_the_email_of_a_soft_deleted_account(): void
    {
        $user = User::factory()->create(['email' => 'geri@ornek.com']);
        $user->delete();

        $this->postJson('/api/v1/auth/register', [
            'first_name'            => 'Ozan',
            'last_name'             => 'Sonar',
            'email'                 => 'geri@ornek.com',
            'password'              => 'Gizli*12345',
            'password_confirmation' => 'Gizli*12345',
        ])->assertCreated();
    }

    public function test_login_returns_a_working_token(): void
    {
        User::factory()->create([
            'email'    => 'giris@ornek.com',
            'password' => 'Gizli*12345',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email'    => 'giris@ornek.com',
            'password' => 'Gizli*12345',
        ])->assertOk()->assertJsonPath('success', true)->json('data.token');

        $this->asToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'giris@ornek.com');
    }

    public function test_login_with_a_wrong_password_returns_401(): void
    {
        User::factory()->create([
            'email'    => 'giris@ornek.com',
            'password' => 'Gizli*12345',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'giris@ornek.com',
            'password' => 'YanlisSifre1',
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        User::factory()->create([
            'email'     => 'pasif@ornek.com',
            'password'  => 'Gizli*12345',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'pasif@ornek.com',
            'password' => 'Gizli*12345',
        ])->assertForbidden();
    }

    /**
     * Hesap kapatıldığında elde kalan jeton da ölmeli.
     *
     * Oturum kendiliğinden sona eriyor, jeton ermiyor: kontrol edilmezse
     * yönetici hesabı kapattıktan sonra mobil uygulama aylarca erişmeye devam
     * ederdi.
     */
    public function test_a_token_stops_working_once_the_account_is_deactivated(): void
    {
        $user = User::factory()->create(['password' => 'Gizli*12345']);

        $token = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Gizli*12345',
        ])->json('data.token');

        $user->update(['is_active' => false]);

        $this->asToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden();

        // Sadece reddedilmiyor, jeton da siliniyor.
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_logout_revokes_only_the_calling_device(): void
    {
        $user = User::factory()->create(['password' => 'Gizli*12345']);

        $phone = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Gizli*12345', 'device_name' => 'telefon',
        ])->json('data.token');

        $tablet = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Gizli*12345', 'device_name' => 'tablet',
        ])->json('data.token');

        $this->asToken($phone)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->asToken($phone)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        // Tabletteki oturum ayakta: telefondan çıkmak öteki cihazı kapatmamalı.
        $this->asToken($tablet)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_signing_in_again_from_the_same_device_replaces_the_old_token(): void
    {
        $user = User::factory()->create(['password' => 'Gizli*12345']);

        $first = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Gizli*12345', 'device_name' => 'telefon',
        ])->json('data.token');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Gizli*12345', 'device_name' => 'telefon',
        ])->assertOk();

        $this->assertSame(1, $user->tokens()->where('name', 'telefon')->count());

        $this->asToken($first)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_issued_tokens_carry_an_expiry(): void
    {
        $user = User::factory()->create(['password' => 'Gizli*12345']);

        $expiresAt = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Gizli*12345',
        ])->json('data.expires_at');

        $this->assertNotNull($expiresAt, 'Jeton son kullanma tarihi taşımalı.');
        $this->assertNotNull(PersonalAccessToken::first()?->expires_at);
    }

    public function test_protected_endpoints_reject_a_request_without_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_repeated_failed_logins_are_throttled(): void
    {
        User::factory()->create(['email' => 'kaba@ornek.com', 'password' => 'Gizli*12345']);

        $limit = (int) config('api.rate_limits.login');

        for ($attempt = 0; $attempt < $limit; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'kaba@ornek.com', 'password' => 'yanlis-sifre',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'kaba@ornek.com', 'password' => 'yanlis-sifre',
        ])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertHeader('Retry-After');
    }
}
