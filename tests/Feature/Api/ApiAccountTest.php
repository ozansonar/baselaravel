<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Hesap uçları: profil güncelleme ve e-posta doğrulama.
 *
 * Kapının ön yüzdekiyle aynı olması önemli — /hesabim giriş, açık hesap ve
 * doğrulanmış e-posta istiyor. Üçünden biri API'de gevşek kalsaydı web'den
 * kapalı olan kapı mobilden açık olurdu.
 */
class ApiAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function signedIn(array $attributes = []): array
    {
        $user = User::factory()->create([
            'password'          => 'Gizli*12345',
            'email_verified_at' => now(),
            ...$attributes,
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Gizli*12345',
        ])->json('data.token');

        $this->app['auth']->forgetGuards();

        return [$user, (string) $token];
    }

    // ── Profil ──

    public function test_the_profile_can_be_updated(): void
    {
        [$user, $token] = $this->signedIn();

        $this->withToken($token)
            ->putJson('/api/v1/account/profile', [
                'first_name' => 'Ozan',
                'last_name'  => 'Sonar',
                'email'      => 'guncel@ornek.com',
                'phone'      => '0505 111 22 33',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.first_name', 'Ozan')
            ->assertJsonPath('data.email', 'guncel@ornek.com');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'guncel@ornek.com']);
    }

    public function test_the_password_can_be_changed_only_with_the_current_one(): void
    {
        [$user, $token] = $this->signedIn();

        $payload = [
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'password'   => 'Yeni*12345',
            'password_confirmation' => 'Yeni*12345',
        ];

        // Mevcut şifre olmadan reddediliyor: ele geçirilmiş bir jeton, gerçek
        // sahibi hesabından kilitleyememeli.
        $this->withToken($token)
            ->putJson('/api/v1/account/profile', $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['current_password']]);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->putJson('/api/v1/account/profile', [...$payload, 'current_password' => 'Gizli*12345'])
            ->assertOk();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Yeni*12345'])
            ->assertOk();
    }

    public function test_the_profile_never_returns_the_password(): void
    {
        [$user, $token] = $this->signedIn();

        $payload = $this->withToken($token)
            ->putJson('/api/v1/account/profile', [
                'first_name' => 'Ozan', 'last_name' => 'Sonar', 'email' => $user->email,
            ])
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('remember_token', $payload);
    }

    public function test_an_avatar_can_be_uploaded_in_the_same_request(): void
    {
        [$user, $token] = $this->signedIn();

        $this->withToken($token)
            ->post('/api/v1/account/profile', [
                '_method'    => 'PUT',
                'first_name' => 'Ozan',
                'last_name'  => 'Sonar',
                'email'      => $user->email,
                'avatar'     => UploadedFile::fake()->image('ben.jpg', 400, 400),
            ])
            ->assertOk();

        $this->assertNotNull($user->fresh()?->avatar);
    }

    public function test_the_profile_is_closed_to_anonymous_requests(): void
    {
        $this->putJson('/api/v1/account/profile', [
            'first_name' => 'Ozan', 'last_name' => 'Sonar', 'email' => 'a@ornek.com',
        ])->assertUnauthorized();
    }

    /**
     * Ön yüzdeki /hesabim `verified` istiyor; API de istemeli.
     */
    public function test_an_unverified_account_cannot_touch_the_profile(): void
    {
        [$user, $token] = $this->signedIn(['email_verified_at' => null]);

        $this->withToken($token)
            ->putJson('/api/v1/account/profile', [
                'first_name' => 'Ozan', 'last_name' => 'Sonar', 'email' => $user->email,
            ])
            ->assertForbidden()
            ->assertJsonPath('errors.code.0', 'email_unverified');
    }

    // ── E-posta doğrulama ──

    /**
     * /me bilerek doğrulama şartının dışında: uygulama "e-postanı doğrula"
     * ekranını buna bakarak çiziyor, kapatılsaydı neye bakacağını bilemezdi.
     */
    public function test_me_stays_open_to_an_unverified_account(): void
    {
        [$user, $token] = $this->signedIn(['email_verified_at' => null]);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email_verified', false);
    }

    public function test_the_verification_link_can_be_resent(): void
    {
        Mail::fake();

        [, $token] = $this->signedIn(['email_verified_at' => null]);

        $this->withToken($token)
            ->postJson('/api/v1/auth/email/resend')
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertQueued(VerifyEmailMail::class);
    }

    public function test_resending_is_a_no_op_for_a_verified_account(): void
    {
        Mail::fake();

        [, $token] = $this->signedIn();

        $this->withToken($token)
            ->postJson('/api/v1/auth/email/resend')
            ->assertOk();

        Mail::assertNotQueued(VerifyEmailMail::class);
    }

    public function test_resending_is_throttled(): void
    {
        Mail::fake();

        [, $token] = $this->signedIn(['email_verified_at' => null]);

        $limit = (int) config('api.rate_limits.verification');

        for ($attempt = 0; $attempt < $limit; $attempt++) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->postJson('/api/v1/auth/email/resend')->assertOk();
        }

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/auth/email/resend')->assertStatus(429);
    }
}
