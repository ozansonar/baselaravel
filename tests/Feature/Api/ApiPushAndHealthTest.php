<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\PushPlatform;
use App\Enums\TokenAbility;
use App\Models\PushToken;
use App\Models\Setting;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\ConfiguresFcm;
use Tests\TestCase;

/**
 * Mobil uygulamanın sunucuya ilk ve son bağlandığı iki uç: sağlık/sürüm
 * bilgisi ve bildirim adresi.
 *
 * İkisi de "uygulama mağazada, sunucu değişti" durumunun cevabı: biri eski
 * sürümü güncellemeye yönlendiriyor, öteki kullanıcıya haber ulaştırıyor.
 */
class ApiPushAndHealthTest extends TestCase
{
    use RefreshDatabase, ConfiguresFcm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        RateLimiter::clear('api');
    }

    private function user(string $email = 'push@ornek.com'): User
    {
        $user = User::create([
            'first_name' => 'Ozan', 'last_name' => 'Sonar',
            'email' => $email, 'password' => 'Gizli*12345', 'is_active' => true,
        ]);
        $user->markEmailAsVerified();

        return $user;
    }

    private function tokenFor(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return $user->createToken('test', array_map(
            fn (TokenAbility $ability): string => $ability->value,
            TokenAbility::cases(),
        ))->plainTextToken;
    }

    // ── Sağlık ve sürüm ──

    public function test_the_health_endpoint_is_open_without_a_token(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.api_version', 'v1');
    }

    /**
     * Bakım penceresinde bile açık: uygulamanın bakımı öğrenebileceği tek yer
     * burası, kapalı olsaydı bakım her istek gibi hata dönerdi.
     */
    public function test_the_health_endpoint_reports_maintenance(): void
    {
        Setting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '1', 'group' => 'appearance', 'type' => 'boolean']);
        Setting::clearSettingsCache();

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.maintenance', true);
    }

    public function test_an_old_client_is_told_to_update(): void
    {
        Setting::updateOrCreate(['key' => 'api_minimum_client_version'], ['value' => '2.0.0', 'group' => 'appearance', 'type' => 'text']);
        Setting::clearSettingsCache();

        $this->withHeader('X-Client-Version', '1.9.0')
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.update_required', true);

        $this->withHeader('X-Client-Version', '2.0.0')
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.update_required', false);
    }

    /**
     * Sürümünü bildirmeyen istemciyi "güncelle" diye çevirmek onu tamamen
     * kullanılamaz hâle getirirdi; oysa yapması gereken tek şey güncellenmek
     * ve bunu ancak uygulamayı açabilirse öğrenir.
     */
    public function test_a_client_that_reports_no_version_is_not_blocked(): void
    {
        Setting::updateOrCreate(['key' => 'api_minimum_client_version'], ['value' => '2.0.0', 'group' => 'appearance', 'type' => 'text']);
        Setting::clearSettingsCache();

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.update_required', false);
    }

    // ── Bildirim adresleri ──

    public function test_a_device_can_register_its_token(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/account/push-tokens', [
                'token'       => 'cihaz-jetonu-1',
                'platform'    => PushPlatform::Ios->value,
                'device_name' => 'iPhone 15',
            ])
            ->assertOk();

        $this->assertDatabaseHas('push_tokens', [
            'user_id'  => $user->getKey(),
            'token'    => 'cihaz-jetonu-1',
            'platform' => 'ios',
        ]);
    }

    public function test_registering_the_same_token_twice_does_not_duplicate_it(): void
    {
        $user = $this->user();
        $token = $this->tokenFor($user);

        foreach ([1, 2] as $ignored) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->postJson('/api/v1/account/push-tokens', [
                'token'    => 'ayni-jeton',
                'platform' => PushPlatform::Android->value,
            ])->assertOk();
        }

        $this->assertSame(1, PushToken::where('token', 'ayni-jeton')->count());
    }

    /**
     * Aynı telefondan başka bir hesaba girildiğinde jeton yeni sahibine
     * geçmeli; geçmezse bildirim eski kullanıcıya gider.
     */
    public function test_a_token_moves_to_the_account_that_last_registered_it(): void
    {
        $first = $this->user('birinci@ornek.com');
        $second = $this->user('ikinci@ornek.com');

        $this->withToken($this->tokenFor($first))->postJson('/api/v1/account/push-tokens', [
            'token' => 'paylasilan-cihaz', 'platform' => 'ios',
        ])->assertOk();

        $this->withToken($this->tokenFor($second))->postJson('/api/v1/account/push-tokens', [
            'token' => 'paylasilan-cihaz', 'platform' => 'ios',
        ])->assertOk();

        $this->assertDatabaseHas('push_tokens', [
            'token'   => 'paylasilan-cihaz',
            'user_id' => $second->getKey(),
        ]);
        $this->assertSame(1, PushToken::where('token', 'paylasilan-cihaz')->count());
    }

    public function test_a_device_can_forget_its_token(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))->postJson('/api/v1/account/push-tokens', [
            'token' => 'silinecek', 'platform' => 'web',
        ])->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($this->tokenFor($user))->deleteJson('/api/v1/account/push-tokens', [
            'token' => 'silinecek',
        ])->assertOk();

        $this->assertSoftDeleted('push_tokens', ['token' => 'silinecek']);
    }

    public function test_an_unknown_platform_is_refused(): void
    {
        $user = $this->user();

        $this->withToken($this->tokenFor($user))->postJson('/api/v1/account/push-tokens', [
            'token' => 'jeton', 'platform' => 'blackberry',
        ])->assertStatus(422);
    }

    /**
     * Erişimi kapatılan hesabın telefonuna bildirim gitmeye devam etmemeli.
     */
    public function test_deactivating_the_account_drops_its_push_tokens(): void
    {
        $user = $this->user();
        PushToken::create(['user_id' => $user->getKey(), 'token' => 'kapatilan', 'platform' => 'ios']);

        $user->update(['is_active' => false]);

        $this->assertDatabaseMissing('push_tokens', ['token' => 'kapatilan']);
    }

    // ── Gönderim ──

    /**
     * Taşıyıcı yapılandırılmadığında gönderim sessizce kaybolmuyor: çağıran
     * taraf "gönderilmedi" cevabını alıyor.
     */
    public function test_sending_without_a_configured_carrier_reports_it(): void
    {
        config(['push.driver' => 'null']);

        $user = $this->user();
        PushToken::create(['user_id' => $user->getKey(), 'token' => 'jeton', 'platform' => 'ios']);

        $result = app(PushNotificationService::class)->sendToUser($user, 'Başlık', 'Gövde');

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['skipped']);
    }

    public function test_sending_reaches_the_carrier_when_configured(): void
    {
        $this->configureFcm();
        $this->fakeFcm();

        $user = $this->user();
        PushToken::create(['user_id' => $user->getKey(), 'token' => 'jeton', 'platform' => 'android']);

        $result = app(PushNotificationService::class)->sendToUser($user, 'Başlık', 'Gövde');

        $this->assertSame(1, $result['sent']);
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'messages:send'));
    }

    /**
     * Sağlayıcı "bu jeton artık geçerli değil" derse kayıt siliniyor: ölü
     * jetonlar birikirse her bildirim, çoğu boşa giden yüzlerce isteğe dönüşür.
     */
    public function test_a_dead_token_is_dropped(): void
    {
        $this->configureFcm();
        $this->fakeFcm(404, ['error' => ['status' => 'NOT_FOUND']]);

        $user = $this->user();
        PushToken::create(['user_id' => $user->getKey(), 'token' => 'olu-jeton', 'platform' => 'android']);

        app(PushNotificationService::class)->sendToUser($user, 'Başlık', 'Gövde');

        $this->assertSoftDeleted('push_tokens', ['token' => 'olu-jeton']);
    }

    public function test_stale_tokens_are_pruned(): void
    {
        $user = $this->user();
        PushToken::create([
            'user_id' => $user->getKey(), 'token' => 'eski', 'platform' => 'ios',
            'last_used_at' => now()->subYear(),
        ]);
        PushToken::create([
            'user_id' => $user->getKey(), 'token' => 'taze', 'platform' => 'ios',
            'last_used_at' => now(),
        ]);

        $this->assertSame(1, app(PushNotificationService::class)->pruneStale(180));
        $this->assertDatabaseHas('push_tokens', ['token' => 'taze', 'deleted_at' => null]);
    }
}
