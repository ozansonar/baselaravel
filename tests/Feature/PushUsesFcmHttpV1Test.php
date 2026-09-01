<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PushPlatform;
use App\Models\PushToken;
use App\Models\User;
use App\Services\Push\FcmAccessToken;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresFcm;
use Tests\TestCase;

/**
 * Bildirimler FCM'in güncel arayüzüne gidiyor.
 *
 * Taşıma katmanı `https://fcm.googleapis.com/fcm/send` adresine, sunucu
 * anahtarıyla ve `{"to": "..."}` gövdesiyle yazıyordu — Google o API'yi
 * Haziran 2024'te kapattı. Panel ekranı, jeton kaydı, kuyruk ve ölü jeton
 * temizliği çalışıyordu, yalnız gönderim sessizce hiçbir yere gitmiyordu:
 * varsayılan sürücü `null` olduğu için kimse fark etmiyordu.
 *
 * Buradaki sınavlar sahte bir servis hesabıyla koşuyor; gerçek bir Firebase
 * projesi gerekmiyor. İmza gerçekten kuruluyor, yalnız ağ sahte.
 */
final class PushUsesFcmHttpV1Test extends TestCase
{
    use RefreshDatabase, ConfiguresFcm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureFcm();
    }

    protected function tearDown(): void
    {
        $this->forgetFcmCredentials();

        parent::tearDown();
    }

    private function tokenFor(User $user, string $value = 'cihaz-jetonu-1'): PushToken
    {
        return app(PushNotificationService::class)
            ->register($user, $value, PushPlatform::Android, 'Test Cihazı');
    }

    private function user(): User
    {
        return User::create([
            'first_name' => 'Bildirim',
            'last_name'  => 'Alan',
            'email'      => 'bildirim@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);
    }

    // ── Adres ve gövde ──

    public function test_the_notification_goes_to_the_v1_endpoint(): void
    {
        $this->fakeFcm();

        $user = $this->user();
        $this->tokenFor($user);

        app(PushNotificationService::class)->sendToUser($user, 'Başlık', 'Gövde');

        Http::assertSent(static fn (Request $request): bool => $request->url()
            === 'https://fcm.googleapis.com/v1/projects/deneme-projesi/messages:send');

        // Kapanmış olan eski uca hiç gidilmemeli.
        Http::assertNotSent(static fn (Request $request): bool => str_contains($request->url(), '/fcm/send'));
    }

    public function test_the_body_uses_the_v1_message_shape(): void
    {
        $this->fakeFcm();

        $user = $this->user();
        $this->tokenFor($user, 'jeton-abc');

        app(PushNotificationService::class)->sendToUser($user, 'Başlık', 'Gövde');

        Http::assertSent(static function (Request $request): bool {
            if (! str_contains($request->url(), 'messages:send')) {
                return false;
            }

            $body = $request->data();

            return isset($body['message'])
                && $body['message']['token'] === 'jeton-abc'
                && $body['message']['notification']['title'] === 'Başlık'
                // Eski biçimin izi kalmamalı.
                && ! isset($body['to']);
        });
    }

    public function test_the_request_carries_the_oauth_bearer_token(): void
    {
        $this->fakeFcm();

        $user = $this->user();
        $this->tokenFor($user);

        app(PushNotificationService::class)->sendToUser($user, 'Başlık', 'Gövde');

        Http::assertSent(static function (Request $request): bool {
            if (! str_contains($request->url(), 'messages:send')) {
                return false;
            }

            return $request->hasHeader('Authorization', 'Bearer sahte-erisim-jetonu');
        });
    }

    /**
     * v1 `data` alanında yalnız dizge kabul ediyor; sayı gönderen bir çağrı
     * 400 alıyordu.
     */
    public function test_data_values_are_sent_as_strings(): void
    {
        $this->fakeFcm();

        $user = $this->user();
        $this->tokenFor($user);

        app(PushNotificationService::class)->sendToUser($user, 'Başlık', 'Gövde', [
            'post_id' => 42,
            'acik'    => true,
        ]);

        Http::assertSent(static function (Request $request): bool {
            if (! str_contains($request->url(), 'messages:send')) {
                return false;
            }

            $data = $request->data()['message']['data'] ?? [];

            return $data['post_id'] === '42' && $data['acik'] === 'true';
        });
    }

    // ── Erişim jetonu ──

    public function test_the_access_token_is_reused_across_sends(): void
    {
        $this->fakeFcm();

        $user = $this->user();
        $this->tokenFor($user);

        $service = app(PushNotificationService::class);
        $service->sendToUser($user, 'Bir', 'Gövde');
        $service->sendToUser($user, 'İki', 'Gövde');

        $tokenCalls = 0;

        Http::assertSent(static function (Request $request) use (&$tokenCalls): bool {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                $tokenCalls++;
            }

            return true;
        });

        $this->assertSame(1, $tokenCalls, 'Her gönderim için yeniden jeton alınıyor');
    }

    public function test_a_signed_assertion_is_exchanged_for_the_token(): void
    {
        $this->fakeFcm();

        $user = $this->user();
        $this->tokenFor($user);

        app(PushNotificationService::class)->sendToUser($user, 'Başlık', 'Gövde');

        Http::assertSent(static function (Request $request): bool {
            if (! str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return false;
            }

            $data = $request->data();

            if (($data['grant_type'] ?? '') !== 'urn:ietf:params:oauth:grant-type:jwt-bearer') {
                return false;
            }

            // JWT üç parçalı ve orta parçası doğru istekleri taşımalı.
            $parts = explode('.', (string) ($data['assertion'] ?? ''));

            if (count($parts) !== 3) {
                return false;
            }

            $claims = json_decode(
                (string) base64_decode(strtr($parts[1], '-_', '+/'), true),
                true,
            );

            return is_array($claims)
                && $claims['iss'] === 'push@deneme-projesi.iam.gserviceaccount.com'
                && $claims['scope'] === 'https://www.googleapis.com/auth/firebase.messaging';
        });
    }

    // ── Ölü jetonlar ──

    public function test_an_unregistered_device_is_dropped(): void
    {
        $this->fakeFcm(404, ['error' => ['status' => 'NOT_FOUND']]);

        $user = $this->user();
        $token = $this->tokenFor($user);

        app(PushNotificationService::class)->sendToUser($user, 'Başlık', 'Gövde');

        // Yumuşak silme (proje kuralı): satır duruyor ama sonraki
        // gönderimlerin kapsamı dışında.
        $this->assertSoftDeleted('push_tokens', ['id' => $token->id]);
        $this->assertSame(0, app(PushNotificationService::class)->tokensFor($user->fresh())->count());
    }

    public function test_a_malformed_token_is_dropped(): void
    {
        $this->fakeFcm(400, ['error' => ['status' => 'INVALID_ARGUMENT']]);

        $user = $this->user();
        $token = $this->tokenFor($user);

        app(PushNotificationService::class)->sendToUser($user, 'Başlık', 'Gövde');

        // Yumuşak silme (proje kuralı): satır duruyor ama sonraki
        // gönderimlerin kapsamı dışında.
        $this->assertSoftDeleted('push_tokens', ['id' => $token->id]);
        $this->assertSame(0, app(PushNotificationService::class)->tokensFor($user->fresh())->count());
    }

    /**
     * Kurulum hatası jetonun suçu değil: 403 alınca kayıtlar durmalı, yoksa
     * yanlış yapılandırılmış bir sunucu bütün cihazları siler.
     */
    public function test_a_configuration_error_does_not_delete_devices(): void
    {
        $this->fakeFcm(403, ['error' => ['status' => 'PERMISSION_DENIED']]);

        $user = $this->user();
        $token = $this->tokenFor($user);

        app(PushNotificationService::class)->sendToUser($user, 'Başlık', 'Gövde');

        $this->assertDatabaseHas('push_tokens', ['id' => $token->id]);
    }

    // ── Kurulum eksikken ──

    public function test_nothing_is_sent_without_credentials(): void
    {
        Http::fake();
        config()->set('push.fcm.credentials', '');

        $user = $this->user();
        $this->tokenFor($user);

        $result = app(PushNotificationService::class)->sendToUser($user, 'Başlık', 'Gövde');

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['skipped']);
        Http::assertNothingSent();
    }

    public function test_the_service_reports_whether_it_is_configured(): void
    {
        $this->assertTrue(app(FcmAccessToken::class)->isConfigured());

        config()->set('push.fcm.credentials', storage_path('app/olmayan-dosya.json'));

        $this->assertFalse(app(FcmAccessToken::class)->isConfigured());
    }
}
