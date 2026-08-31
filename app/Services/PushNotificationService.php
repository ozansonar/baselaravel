<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PushPlatform;
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
        return config('push.driver') === 'fcm' && (string) config('push.fcm.key') !== '';
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
        try {
            $response = Http::withToken((string) config('push.fcm.key'))
                ->timeout((int) config('push.timeout', 10))
                ->post((string) config('push.fcm.endpoint'), [
                    'to'           => $token->token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data'         => $data,
                ]);

            if ($response->successful()) {
                $token->forceFill(['last_used_at' => now()])->save();

                return true;
            }

            if ($response->status() === 404 || str_contains((string) $response->body(), 'NotRegistered')) {
                $token->delete();
            }

            Log::warning('Push bildirimi düştü', [
                'token_id' => $token->getKey(),
                'status'   => $response->status(),
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
