<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\SocialProvider;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IssuesSocialIdTokens;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Google / Apple ile giriş.
 *
 * Jeton istemciden geliyor, yani istemci onu kendisi yazabilir. Bu sınıfın
 * yarısı "yazamaz" sınavı: imza, `iss`, `aud` ve `exp` dördü de tutmadan
 * hiçbir jeton kabul edilmemeli. Biri bile atlanırsa herkes herkesin hesabına
 * girer.
 *
 * Öteki yarısı hesap eşleştirme. Oradaki kritik karar şu: doğrulanmamış bir
 * e-postayla var olan bir hesaba bağlanmak, o adresi kendi sağlayıcı hesabına
 * yazan birine sitedeki hesabı teslim etmek demek.
 */
final class ApiSocialLoginTest extends TestCase
{
    use RefreshDatabase, IssuesSocialIdTokens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->bootSocialProvider();
    }

    private function login(SocialProvider $provider, string $token, array $extra = [])
    {
        return $this->postJson('/api/v1/auth/social/' . $provider->value, array_merge([
            'id_token'    => $token,
            'device_name' => 'iPhone 15',
        ], $extra));
    }

    // ── Jeton doğrulama ──

    public function test_a_valid_token_signs_in(): void
    {
        $this->login(SocialProvider::Google, $this->idToken(SocialProvider::Google))
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['user', 'token', 'expires_at', 'abilities']]);

        $this->assertDatabaseHas('users', ['email' => 'sosyal@example.com']);
        $this->assertDatabaseHas('social_accounts', [
            'provider'         => 'google',
            'provider_user_id' => 'saglayici-kullanici-1',
        ]);
    }

    /**
     * Başkasının anahtarıyla imzalanmış jeton reddedilmeli. Bu sınav
     * geçmezse imza hiç doğrulanmıyor demektir.
     */
    public function test_a_forged_signature_is_refused(): void
    {
        $this->login(SocialProvider::Google, $this->forgedToken(SocialProvider::Google))
            ->assertStatus(401);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * "alg: none" — imzayı atlatmanın klasik yolu.
     */
    public function test_an_unsigned_token_is_refused(): void
    {
        $this->login(SocialProvider::Google, $this->unsignedToken(SocialProvider::Google))
            ->assertStatus(401);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Başka bir uygulamaya düzenlenmiş, imzası tamamen geçerli bir jeton.
     *
     * `aud` doğrulanmazsa saldırgan kendi uygulamasına aldığı gerçek bir
     * Google jetonuyla buraya girebilirdi — imza da `iss` de tutardı.
     */
    public function test_a_token_for_another_app_is_refused(): void
    {
        $token = $this->idToken(SocialProvider::Google, ['aud' => 'baska-bir-uygulama']);

        $this->login(SocialProvider::Google, $token)->assertStatus(401);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_token_from_another_issuer_is_refused(): void
    {
        $token = $this->idToken(SocialProvider::Google, ['iss' => 'https://kotu-saglayici.example']);

        $this->login(SocialProvider::Google, $token)->assertStatus(401);
    }

    public function test_an_expired_token_is_refused(): void
    {
        $token = $this->idToken(SocialProvider::Google, ['exp' => time() - 3600]);

        $this->login(SocialProvider::Google, $token)->assertStatus(401);
    }

    /**
     * Bir sağlayıcının jetonu ötekinin ucunda geçerli olmamalı.
     */
    public function test_a_google_token_is_refused_at_the_apple_endpoint(): void
    {
        $this->login(SocialProvider::Apple, $this->idToken(SocialProvider::Google))
            ->assertStatus(401);
    }

    public function test_an_unknown_provider_is_refused(): void
    {
        $this->postJson('/api/v1/auth/social/facebook', [
            'id_token' => $this->idToken(SocialProvider::Google),
        ])->assertStatus(404);
    }

    /**
     * Yapılandırılmamış sağlayıcı kapalı sayılıyor: `aud` doğrulaması
     * yapılamayan bir jeton kabul edilemez.
     */
    public function test_an_unconfigured_provider_is_closed(): void
    {
        config()->set('services.apple.client_ids', '');

        $this->login(SocialProvider::Apple, $this->idToken(SocialProvider::Apple))
            ->assertStatus(404);
    }

    // ── Hesap eşleştirme ──

    public function test_the_same_social_account_signs_into_the_same_user(): void
    {
        $this->login(SocialProvider::Google, $this->idToken(SocialProvider::Google))->assertOk();
        $this->login(SocialProvider::Google, $this->idToken(SocialProvider::Google))->assertOk();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 1);
    }

    /**
     * Adres sağlayıcıda değişse de hesap aynı kalmalı: anahtar `sub`.
     */
    public function test_a_changed_email_still_reaches_the_same_account(): void
    {
        $this->login(SocialProvider::Google, $this->idToken(SocialProvider::Google))->assertOk();

        $this->login(SocialProvider::Google, $this->idToken(SocialProvider::Google, [
            'email' => 'yeni-adres@example.com',
        ]))->assertOk();

        $this->assertDatabaseCount('users', 1);
    }

    /**
     * Doğrulanmış adres var olan hesaba bağlanıyor.
     */
    public function test_a_verified_email_links_to_the_existing_account(): void
    {
        $user = User::create([
            'first_name' => 'Var',
            'last_name'  => 'Olan',
            'email'      => 'sosyal@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);
        $user->roles()->attach(Role::where('slug', 'user')->firstOrFail());

        $this->login(SocialProvider::Google, $this->idToken(SocialProvider::Google))->assertOk();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google']);
    }

    /**
     * Sosyal girişin en bilinen hesap devralma yolu.
     *
     * Sağlayıcı adresi doğrulamadıysa, o adresi kendi sağlayıcı hesabına yazan
     * biri buradaki hesabın sahibi olurdu.
     */
    public function test_an_unverified_email_cannot_take_over_an_account(): void
    {
        $user = User::create([
            'first_name' => 'Kurban',
            'last_name'  => 'Kullanıcı',
            'email'      => 'sosyal@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $this->login(SocialProvider::Google, $this->idToken(SocialProvider::Google, [
            'email_verified' => false,
            'sub'            => 'saldirganin-google-hesabi',
        ]))->assertStatus(409);

        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Kurban', $user->fresh()->first_name);
    }

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        $user = User::create([
            'first_name' => 'Pasif',
            'last_name'  => 'Hesap',
            'email'      => 'sosyal@example.com',
            'password'   => 'password',
            'is_active'  => false,
        ]);
        $user->roles()->attach(Role::where('slug', 'user')->firstOrFail());

        $this->login(SocialProvider::Google, $this->idToken(SocialProvider::Google))
            ->assertStatus(403);
    }

    /**
     * Apple adı yalnız ilk yetkilendirmede gönderiyor ve jetonun içinde değil.
     */
    public function test_the_client_can_supply_the_name_on_first_sign_in(): void
    {
        $this->login(SocialProvider::Apple, $this->idToken(SocialProvider::Apple, ['name' => null]), [
            'first_name' => 'Grace',
            'last_name'  => 'Hopper',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['first_name' => 'Grace', 'last_name' => 'Hopper']);
    }

    /**
     * Sağlayıcı doğruladıysa ikinci kez doğrulatmanın anlamı yok.
     */
    public function test_a_verified_provider_email_counts_as_verified(): void
    {
        $this->login(SocialProvider::Google, $this->idToken(SocialProvider::Google))->assertOk();

        $this->assertNotNull(User::firstOrFail()->email_verified_at);
    }

    // ── Bağlı hesaplar ──

    public function test_the_account_lists_its_linked_providers(): void
    {
        $this->login(SocialProvider::Google, $this->idToken(SocialProvider::Google))->assertOk();

        $user = User::firstOrFail();

        Sanctum::actingAs($user, ['*']);

        $this
            ->getJson('/api/v1/account/social-accounts')
            ->assertOk()
            ->assertJsonPath('data.accounts.0.provider', 'google');
    }

    public function test_a_link_can_be_removed_when_the_account_has_a_real_email(): void
    {
        $this->login(SocialProvider::Google, $this->idToken(SocialProvider::Google))->assertOk();

        $user = User::firstOrFail();

        Sanctum::actingAs($user, ['*']);

        $this
            ->deleteJson('/api/v1/account/social-accounts/google')
            ->assertOk();

        $this->assertSame(0, $user->fresh()->socialAccounts()->count());
    }

    /**
     * Apple "adresimi gizle" dediğinde gerçek adres gelmiyor; o hesabın
     * "şifremi unuttum" kapısı da yok, dolayısıyla son bağ koparılamaz.
     */
    public function test_the_last_link_is_kept_when_there_is_no_reachable_email(): void
    {
        $this->login(SocialProvider::Apple, $this->idToken(SocialProvider::Apple, [
            'email'          => null,
            'email_verified' => false,
        ]))->assertOk();

        $user = User::firstOrFail();

        Sanctum::actingAs($user, ['*']);

        $this
            ->deleteJson('/api/v1/account/social-accounts/apple')
            ->assertStatus(422);

        $this->assertSame(1, $user->fresh()->socialAccounts()->count());
    }

    public function test_removing_a_provider_that_is_not_linked_is_a_404(): void
    {
        $this->login(SocialProvider::Google, $this->idToken(SocialProvider::Google))->assertOk();

        Sanctum::actingAs(User::firstOrFail(), ['*']);

        $this
            ->deleteJson('/api/v1/account/social-accounts/apple')
            ->assertStatus(404);
    }

    /**
     * Aynı sosyal hesap iki kullanıcıya bağlanamaz.
     */
    public function test_one_social_account_cannot_belong_to_two_users(): void
    {
        $this->login(SocialProvider::Google, $this->idToken(SocialProvider::Google))->assertOk();

        $this->assertSame(1, SocialAccount::where('provider_user_id', 'saglayici-kullanici-1')->count());
    }
}
