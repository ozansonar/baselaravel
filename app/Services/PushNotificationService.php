<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PushPlatform;
use App\Services\Push\FcmAccessToken;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mobil bildirimlerin sunucu tarafı.
 *
 * İki parçası var ve bilerek ayrılmışlar:
 *
 *   - Jeton kaydı: cihaz uygulamayı açtığında adresini bırakıyor. Bu parça
 *     hiçbir sağlayıcıya bağlı değil ve yapılandırma istemiyor.
 *   - Gönderim: jetonu bir taşıyıcıya (FCM) veriyor. Taşıyıcı
 *     yapılandırılmamışsa gönderim sessizce kaybolmuyor — log'a düşüyor ve
 *     "yapılandırılmadı" diye geri dönüyor.
 *
 * Ayrım şundan: mobil uygulama geliştirilirken jeton kaydı ilk gün gerekiyor,
 * gerçek gönderim ise mağaza hesapları açıldıktan sonra. Sunucunun ikincisini
 * beklemesi için sebep yok.
 */
final class PushNotificationService
{
    public function __construct(
        private readonly FcmAccessToken $accessToken,
    ) {}

    /**
     * Cihazın adresini kaydeder.
     *
     * Aynı jeton başka bir hesapta kayıtlıysa o hesaptan alınıyor: telefon el
     * değiştirmiş ya da aynı cihazdan başka bir hesaba girilmiş demektir ve
     * bildirim yeni sahibine gitmeli.
     */
    public function register(User $user, string $token, PushPlatform $platform, ?string $deviceName = null): PushToken
    {
        return DB::transaction(function () use ($user, $token, $platform, $deviceName): PushToken {
            $existing = PushToken::withTrashed()->where('token', $token)->first();

            if ($existing !== null) {
                $existing->restore();
                $existing->update([
                    'user_id'      => $user->getKey(),
                    'platform'     => $platform->value,
                    'device_name'  => $deviceName ?? $existing->device_name,
                    'last_used_at' => now(),
                ]);

                return $existing;
            }

            return PushToken::create([
                'user_id'      => $user->getKey(),
                'token'        => $token,
                'platform'     => $platform->value,
                'device_name'  => $deviceName,
                'last_used_at' => now(),
            ]);
        });
    }

    /**
     * Cihazın adresini siler — uygulamadan çıkıldığında ya da bildirim
     * kapatıldığında.
     */
    public function forget(User $user, string $token): bool
    {
        return PushToken::where('user_id', $user->getKey())
            ->where('token', $token)
            ->delete() > 0;
    }

    /**
     * @return Collection<int, PushToken>
     */
    public function tokensFor(User $user): Collection
    {
        return PushToken::where('user_id', $user->getKey())->get();
    }

    public function isConfigured(): bool
    {
        return config('push.driver') === 'fcm' && $this->accessToken->isConfigured();
    }

    /**
     * Bir kullanıcının bütün cihazlarına bildirim gönderir.
     *
     * @param array<string, mixed> $data Uygulamanın açacağı ekranı belirleyen ek veri
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        return $this->send($this->tokensFor($user), $title, $body, $data);
    }

    /**
     * @param Collection<int, PushToken> $tokens
     * @param array<string, mixed> $data
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function send(Collection $tokens, string $title, string $body, array $data = []): array
    {
        if ($tokens->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        }

        if (! $this->isConfigured()) {
            // Sessizce kaybolmuyor: yapılandırmayı unutan kişi, bildirimin
            // neden gitmediğini log'da görüyor.
            Log::info('Push bildirimi gönderilmedi: taşıyıcı yapılandırılmamış', [
                'title'  => $title,
                'tokens' => $tokens->count(),
            ]);

            return ['sent' => 0, 'failed' => 0, 'skipped' => $tokens->count()];
        }

        $sent = 0;
        $failed = 0;

        foreach ($tokens as $token) {
            $this->deliver($token, $title, $body, $data) ? $sent++ : $failed++;
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => 0];
    }

    /**
     * Tek bir cihaza gönderim.
     *
     * Sağlayıcı "bu jeton artık geçerli değil" derse kayıt siliniyor: ölü
     * jetonlar birikirse her bildirim, çoğu boşa giden yüzlerce isteğe dönüşür.
     */
    private function deliver(PushToken $token, string $title, string $body, array $data): bool
    {
        $accessToken = $this->accessToken->token();
        $endpoint = $this->accessToken->endpoint();

        if ($accessToken === null || $endpoint === null) {
            return false;
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout((int) config('push.timeout', 10))
                ->post($endpoint, [
                    'message' => [
                        'token'        => $token->token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data'         => self::stringifyData($data),
                    ],
                ]);

            if ($response->successful()) {
                $token->forceFill(['last_used_at' => now()])->save();

                return true;
            }

            if ($this->tokenIsDead($response->status(), (string) $response->body())) {
                $token->delete();
            }

            Log::warning('Push bildirimi düştü', [
                'token_id' => $token->getKey(),
                'status'   => $response->status(),
                'error'    => mb_substr((string) $response->body(), 0, 300),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('Push bildirimi gönderilemedi', [
                'token_id' => $token->getKey(),
                'error'    => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Jeton artık geçerli değil mi?
     *
     * v1 bunu iki biçimde söylüyor: uygulaması silinmiş cihaz için 404
     * UNREGISTERED, hiç var olmamış bir dizge için 400 INVALID_ARGUMENT.
     * İkisinde de kayıt gitmeli — ölü jetonlar birikirse her bildirim, çoğu
     * boşa giden yüzlerce isteğe dönüşür.
     *
     * 401/403 bilerek dışarıda: onlar jetonun değil kurulumun sorunu ve
     * kayıtları silmek yanlış olurdu.
     */
    private function tokenIsDead(int $status, string $body): bool
    {
        if ($status === 404) {
            return true;
        }

        return $status === 400 && str_contains($body, 'INVALID_ARGUMENT');
    }

    /**
     * v1 `data` alanında yalnız dizge kabul ediyor.
     *
     * Sayı ya da boolean gönderen bir çağrı 400 alıyordu; dönüştürme burada
     * yapılıyor ki çağıran tarafın bunu bilmesi gerekmesin.
     *
     * @param  array<string, mixed> $data
     * @return array<string, string>
     */
    private static function stringifyData(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $out[(string) $key] = match (true) {
                is_bool($value)   => $value ? 'true' : 'false',
                is_scalar($value) => (string) $value,
                $value === null   => '',
                default           => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            };
        }

        return $out;
    }

    /**
     * Uzun süredir kullanılmayan jetonları temizler.
     *
     * Uygulama silinmiş bir telefonun jetonu sonsuza kadar durmamalı; her
     * gönderimde boşa giden bir istek demek.
     */
    public function pruneStale(int $days = 180): int
    {
        return PushToken::where('last_used_at', '<', now()->subDays($days))->delete();
    }
}
