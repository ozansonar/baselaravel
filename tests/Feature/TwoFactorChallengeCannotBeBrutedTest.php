<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * İkinci adımın altı haneli kodu tahmin edilemez olmalı.
 *
 * Kod tek başına zayıf — bir milyon olasılık — ve güvenliğini iki şey
 * taşıyor: denemenin sınırlı olması ve yanlış denemenin bir bedeli olması.
 * İkisi de yoktu:
 *
 *  1. Rota `throttle:login` kovasını kullanıyordu, o kovanın anahtarı da
 *     isteğin gövdesindeki `email`. İkinci adım formu yalnız `code`
 *     gönderiyor, yani gövdeye her seferinde farklı bir `email` koymak yeni
 *     bir kova açıyordu; sınır hiçbir şey tutmuyordu.
 *  2. Yanlış kod bekleme durumunu düşürmüyordu ve sayaç yoktu.
 *
 * Şifresi ele geçmiş bir hesapta bu ikisi birleşince ikinci etken tamamen
 * devre dışı kalıyordu.
 */
final class TwoFactorChallengeCannotBeBrutedTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = '';

    private function userWithTwoFactor(): User
    {
        $user = User::create([
            'first_name' => 'İki',
            'last_name'  => 'Adım',
            'email'      => 'iki@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $service = app(TwoFactorService::class);
        $this->secret = $service->beginSetup($user);
        $service->confirm($user->fresh(), $this->currentCode());

        return $user->fresh();
    }

    private function currentCode(): string
    {
        $method = new \ReflectionMethod(TwoFactorService::class, 'codeAt');
        $method->setAccessible(true);

        return (string) $method->invoke(app(TwoFactorService::class), $this->secret, intdiv(time(), 30));
    }

    /** Şifre adımı: buradan sonra bekleme durumu kuruluyor. */
    private function startChallenge(User $user): void
    {
        $this->post('/tr/giris', [
            'email'    => $user->email,
            'password' => 'password',
        ]);
    }

    /**
     * Kova gövdedeki alandan kurulmamalı.
     *
     * Aynı isteği her seferinde farklı bir `email` ile göndermek sınırı
     * aşmamalı: sınır bekleyen kullanıcının kimliğinden kuruluyor.
     */
    public function test_a_junk_email_in_the_body_does_not_mint_a_fresh_bucket(): void
    {
        $user = $this->userWithTwoFactor();
        $this->startChallenge($user);

        $lastStatus = null;

        for ($i = 0; $i < 8; $i++) {
            $lastStatus = $this->post(route('login.two-factor.verify', ['locale' => 'tr']), [
                'code'  => '000000',
                'email' => "kova-kacirma-{$i}@example.com",
            ])->getStatusCode();
        }

        // Sekiz denemeden sonra ya sınıra takılmış (429) ya da bekleme düşmüş
        // olmalı; ikisi de saldırganın önünü kesiyor.
        $this->assertNotNull($lastStatus);
        $this->assertContains($lastStatus, [302, 429]);
        $this->assertNull(session('two_factor.pending'), 'Bekleme durumu hâlâ ayakta');
    }

    /**
     * Yanlış kodun bir bedeli olmalı.
     */
    public function test_the_pending_state_is_dropped_after_repeated_wrong_codes(): void
    {
        $user = $this->userWithTwoFactor();
        $this->startChallenge($user);

        $this->assertNotNull(session('two_factor.pending'), 'Bekleme kurulmadı');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.two-factor.verify', ['locale' => 'tr']), ['code' => '000000']);
        }

        $this->assertNull(session('two_factor.pending'));
        $this->assertGuest();
    }

    /**
     * Doğru kod hâlâ girişi tamamlamalı — kapı meşru kullanıcıya kapanmadı.
     */
    public function test_the_right_code_still_signs_in(): void
    {
        $user = $this->userWithTwoFactor();
        $this->startChallenge($user);

        $this->post(route('login.two-factor.verify', ['locale' => 'tr']), [
            'code' => $this->currentCode(),
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }
}
