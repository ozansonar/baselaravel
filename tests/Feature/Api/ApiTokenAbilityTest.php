<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\TokenAbility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jeton yetkileri.
 *
 * Varsayılan jeton `*` taşıyor — mobil uygulama hesabın tamamını yönetiyor ve
 * daraltmanın anlamı yok. Yetkiler, jetonun bir uygulamaya değil bir
 * entegrasyona verildiği durum için: bilgi ekranı, rapor aracı, üçüncü taraf
 * istemci. Böyle bir yere tam yetkili jeton vermek, onu ele geçiren birine
 * hesabın tamamını vermek demek.
 *
 * Buradaki en kritik sınama, parametrenin yalnızca DARALTABİLDİĞİ: bir yetki
 * isteği hiçbir koşulda `*` üretememeli, yoksa parametre bir yetki yükseltme
 * yüzeyi olurdu.
 */
class ApiTokenAbilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function asToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    /**
     * @param array<int, string> $abilities
     */
    private function signIn(User $user, array $abilities = []): string
    {
        $this->app['auth']->forgetGuards();

        $payload = ['email' => $user->email, 'password' => 'Gizli*12345'];

        if ($abilities !== []) {
            $payload['abilities'] = $abilities;
        }

        return (string) $this->postJson('/api/v1/auth/login', $payload)->json('data.token');
    }

    private function verifiedUser(): User
    {
        return User::factory()->create([
            'password'          => 'Gizli*12345',
            'email_verified_at' => now(),
        ]);
    }

    public function test_a_token_is_fully_powered_by_default(): void
    {
        $user = $this->verifiedUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Gizli*12345',
        ])->assertOk();

        $response->assertJsonPath('data.abilities', ['*']);

        $token = $response->json('data.token');

        // Tam yetkili jeton her kapıdan geçiyor.
        $this->asToken($token)->getJson('/api/v1/auth/me')->assertOk();
        $this->asToken($token)->getJson('/api/v1/auth/devices')->assertOk();
        $this->asToken($token)->putJson('/api/v1/account/profile', [
            'first_name' => 'Ozan', 'last_name' => 'Sonar', 'email' => $user->email,
        ])->assertOk();
    }

    public function test_a_narrowed_token_can_only_do_what_it_asked_for(): void
    {
        $user = $this->verifiedUser();

        $token = $this->signIn($user, [TokenAbility::ProfileRead->value]);

        $this->asToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $this->asToken($token)
            ->putJson('/api/v1/account/profile', [
                'first_name' => 'Ozan', 'last_name' => 'Sonar', 'email' => $user->email,
            ])
            ->assertForbidden()
            ->assertJsonPath('errors.code.0', 'missing_ability')
            ->assertJsonPath('errors.abilities.0', 'profile:write');

        $this->asToken($token)->getJson('/api/v1/auth/devices')->assertForbidden();
    }

    /**
     * Parametre bir yetki yükseltme yüzeyi olmamalı.
     *
     * Tanınmayan her değer —`*` dahil— doğrulamada reddediliyor: sessizce
     * yok sayılsaydı istemci istediğini aldığını sanır, reddedilince ne
     * isteyebileceğini öğrenir.
     */
    public function test_an_unrecognised_ability_is_refused_outright(): void
    {
        $user = $this->verifiedUser();

        foreach (['*', 'admin:everything', 'profile:delete'] as $bogus) {
            $this->app['auth']->forgetGuards();

            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email, 'password' => 'Gizli*12345', 'abilities' => [$bogus],
            ])
                ->assertStatus(422)
                ->assertJsonStructure(['errors' => ['abilities.0']]);
        }

        // Hiçbiri jeton üretmedi.
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_narrowed_token_can_always_sign_itself_out(): void
    {
        $user = $this->verifiedUser();

        // Cihaz yönetimi yetkisi olmayan bir jeton bile kendini kapatabilmeli,
        // yoksa ele geçen dar yetkili bir jeton iptal edilemez.
        $token = $this->signIn($user, [TokenAbility::ProfileRead->value]);

        $this->asToken($token)->getJson('/api/v1/auth/devices')->assertForbidden();
        $this->asToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->asToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_me_reports_what_the_token_may_do(): void
    {
        $user = $this->verifiedUser();

        $token = $this->signIn($user, [
            TokenAbility::ProfileRead->value,
            TokenAbility::DevicesManage->value,
        ]);

        $this->asToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('meta.abilities', ['profile:read', 'devices:manage']);
    }

    public function test_registration_can_ask_for_a_narrowed_token_too(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name'            => 'Ozan',
            'last_name'             => 'Sonar',
            'email'                 => 'dar@ornek.com',
            'password'              => 'Gizli*12345',
            'password_confirmation' => 'Gizli*12345',
            'abilities'             => [TokenAbility::ProfileRead->value],
        ])->assertCreated();

        $response->assertJsonPath('data.abilities', ['profile:read']);
    }

    /**
     * Çerçevenin iç metni ziyaretçiye ulaşmamalı.
     *
     * Laravel bazı istisnaları bizim kapanışımız çalışmadan önce HTTP
     * istisnasına çeviriyor ve aslını `getPrevious()` içinde taşıyor. Sarmalayana
     * bakıp metnini olduğu gibi yansıtan bir yanıt, buradaki gibi İngilizce
     * sabit bir cümleyi ("Invalid ability provided.") ziyaretçiye gösterirdi —
     * daha kötüsü, başka istisnalarda sınıf adı ya da sorgu taşıyabilirdi.
     */
    public function test_the_frameworks_own_message_never_reaches_the_client(): void
    {
        $user = $this->verifiedUser();
        $token = $this->signIn($user, [TokenAbility::ProfileRead->value]);

        foreach (['tr' => 'tr', 'en' => 'en'] as $header => $locale) {
            $this->app['auth']->forgetGuards();

            $message = $this->withToken($token)
                ->withHeader('Accept-Language', $header)
                ->getJson('/api/v1/auth/devices')
                ->assertForbidden()
                ->json('message');

            $this->assertSame(__('api.auth.missing_ability', [], $locale), $message);
            $this->assertStringNotContainsString('Invalid ability provided', (string) $message);
        }
    }

    /**
     * Her yetki bir uca bağlı olmalı; tanımlanıp hiçbir yerde kullanılmayan
     * yetki, istemciye verilebilen ama hiçbir işe yaramayan bir söz olurdu.
     */
    public function test_every_declared_ability_guards_a_route(): void
    {
        $guarded = collect(app('router')->getRoutes())
            ->flatMap(fn ($route): array => $route->gatherMiddleware())
            ->filter(fn ($m): bool => is_string($m) && str_starts_with($m, 'abilities:'))
            ->flatMap(fn (string $m): array => explode(',', substr($m, strlen('abilities:'))))
            ->unique()
            ->values()
            ->all();

        foreach (TokenAbility::values() as $ability) {
            $this->assertContains(
                $ability,
                $guarded,
                "'{$ability}' yetkisi hiçbir ucu korumuyor — verilebilen ama işe yaramayan bir söz.",
            );
        }
    }
}
