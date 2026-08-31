<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\EmailChangedMail;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use App\Services\AccountService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * E-posta adresi değiştiğinde olması gereken iki şey.
 *
 * **Doğrulama düşer.** Damga adrese ait, hesaba değil. Adres değişip damga
 * yerinde kalırsa kullanıcı sahibi olmadığı bir adrese geçip "doğrulanmış"
 * kalabiliyordu — ve doğrulamaya bakan her yer (ön yüzdeki /hesabim, API'nin
 * hesap uçları, kampanya alıcı süzgeci) kanıtlanmamış bir adrese güveniyordu.
 *
 * **Eski adres uyarılır.** Hesabı ele geçiren kişinin ilk yaptığı şey çoğu
 * zaman adresi değiştirmektir: o andan sonra şifre sıfırlama bağlantısı da
 * bildirimler de ona gider ve gerçek sahibin hesaptan haberi kesilir. Yeni
 * adrese giden doğrulama maili bu senaryoda saldırganın kutusuna düşer, yani
 * kimseyi uyarmaz — sahibin durumu öğrenebileceği tek şey eski adrese giden
 * uyarıdır ve gönderilebileceği son an değişiklik anıdır.
 *
 * İkisi de UserObserver'da, yani adresi değiştiren her yol için geçerli.
 * Buradaki sınamalar bunu üç ayrı yoldan da doğruluyor (ön yüz formu, API ucu,
 * panel); dördüncü bir yol eklenirse kuralı ayrıca hatırlamak gerekmiyor.
 */
class EmailChangeSecurityTest extends TestCase
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

    /**
     * Hesabı ele geçiren kişinin ilk yaptığı şey çoğu zaman adresi değiştirmek:
     * o andan sonra şifre sıfırlama bağlantısı da bildirimler de ona gider.
     * Yeni adrese giden doğrulama maili bu senaryoda saldırganın kutusuna düşer,
     * yani kimseyi uyarmaz. Sahibin durumu öğrenebileceği tek şey bu uyarı.
     */
    public function test_the_previous_address_is_warned(): void
    {
        $user = $this->verifiedUser();

        $user->update(['email' => 'saldirgan@baska.com']);

        Mail::assertQueued(
            EmailChangedMail::class,
            fn (EmailChangedMail $mail): bool => $mail->hasTo('eski@ornek.com'),
        );
    }

    /**
     * Yeni adres maskeli gidiyor: tamamen gizlenseydi sahibi neyin olduğunu
     * anlatamaz, olduğu gibi yazılsaydı bu mail bir adresi başkasına
     * sızdırmanın yolu olurdu.
     */
    public function test_the_warning_masks_the_new_address(): void
    {
        $user = $this->verifiedUser();

        $user->update(['email' => 'saldirgan@baska.com']);

        Mail::assertQueued(EmailChangedMail::class, function (EmailChangedMail $mail): bool {
            $this->assertSame('s***n@baska.com', $mail->maskedNewEmail);
            $this->assertStringNotContainsString('saldirgan@baska.com', $mail->maskedNewEmail);

            // Eski adres maskelenmiyor: zaten okuyanın kendi adresi.
            $this->assertSame('eski@ornek.com', $mail->previousEmail);

            return true;
        });
    }

    public function test_the_two_mails_go_to_two_different_addresses(): void
    {
        $user = $this->verifiedUser();

        $user->update(['email' => 'yeni@ornek.com']);

        // Uyarı eskiye, doğrulama yeniye. Yer değiştirirlerse uyarı saldırganın
        // kutusuna düşer ve hiçbir işe yaramaz.
        Mail::assertQueued(EmailChangedMail::class, fn ($mail): bool => $mail->hasTo('eski@ornek.com'));
        Mail::assertQueued(VerifyEmailMail::class, fn ($mail): bool => $mail->hasTo('yeni@ornek.com'));
    }

    public function test_a_new_account_warns_nobody(): void
    {
        User::factory()->create(['email' => 'yepyeni@ornek.com']);

        Mail::assertNotQueued(EmailChangedMail::class);
    }

    public function test_saving_without_touching_the_address_leaves_verification_alone(): void
    {
        $user = $this->verifiedUser();

        $user->update(['first_name' => 'Ozan', 'email' => 'eski@ornek.com']);

        $this->assertNotNull($user->fresh()?->email_verified_at);
        Mail::assertNotQueued(VerifyEmailMail::class);
        Mail::assertNotQueued(EmailChangedMail::class);
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

        // Uyarı panelden yapılan değişiklikte daha da önemli: kullanıcı kendisi
        // yapmadığı bir değişiklikten yalnızca bu mailden haberdar olabilir.
        Mail::assertQueued(EmailChangedMail::class, fn ($mail): bool => $mail->hasTo('eski@ornek.com'));
    }

    /**
     * Posta yolu tıkalıyken bile adres değişikliği tamamlanmalı.
     *
     * Aksi hâlde SMTP'nin düştüğü bir anda kullanıcı profilini kaydedemez ve
     * gördüğü hata ona hiçbir şey anlatmaz. Gönderim katmanı zaten kendi
     * hatasını yutup loga yazıyor; buradaki sınama o güvencenin gerçekten
     * uçtan uca durduğunu gösteriyor.
     */
    public function test_a_failing_mail_does_not_break_the_change(): void
    {
        $user = $this->verifiedUser();

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP kapalı'));

        $user->update(['email' => 'yeni@ornek.com']);

        $this->assertSame('yeni@ornek.com', $user->fresh()?->email);
        // Damga yine de düşmüş olmalı: adres kanıtlanmamış olmaya devam ediyor.
        $this->assertNull($user->fresh()?->email_verified_at);
    }
}
