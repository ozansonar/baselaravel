<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * API girişinde ikinci adım.
 *
 * Mobil uygulama için asıl soru şu: şifre doğruyken sunucu ne söylüyor?
 * "Giriş başarısız" deseydi uygulama kişiyi şifresini yanlış girmiş sayıp
 * geri çevirirdi. Bu yüzden yanıt ayrı bir kod taşıyor ve jeton, kod
 * doğrulanana kadar hiç üretilmiyor.
 */
class ApiTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        RateLimiter::clear('api-login');
    }

    private function service(): TwoFactorService
    {
        return app(TwoFactorService::class);
    }

    private function currentCode(string $secret): string
    {
        $method = new \ReflectionMethod(TwoFactorService::class, 'codeAt');
        $method->setAccessible(true);

        return (string) $method->invoke($this->service(), $secret, intdiv(time(), 30));
    }

    /**
     * @return array{0: User, 1: string, 2: list<string>}
     */
    private function enabledUser(): array
    {
        $user = User::create([
            'first_name' => 'Ozan',
            'last_name'  => 'Sonar',
            'email'      => 'mobil@ornek.com',
            'password'   => 'Gizli*12345',
            'is_active'  => true,
        ]);
        $user->markEmailAsVerified();

        $secret = $this->service()->beginSetup($user);
        $codes = (array) $this->service()->confirm($user->fresh(), $this->currentCode($secret));

        return [$user->fresh(), $secret, array_values($codes)];
    }

    public function test_login_without_a_code_asks_for_the_second_step(): void
    {
        [$user] = $this->enabledUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Gizli*12345',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('errors.code.0', 'two_factor_required');

        // Jeton üretilmedi: "al ama kullanma" diye bir kapı olamaz.
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_with_a_valid_code_returns_a_token(): void
    {
        [$user, $secret] = $this->enabledUser();

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Gizli*12345',
            'code'     => $this->currentCode($secret),
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_a_recovery_code_also_opens_the_door_and_is_then_spent(): void
    {
        [$user, , $codes] = $this->enabledUser();

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Gizli*12345',
            'code'     => $codes[0],
        ])->assertOk();

        RateLimiter::clear('api-login');

        // Aynı kurtarma kodu ikinci kez geçmemeli.
        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Gizli*12345',
            'code'     => $codes[0],
        ])->assertStatus(403);
    }

    public function test_a_wrong_code_does_not_issue_a_token(): void
    {
        [$user] = $this->enabledUser();

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Gizli*12345',
            'code'     => '000000',
        ])->assertStatus(403);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_wrong_password_still_reports_a_failed_login_not_a_second_step(): void
    {
        [$user] = $this->enabledUser();

        // Sıra önemli: şifre yanlışsa ikinci adımın varlığı bile
        // söylenmemeli, yoksa yanıt "bu hesap var" bilgisini sızdırır.
        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'yanlis-sifre',
        ])->assertStatus(401);
    }

    public function test_me_reports_whether_two_factor_is_on(): void
    {
        [$user, $secret] = $this->enabledUser();

        $token = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Gizli*12345',
            'code'     => $this->currentCode($secret),
        ])->json('data.token');

        $this->app['auth']->forgetGuards();

        $this->withToken((string) $token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', true);
    }

    /**
     * Anahtar ve kurtarma kodları hiçbir yanıtta görünmemeli: ikisi de tek
     * başına ikinci adımı geçmeye yetiyor.
     */
    public function test_the_secret_never_leaves_the_server(): void
    {
        [$user, $secret] = $this->enabledUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Gizli*12345',
            'code'     => $this->currentCode($secret),
        ]);

        $body = (string) $response->getContent();

        $this->assertStringNotContainsString($secret, $body);
        $this->assertStringNotContainsString('two_factor_secret', $body);
        $this->assertStringNotContainsString('recovery', $body);
    }
}
