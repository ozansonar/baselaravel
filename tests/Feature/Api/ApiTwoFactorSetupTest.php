<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\TokenAbility;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * API'den iki adımlı doğrulama kurulumu.
 *
 * Girişin ikinci adımı ({@see ApiTwoFactorTest}) API'de zaten vardı, ama
 * **kurulum** yalnız web'deydi: yalnız mobil uygulamadan giren bir kullanıcı
 * 2FA'yı hiç açamıyordu. Bu sınıf o boşluğun kapandığını sınıyor.
 *
 * Sınavların çoğu "açılıyor mu"dan çok **açılmaması gereken durumlara**
 * bakıyor: yanlış kodla açılmamalı, kurulum başlatılmadan onaylanmamalı,
 * şifresiz kapatılmamalı, zorunluluk açıkken yönetici tarafından
 * kaldırılmamalı.
 */
class ApiTwoFactorSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        RateLimiter::clear('api-password');
    }

    private function service(): TwoFactorService
    {
        return app(TwoFactorService::class);
    }

    /**
     * Şu anki geçerli TOTP kodu.
     *
     * Servisin kendi üretecinden okunuyor: testin kendi RFC 6238 uygulamasını
     * yazması, sınananla aynı hatayı yapma ihtimali demekti.
     */
    private function currentCode(string $secret): string
    {
        $method = new \ReflectionMethod(TwoFactorService::class, 'codeAt');
        $method->setAccessible(true);

        return (string) $method->invoke($this->service(), $secret, intdiv(time(), 30));
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

    /**
     * @param list<string>|null $abilities
     */
    private function tokenFor(User $user, ?array $abilities = null): string
    {
        $abilities ??= array_map(static fn (TokenAbility $a): string => $a->value, TokenAbility::cases());

        $this->app['auth']->forgetGuards();

        return $user->createToken('test', $abilities)->plainTextToken;
    }

    /* ==================== Durum ==================== */

    public function test_the_status_endpoint_describes_a_fresh_account(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/account/two-factor')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.pending', false)
            ->assertJsonPath('data.recovery_codes_remaining', 0)
            ->assertJsonPath('data.required', false);
    }

    /**
     * Yarıda kalmış kurulum ayrı bir durum: uygulama bunu bilmezse kullanıcıyı
     * baştan başlatır ve okuttuğu QR geçersiz olur.
     */
    public function test_a_half_finished_setup_is_reported_as_pending(): void
    {
        $user = $this->user();
        $this->service()->beginSetup($user);

        $this->withToken($this->tokenFor($user->fresh()))
            ->getJson('/api/v1/account/two-factor')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.pending', true);
    }

    /* ==================== Kurulum ==================== */

    public function test_starting_the_setup_returns_the_three_shapes_the_client_needs(): void
    {
        $user = $this->user();

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/account/two-factor')
            ->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'otpauth_uri', 'qr_svg']]);

        $secret = $response->json('data.secret');

        $this->assertSame(32, strlen((string) $secret));
        $this->assertStringStartsWith('otpauth://totp/', (string) $response->json('data.otpauth_uri'));
        $this->assertStringContainsString('<svg', (string) $response->json('data.qr_svg'));

        // Anahtar üretildi ama doğrulama AÇILMADI: açılması ilk doğru kodun
        // girilmesine bağlı, yoksa QR'ı okutamayan kişi kilitlenirdi.
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_the_first_correct_code_turns_it_on_and_hands_over_the_recovery_codes(): void
    {
        $user = $this->user();
        $secret = $this->service()->beginSetup($user);

        $response = $this->withToken($this->tokenFor($user->fresh()))
            ->postJson('/api/v1/account/two-factor/confirm', ['code' => $this->currentCode($secret)])
            ->assertOk();

        $codes = $response->json('data.recovery_codes');

        $this->assertIsArray($codes);
        $this->assertCount(8, $codes);
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_a_wrong_code_changes_nothing(): void
    {
        $user = $this->user();
        $this->service()->beginSetup($user);

        $this->withToken($this->tokenFor($user->fresh()))
            ->postJson('/api/v1/account/two-factor/confirm', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_confirming_without_starting_is_refused(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/account/two-factor/confirm', ['code' => '123456'])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_starting_again_while_already_on_is_refused(): void
    {
        [$user] = $this->enabled();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/account/two-factor')
            ->assertStatus(409);
    }

    /* ==================== Kapatma ==================== */

    public function test_turning_it_off_needs_the_password(): void
    {
        [$user] = $this->enabled();

        $this->withToken($this->tokenFor($user))
            ->deleteJson('/api/v1/account/two-factor', ['password' => 'yanlis-sifre'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_the_right_password_turns_it_off(): void
    {
        [$user] = $this->enabled();

        $this->withToken($this->tokenFor($user))
            ->deleteJson('/api/v1/account/two-factor', ['password' => 'Gizli*12345'])
            ->assertOk();

        $user->refresh();

        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
    }

    public function test_turning_off_something_that_is_not_on_is_refused(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))
            ->deleteJson('/api/v1/account/two-factor', ['password' => 'Gizli*12345'])
            ->assertStatus(409);
    }

    /**
     * Zorunluluk açıkken yönetici kendi ikinci adımını kaldıramıyor —
     * kaldırabilseydi ayar bir kural değil, bir öneri olurdu. Web tarafındaki
     * kuralın aynısı; iki yüz ayrışmamalı.
     */
    public function test_an_admin_cannot_turn_it_off_while_it_is_required(): void
    {
        [$user] = $this->enabled();
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'admin')->firstOrFail()->id]);

        Setting::updateOrCreate(['key' => 'two_factor_required_admins'], ['value' => '1']);
        Setting::clearSettingsCache();

        $this->withToken($this->tokenFor($user->fresh()))
            ->deleteJson('/api/v1/account/two-factor', ['password' => 'Gizli*12345'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_the_status_endpoint_says_when_it_is_required(): void
    {
        [$user] = $this->enabled();
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'admin')->firstOrFail()->id]);

        Setting::updateOrCreate(['key' => 'two_factor_required_admins'], ['value' => '1']);
        Setting::clearSettingsCache();

        $this->withToken($this->tokenFor($user->fresh()))
            ->getJson('/api/v1/account/two-factor')
            ->assertOk()
            ->assertJsonPath('data.required', true);
    }

    /* ==================== Kurtarma kodları ==================== */

    public function test_recovery_codes_can_be_regenerated_with_the_password(): void
    {
        [$user, , $codes] = $this->enabled();

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/account/two-factor/recovery-codes', ['password' => 'Gizli*12345'])
            ->assertOk();

        $fresh = $response->json('data.recovery_codes');

        $this->assertCount(8, $fresh);
        // Eski liste geçersiz olmalı: yenilemenin bütün anlamı bu.
        $this->assertNotEquals($codes, $fresh);
    }

    public function test_regenerating_needs_the_password(): void
    {
        [$user, , $codes] = $this->enabled();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/account/two-factor/recovery-codes', ['password' => 'yanlis'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertSame($codes, $user->fresh()->two_factor_recovery_codes);
    }

    /**
     * Kalan kod sayısı durumda görünmeli ki kullanıcı tükenmeden yenilesin.
     */
    public function test_the_status_endpoint_counts_the_remaining_recovery_codes(): void
    {
        [$user] = $this->enabled();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/account/two-factor')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.recovery_codes_remaining', 8);
    }

    /* ==================== Yetki ve erişim ==================== */

    public function test_every_endpoint_needs_a_token(): void
    {
        $this->getJson('/api/v1/account/two-factor')->assertUnauthorized();
        $this->postJson('/api/v1/account/two-factor')->assertUnauthorized();
        $this->postJson('/api/v1/account/two-factor/confirm', ['code' => '123456'])->assertUnauthorized();
        $this->deleteJson('/api/v1/account/two-factor', ['password' => 'x'])->assertUnauthorized();
        $this->postJson('/api/v1/account/two-factor/recovery-codes', ['password' => 'x'])->assertUnauthorized();
    }

    /**
     * Salt okunur jeton kurulumu değiştiremiyor: okuma yetkisiyle verilen bir
     * jeton, hesabın ikinci adımını açıp kapatabilseydi yetki ayrımı
     * anlamsız olurdu.
     */
    public function test_a_read_only_token_cannot_change_the_setup(): void
    {
        $user = $this->user();
        $token = $this->tokenFor($user, [TokenAbility::ProfileRead->value]);

        $this->withToken($token)->getJson('/api/v1/account/two-factor')->assertOk();
        $this->withToken($token)->postJson('/api/v1/account/two-factor')->assertForbidden();
        $this->withToken($token)
            ->deleteJson('/api/v1/account/two-factor', ['password' => 'Gizli*12345'])
            ->assertForbidden();
    }

    /**
     * Anahtar hiçbir okuma ucunda geçmiyor: yalnız kurulumu başlatan istek
     * onu görüyor.
     */
    public function test_the_secret_never_appears_in_the_status_endpoint(): void
    {
        [$user, $secret] = $this->enabled();

        $body = (string) $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/account/two-factor')
            ->getContent();

        $this->assertStringNotContainsString($secret, $body);
    }

    /**
     * Kurulumu tamamlanmış bir kullanıcı ve anahtarı.
     *
     * @return array{0: User, 1: string, 2: list<string>}
     */
    private function enabled(): array
    {
        $user = $this->user();
        $secret = $this->service()->beginSetup($user);
        $codes = (array) $this->service()->confirm($user->fresh(), $this->currentCode($secret));

        return [$user->fresh(), $secret, array_values($codes)];
    }
}
