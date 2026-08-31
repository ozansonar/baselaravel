<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use App\Services\AccountService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Doğrulama damgası adrese ait, hesaba değil.
 *
 * Adres değişip damga yerinde kalırsa kullanıcı sahibi olmadığı bir adrese
 * geçip "doğrulanmış" kalabiliyordu — ve doğrulamaya bakan her yer (ön yüzdeki
 * /hesabim, API'nin hesap uçları, kampanya alıcı süzgeci) kanıtlanmamış bir
 * adrese güveniyordu.
 *
 * Kural UserObserver'da, yani adresi değiştiren her yol için geçerli. Buradaki
 * sınamalar bunu üç ayrı yoldan da doğruluyor; dördüncü bir yol eklenirse
 * kuralı ayrıca hatırlamak gerekmiyor.
 */
class EmailChangeRevokesVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        Mail::fake();
    }

    private function verifiedUser(array $attributes = []): User
    {
        return User::factory()->create([
            'email'             => 'eski@ornek.com',
            'password'          => 'Gizli*12345',
            'email_verified_at' => now(),
            ...$attributes,
        ]);
    }

    // ── Modelin kendisi ──

    public function test_changing_the_address_clears_the_verification_stamp(): void
    {
        $user = $this->verifiedUser();

        $user->update(['email' => 'yeni@ornek.com']);

        $this->assertNull($user->fresh()?->email_verified_at);
        $this->assertFalse($user->fresh()?->hasVerifiedEmail());
    }

    /**
     * Doğrulama adresinin imzası e-postadan türüyor (sha1), yani adres
     * değiştiği anda eski bağlantı zaten çalışmaz hâle geliyor. Yenisi
     * gönderilmezse kullanıcının doğrulanmasının hiçbir yolu kalmaz.
     */
    public function test_a_fresh_verification_link_is_sent_to_the_new_address(): void
    {
        $user = $this->verifiedUser();

        $user->update(['email' => 'yeni@ornek.com']);

        Mail::assertQueued(VerifyEmailMail::class, fn ($mail): bool => $mail->hasTo('yeni@ornek.com'));
    }

    public function test_saving_without_touching_the_address_leaves_verification_alone(): void
    {
        $user = $this->verifiedUser();

        $user->update(['first_name' => 'Ozan', 'email' => 'eski@ornek.com']);

        $this->assertNotNull($user->fresh()?->email_verified_at);
        Mail::assertNotQueued(VerifyEmailMail::class);
    }

    /**
     * Doğrulamanın kendisi damgayı yazıyor; gözlemci onu geri almamalı.
     */
    public function test_verifying_still_works(): void
    {
        $user = $this->verifiedUser(['email_verified_at' => null]);

        $user->markEmailAsVerified();

        $this->assertNotNull($user->fresh()?->email_verified_at);
    }

    // ── Adresin değişebildiği yollar ──

    public function test_the_front_profile_form_resets_verification(): void
    {
        $user = $this->verifiedUser();

        $this->actingAs($user)
            ->put(route('account.profile.update', ['locale' => 'tr']), [
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => 'yeni@ornek.com',
            ])
            // Doğrulama ekranına gidiyor: /hesabim artık kapalı ve kullanıcının
            // sebebini görmesi gerekiyor.
            ->assertRedirect(route('verification.notice', ['locale' => 'tr']))
            ->assertSessionHas('success', __('site.account.email_changed'));

        $this->assertNull($user->fresh()?->email_verified_at);
    }

    public function test_the_api_profile_endpoint_resets_verification(): void
    {
        $user = $this->verifiedUser();

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Gizli*12345',
        ])->json('data.token');

        $this->app['auth']->forgetGuards();

        $this->withToken((string) $token)
            ->putJson('/api/v1/account/profile', [
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => 'yeni@ornek.com',
            ])
            ->assertOk()
            // İstemci bunu yanıttan öğrenmeli: bir sonraki istek 403 olacak.
            ->assertJsonPath('data.email_verified', false)
            ->assertJsonPath('message', __('site.account.email_changed'));

        // Ve gerçekten kapanıyor.
        $this->app['auth']->forgetGuards();

        $this->withToken((string) $token)
            ->putJson('/api/v1/account/profile', [
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => 'yeni@ornek.com',
            ])
            ->assertForbidden()
            ->assertJsonPath('errors.code.0', 'email_unverified');
    }

    public function test_the_account_service_resets_verification(): void
    {
        $user = $this->verifiedUser();

        app(AccountService::class)->updateProfile($user, [
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => 'yeni@ornek.com',
        ]);

        $this->assertNull($user->fresh()?->email_verified_at);
    }

    /**
     * Panelden yapılan değişiklikte de geçerli: adres yine kanıtlanmamıştır ve
     * mail onu kanıtlaması gereken kişiye, yani yeni adrese gider.
     */
    public function test_the_admin_screen_resets_verification_too(): void
    {
        $user = $this->verifiedUser();

        app(UserService::class)->update($user, ['email' => 'yeni@ornek.com']);

        $this->assertNull($user->fresh()?->email_verified_at);
        Mail::assertQueued(VerifyEmailMail::class, fn ($mail): bool => $mail->hasTo('yeni@ornek.com'));
    }
}
