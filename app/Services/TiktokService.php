<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InstagramMediaType;
use App\Models\InstagramPost;
use App\Models\Setting;
use App\Services\Tiktok\TiktokApiClient;
use App\Services\Tiktok\TiktokApiException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * TikTok cross-post yüksek seviye iş mantığı.
 *
 * docs/tiktok.md Bölüm 4.3 — TiktokApiClient'ı kullanarak
 * InstagramPost → TikTok yayın akışını orkestre eder.
 */
final class TiktokService
{
    private const RATE_LIMIT_PER_MINUTE = 8;          // TT limit 10/min, güvenli pay
    private const POLL_INTERVAL_SECONDS = 5;
    private const POLL_MAX_ATTEMPTS = 12;              // 5sn × 12 = 60sn max

    /**
     * InstagramPost'tan TikTok yayını yap.
     *
     * @return array{success: bool, message: string, tt_post_id?: string,
     *               tt_permalink?: string, tt_inbox_id?: string}
     */
    public function publish(InstagramPost $post): array
    {
        // 1) Master switch
        if (Setting::getValue('tiktok_enabled', '0') !== '1') {
            return ['success' => false, 'message' => 'TikTok cross-post Settings\'ten kapalı.'];
        }

        // 2) Idempotency — zaten yayında
        if ($post->tt_post_id !== null) {
            return [
                'success'      => true,
                'message'      => 'Bu post zaten TikTok\'ta yayında.',
                'tt_post_id'   => $post->tt_post_id,
                'tt_permalink' => (string) ($post->tt_permalink ?? ''),
            ];
        }

        // 3) Rate limit guard (dakika bucket)
        $rateKey = 'tt_publish_throttle:' . now()->format('YmdHi');
        $count = (int) Cache::get($rateKey, 0);
        if ($count >= self::RATE_LIMIT_PER_MINUTE) {
            return [
                'success' => false,
                'message' => 'TikTok rate limit ('. self::RATE_LIMIT_PER_MINUTE .'/dakika). Bir sonraki cron turunda denenecek.',
            ];
        }

        // 4) Token expire kontrolü
        $token = trim((string) Setting::getValue('tiktok_access_token', ''));
        if ($token === '') {
            return ['success' => false, 'message' => 'TikTok access_token tanımlı değil. Settings → TikTok → Bağla'];
        }

        $expiresAtStr = (string) Setting::getValue('tiktok_expires_at', '');
        if ($expiresAtStr !== '') {
            try {
                $expiresAt = Carbon::parse($expiresAtStr);
                // 10dk içinde dolacaksa preemptive refresh
                if ($expiresAt->lt(now()->addMinutes(10))) {
                    $refreshResult = $this->refreshAccessToken();
                    if (! $refreshResult['success']) {
                        return [
                            'success' => false,
                            'message' => 'Token expire + refresh fail: ' . $refreshResult['message'],
                        ];
                    }
                }
            } catch (\Throwable) {
                // parse fail → token'ı yine de denesin
            }
        }

        // 5) Asıl yayın
        try {
            Cache::put($rateKey, $count + 1, now()->addMinutes(2));

            $client = $this->buildClient();
            $caption = $post->buildFullCaption();
            $postMode = Setting::getValue('tiktok_post_mode', 'inbox');

            $options = $this->buildOptions();

            // Video mu, foto mu?
            $isVideo = ! empty($post->video_path);

            // Inbox modu — Direct Post yerine drafts
            if ($postMode === 'inbox') {
                if (! $isVideo) {
                    // Inbox API sadece video destekliyor — foto için "inbox" yok
                    return [
                        'success' => false,
                        'message' => 'Inbox modu sadece video destekler. Photo Mode için tiktok_post_mode=direct gerekli (audit gerekli).',
                    ];
                }

                $videoUrl = upload_url((string) $post->video_path);
                $result = $client->publishToInbox($videoUrl, $options);

                Log::info('TikTok Inbox publish', [
                    'post_id'    => $post->id,
                    'publish_id' => $result['publish_id'],
                ]);

                return [
                    'success'     => true,
                    'message'     => 'TikTok Inbox\'a düştü. Mobilde TikTok → Inbox → Drafts → Paylaş.',
                    'tt_inbox_id' => $result['publish_id'],
                ];
            }

            // Direct Post modu
            if ($isVideo) {
                $videoUrl = upload_url((string) $post->video_path);
                $init = $client->publishVideo($caption, $videoUrl, $options);
            } else {
                $imageUrls = $this->collectImageUrls($post);
                if (empty($imageUrls)) {
                    return ['success' => false, 'message' => 'Yüklenecek görsel bulunamadı.'];
                }
                $init = $client->publishPhoto($caption, $imageUrls, $options);
            }

            // Status poll
            $publishId = $init['publish_id'];
            for ($i = 0; $i < self::POLL_MAX_ATTEMPTS; $i++) {
                sleep(self::POLL_INTERVAL_SECONDS);
                $status = $client->getPublishStatus($publishId);
                $state = (string) ($status['status'] ?? '');

                if ($state === 'PUBLISH_COMPLETE') {
                    $postIds = (array) ($status['publicaly_available_post_id'] ?? $status['publicly_available_post_id'] ?? []);
                    $ttPostId = (string) ($postIds[0] ?? '');
                    $permalink = $this->buildPermalink($ttPostId);

                    Log::info('TikTok Direct Post başarılı', [
                        'post_id'    => $post->id,
                        'tt_post_id' => $ttPostId,
                    ]);

                    return [
                        'success'      => true,
                        'message'      => 'TikTok\'ta yayınlandı.',
                        'tt_post_id'   => $ttPostId,
                        'tt_permalink' => $permalink,
                    ];
                }

                if ($state === 'FAILED') {
                    return [
                        'success' => false,
                        'message' => 'TikTok yayını başarısız: ' . ($status['fail_reason'] ?? 'unknown'),
                    ];
                }
                // PROCESSING / SEND_TO_USER_INBOX_SUCCESS — bekle
            }

            return [
                'success' => false,
                'message' => 'TikTok yayını ' . (self::POLL_INTERVAL_SECONDS * self::POLL_MAX_ATTEMPTS) . 'sn içinde tamamlanmadı (timeout).',
            ];
        } catch (TiktokApiException $e) {
            Log::warning('TikTok publish API exception', [
                'post_id' => $post->id,
                'err'     => $e->getMessage(),
                'code'    => $e->errorCode,
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::error('TikTok publish unexpected exception', [
                'post_id' => $post->id,
                'err'     => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Beklenmedik hata: ' . $e->getMessage()];
        }
    }

    /**
     * Cron / manuel retry — publish'i yeniden tetikler.
     *
     * @return array{success: bool, message: string, tt_post_id?: string, tt_permalink?: string, tt_inbox_id?: string}
     */
    public function retry(InstagramPost $post): array
    {
        $result = $this->publish($post);
        // Caller (cron veya controller) tt_retry_count++ ve update sorumluluğunu üstlenir
        return $result;
    }

    /**
     * Refresh token ile access_token yenile.
     *
     * @return array{success: bool, message: string, expires_at?: string}
     */
    public function refreshAccessToken(): array
    {
        $refreshToken = trim((string) Setting::getValue('tiktok_refresh_token', ''));
        $clientKey    = trim((string) Setting::getValue('tiktok_client_key', ''));
        $clientSecret = trim((string) Setting::getValue('tiktok_client_secret', ''));

        if ($refreshToken === '' || $clientKey === '' || $clientSecret === '') {
            return ['success' => false, 'message' => 'Refresh token veya client credentials eksik.'];
        }

        try {
            $client = $this->buildClient();
            $result = $client->refreshToken($refreshToken, $clientKey, $clientSecret);

            $expiresAt = now()->addSeconds($result['expires_in'])->toDateTimeString();

            Setting::setValue('tiktok_access_token', $result['access_token'], 'social', 'text');
            Setting::setValue('tiktok_refresh_token', $result['refresh_token'], 'social', 'text');
            Setting::setValue('tiktok_expires_at', $expiresAt, 'social', 'text');

            Log::info('TikTok token refresh başarılı', ['expires_at' => $expiresAt]);

            return [
                'success'    => true,
                'message'    => 'Token yenilendi.',
                'expires_at' => $expiresAt,
            ];
        } catch (\Throwable $e) {
            Log::warning('TikTok token refresh fail', ['err' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Bağlantı sağlığı kontrolü (Settings sayfası için).
     *
     * @return array{success: bool, message: string, username?: string}
     */
    public function validateConnection(): array
    {
        $token = trim((string) Setting::getValue('tiktok_access_token', ''));
        if ($token === '') {
            return ['success' => false, 'message' => 'Access token yok.'];
        }

        try {
            $client = $this->buildClient();
            $userInfo = $client->getUserInfo();
            $username = (string) ($userInfo['username'] ?? $userInfo['display_name'] ?? '');

            return [
                'success'  => true,
                'message'  => 'Bağlantı sağlıklı.',
                'username' => $username,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Setting'lerden TiktokApiClient instance üret (mode'a göre base URL).
     */
    private function buildClient(): TiktokApiClient
    {
        $mode = (string) Setting::getValue('tiktok_mode', 'sandbox');
        $baseUrl = $mode === 'production'
            ? (string) config('services.tiktok.base_url_production', 'https://open.tiktokapis.com')
            : (string) config('services.tiktok.base_url_sandbox', 'https://open-sandbox.tiktokapis.com');

        return new TiktokApiClient(
            accessToken: (string) Setting::getValue('tiktok_access_token', ''),
            baseUrl: $baseUrl,
        );
    }

    /**
     * Setting'lerden options (privacy + 3 disable flag).
     *
     * @return array<string, mixed>
     */
    private function buildOptions(): array
    {
        return [
            'privacy_level'   => (string) Setting::getValue('tiktok_default_privacy', 'PUBLIC_TO_EVERYONE'),
            'disable_comment' => Setting::getValue('tiktok_disable_comment', '0') === '1',
            'disable_duet'    => Setting::getValue('tiktok_disable_duet', '0') === '1',
            'disable_stitch'  => Setting::getValue('tiktok_disable_stitch', '0') === '1',
        ];
    }

    /**
     * Foto carousel için ana + ek görsellerin public URL'lerini topla.
     *
     * @return list<string>
     */
    private function collectImageUrls(InstagramPost $post): array
    {
        $urls = [];

        if ($post->image_path) {
            $urls[] = upload_url((string) $post->image_path);
        }

        $post->loadMissing('additionalImages');
        foreach ($post->additionalImages as $img) {
            if ($img->image_path) {
                $urls[] = upload_url((string) $img->image_path);
            }
        }

        return array_slice($urls, 0, 35);
    }

    /**
     * TikTok post ID → public permalink.
     * Format: https://www.tiktok.com/@<username>/video/<post_id>
     */
    private function buildPermalink(string $postId): string
    {
        $username = (string) Setting::getValue('tiktok_username', '');
        if ($postId === '' || $username === '') {
            return '';
        }
        return 'https://www.tiktok.com/@' . ltrim($username, '@') . '/video/' . $postId;
    }
}
