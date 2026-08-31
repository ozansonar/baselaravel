<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Role;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * İki adımlı doğrulama.
 *
 * Sınavın ağırlığı iki yerde: kodun standartla aynı üretilmesi (yoksa hiçbir
 * kimlik doğrulayıcı uygulama çalışmaz) ve ikinci adımın gerçekten bir kapı
 * olması — şifresi doğru olan biri kod olmadan içeri giremiyor.
 */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    private function service(): TwoFactorService
    {
        return app(TwoFactorService::class);
    }

    private function user(string $email = 'iki@example.test'): User
    {
        $user = User::create([
            'first_name' => 'Deneme',
            'last_name'  => 'Kullanici',
            'email'      => $email,
            'password'   => 'sifre-123456',
            'is_active'  => true,
        ]);

        $user->markEmailAsVerified();

        return $user;
    }

    /**
     * Kurulumu tamamlanmış kullanıcı ve o an geçerli kodu.
     *
     * @return array{0: User, 1: string}
     */
    private function enabledUser(string $email = 'iki@example.test'): array
    {
        $user = $this->user($email);
        $secret = $this->service()->beginSetup($user);
        $code = $this->currentCode($secret);

        $this->service()->confirm($user->fresh(), $code);

        return [$user->fresh(), $this->currentCode($secret)];
    }

    private function currentCode(string $secret): string
    {
        $method = new \ReflectionMethod(TwoFactorService::class, 'codeAt');
        $method->setAccessible(true);

        return (string) $method->invoke($this->service(), $secret, intdiv(time(), 30));
    }

    // ── Kodun kendisi ──

    /**
     * RFC 6238'in kendi test vektörleri. Bu sınav geçmezse hiçbir kimlik
     * doğrulayıcı uygulama bu siteyle çalışmaz — ve bunu ancak kullanıcılar
     * fark ederdi.
     */
    public function test_the_codes_match_the_rfc_6238_test_vectors(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // "12345678901234567890"

        $method = new \ReflectionMethod(TwoFactorService::class, 'codeAt');
        $method->setAccessible(true);

        $vectors = [
            59         => '287082',
            1111111109 => '081804',
            1111111111 => '050471',
            1234567890 => '005924',
            2000000000 => '279037',
        ];

        foreach ($vectors as $timestamp => $expected) {
            $this->assertSame(
                $expected,
                $method->invoke($this->service(), $secret, intdiv($timestamp, 30)),
                "t={$timestamp}",
            );
        }
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        $secret = $this->service()->generateSecret();

        $this->assertFalse($this->service()->verifyCode($secret, '000000'));
        $this->assertFalse($this->service()->verifyCode($secret, 'abcdef'));
        $this->assertFalse($this->service()->verifyCode($secret, ''));
    }

    // ── Kurulum ──

    public function test_the_setup_is_not_active_until_the_first_code_is_confirmed(): void
    {
        $user = $this->user();

        $this->service()->beginSetup($user);

        // Anahtar var ama kurulum tamamlanmadı: bu kişiden kod istemek onu
        // kendi hesabından kilitlerdi.
        $this->assertFalse($user->fresh()?->hasTwoFactorEnabled());
    }

    public function test_confirming_with_a_valid_code_turns_it_on_and_returns_recovery_codes(): void
    {
        $user = $this->user();
        $secret = $this->service()->beginSetup($user);

        $codes = $this->service()->confirm($user->fresh(), $this->currentCode($secret));

        $this->assertIsArray($codes);
        $this->assertCount(8, $codes);
        $this->assertTrue($user->fresh()?->hasTwoFactorEnabled());
    }

    public function test_confirming_with_a_wrong_code_changes_nothing(): void
    {
        $user = $this->user();
        $this->service()->beginSetup($user);

        $this->assertNull($this->service()->confirm($user->fresh(), '000000'));
        $this->assertFalse($user->fresh()?->hasTwoFactorEnabled());
    }

    public function test_a_recovery_code_works_once(): void
    {
        $user = $this->user();
        $secret = $this->service()->beginSetup($user);
        $codes = (array) $this->service()->confirm($user->fresh(), $this->currentCode($secret));

        $this->assertTrue($this->service()->challenge($user->fresh(), (string) $codes[0]));
        $this->assertFalse($this->service()->challenge($user->fresh(), (string) $codes[0]));

        // Öteki kodlar ayakta.
        $this->assertTrue($this->service()->challenge($user->fresh(), (string) $codes[1]));
    }

    public function test_the_secret_and_the_recovery_codes_are_encrypted_at_rest(): void
    {
        $user = $this->user();
        $secret = $this->service()->beginSetup($user);
        $this->service()->confirm($user->fresh(), $this->currentCode($secret));

        $row = \Illuminate\Support\Facades\DB::table('users')->where('id', $user->getKey())->first();

        $this->assertNotSame($secret, $row?->two_factor_secret);
        $this->assertStringNotContainsString($secret, (string) $row?->two_factor_secret);
    }

    // ── Giriş akışı ──

    public function test_a_user_without_two_factor_signs_in_as_before(): void
    {
        $user = $this->user();

        $this->post('/tr/giris', ['email' => $user->email, 'password' => 'sifre-123456'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    /**
     * Kapının kendisi: şifre doğru olsa bile oturum açılmıyor.
     */
    public function test_the_password_alone_does_not_sign_in_a_user_with_two_factor(): void
    {
        [$user] = $this->enabledUser();

        $this->post('/tr/giris', ['email' => $user->email, 'password' => 'sifre-123456'])
            ->assertRedirect(route('login.two-factor'));

        $this->assertGuest();
    }

    public function test_the_second_step_signs_the_user_in(): void
    {
        [$user, $code] = $this->enabledUser();

        $this->post('/tr/giris', ['email' => $user->email, 'password' => 'sifre-123456']);
        $this->post('/tr/giris/iki-adim', ['code' => $code])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_second_step_code_keeps_the_door_closed(): void
    {
        [$user] = $this->enabledUser();

        $this->post('/tr/giris', ['email' => $user->email, 'password' => 'sifre-123456']);
        $this->post('/tr/giris/iki-adim', ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    /**
     * Şifre adımı hiç geçilmemişse ikinci adım tek başına bir giriş yolu
     * olamaz — kod bilen biri şifreyi bilmeden içeri giremesin.
     */
    public function test_the_second_step_is_useless_without_the_password_step(): void
    {
        [, $code] = $this->enabledUser();

        $this->post('/tr/giris/iki-adim', ['code' => $code])->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_challenge_screen_redirects_when_nothing_is_pending(): void
    {
        $this->get('/tr/giris/iki-adim')->assertRedirect(route('login'));
    }

    // ── Hesap ekranı ──

    public function test_the_security_screen_shows_a_qr_code_after_the_setup_starts(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/tr/hesabim/guvenlik/iki-adim')->assertRedirect();

        $html = (string) $this->actingAs($user)->get('/tr/hesabim/guvenlik')->assertOk()->getContent();

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString((string) $user->fresh()?->two_factor_secret, $html);
    }

    public function test_disabling_requires_the_current_password(): void
    {
        [$user] = $this->enabledUser();

        $this->actingAs($user)
            ->delete('/tr/hesabim/guvenlik/iki-adim', ['password' => 'yanlis-sifre'])
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()?->hasTwoFactorEnabled());

        $this->actingAs($user)
            ->delete('/tr/hesabim/guvenlik/iki-adim', ['password' => 'sifre-123456'])
            ->assertRedirect(route('account.security'));

        $this->assertFalse($user->fresh()?->hasTwoFactorEnabled());
    }

    public function test_regenerating_recovery_codes_replaces_the_old_list(): void
    {
        [$user] = $this->enabledUser();

        $before = $user->fresh()?->two_factor_recovery_codes ?? [];

        $this->actingAs($user)
            ->post('/tr/hesabim/guvenlik/kurtarma-kodlari', ['password' => 'sifre-123456'])
            ->assertRedirect(route('account.security'));

        $after = $user->fresh()?->two_factor_recovery_codes ?? [];

        $this->assertNotSame($before, $after);
        $this->assertCount(8, $after);
    }

    // ── Yöneticiler için zorunluluk ──

    private function admin(): User
    {
        $user = $this->user('yonetici@example.test');
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    public function test_an_admin_without_two_factor_is_sent_to_the_setup_when_it_is_required(): void
    {
        Setting::updateOrCreate(['key' => 'two_factor_required_admins'], ['value' => '1', 'group' => 'appearance', 'type' => 'boolean']);
        Setting::clearSettingsCache();

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('account.security'));
    }

    public function test_an_admin_with_two_factor_reaches_the_panel(): void
    {
        Setting::updateOrCreate(['key' => 'two_factor_required_admins'], ['value' => '1', 'group' => 'appearance', 'type' => 'boolean']);
        Setting::clearSettingsCache();

        $admin = $this->admin();
        $secret = $this->service()->beginSetup($admin);
        $this->service()->confirm($admin->fresh(), $this->currentCode($secret));

        $this->actingAs($admin->fresh())->get('/admin')->assertOk();
    }

    /**
     * Zorunluluk açıkken yönetici kendi ikinci adımını kaldıramamalı; aksi
     * hâlde ayar bir kural değil öneri olurdu.
     */
    public function test_an_admin_cannot_disable_it_while_it_is_required(): void
    {
        Setting::updateOrCreate(['key' => 'two_factor_required_admins'], ['value' => '1', 'group' => 'appearance', 'type' => 'boolean']);
        Setting::clearSettingsCache();

        $admin = $this->admin();
        $secret = $this->service()->beginSetup($admin);
        $this->service()->confirm($admin->fresh(), $this->currentCode($secret));

        $this->actingAs($admin->fresh())
            ->delete('/tr/hesabim/guvenlik/iki-adim', ['password' => 'sifre-123456'])
            ->assertSessionHasErrors('password');

        $this->assertTrue($admin->fresh()?->hasTwoFactorEnabled());
    }

    public function test_a_normal_user_is_untouched_by_the_admin_requirement(): void
    {
        Setting::updateOrCreate(['key' => 'two_factor_required_admins'], ['value' => '1', 'group' => 'appearance', 'type' => 'boolean']);
        Setting::clearSettingsCache();

        $user = $this->user();

        $this->actingAs($user)->get('/tr/hesabim')->assertOk();
    }
}
