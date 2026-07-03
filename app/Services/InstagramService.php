<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InstagramMediaType;
use App\Enums\InstagramPostStatus;
use App\Models\InstagramPost;
use App\Models\InstagramPostLog;
use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

final class InstagramService
{
    private const string GRAPH_BASE = 'https://graph.facebook.com/v21.0';

    private const int CONTAINER_READY_ATTEMPTS = 5;
    private const int CONTAINER_READY_SLEEP = 3;

    // Reels/Story video container'ı daha uzun sürebilir (Meta tarafında video processing)
    private const int VIDEO_CONTAINER_READY_ATTEMPTS = 12;
    private const int VIDEO_CONTAINER_READY_SLEEP = 5;

    private const string RATE_LIMIT_KEY = 'instagram-api-rate-limit';

    /**
     * @return array{success: bool, message: string}
     */
    public function publish(InstagramPost $post): array
    {
        if (RateLimiter::tooManyAttempts(self::RATE_LIMIT_KEY, 25)) {
            $seconds = RateLimiter::availableIn(self::RATE_LIMIT_KEY);
            $message = "API rate limit aşıldı. {$seconds} saniye sonra tekrar deneyin.";
            $post->update([
                'status'        => InstagramPostStatus::Failed,
                'error_message' => $message,
                'retry_count'   => $post->retry_count + 1,
            ]);

            return ['success' => false, 'message' => $message];
        }

        $igUserId = $this->getIgUserId();
        $token = $this->getAccessToken();

        if ($igUserId === null || $token === null) {
            $message = 'Instagram API bilgileri (User ID veya Access Token) ayarlarda eksik.';
            $post->update([
                'status'        => InstagramPostStatus::Failed,
                'error_message' => $message,
                'retry_count'   => $post->retry_count + 1,
            ]);

            return ['success' => false, 'message' => $message];
        }

        $caption = $post->buildFullCaption();

        try {
            return DB::transaction(function () use ($post, $igUserId, $token, $caption) {
                $mediaType = $post->media_type ?? InstagramMediaType::Image;

                // Media type'a göre container oluştur
                $containerId = match ($mediaType) {
                    InstagramMediaType::Reels => $this->createReelsContainer($igUserId, $token, $post, $caption),
                    InstagramMediaType::Story => $this->createStoryContainer($igUserId, $token, $post),
                    InstagramMediaType::Image => $post->additionalImages->isNotEmpty()
                        ? $this->createCarouselContainer($igUserId, $token, $post, $caption)
                        : $this->createMediaContainer($igUserId, $token, $this->buildPublicImageUrl($post->image_path), $caption, $post),
                };

                if ($containerId === null) {
                    return ['success' => false, 'message' => $post->error_message ?? 'Media container oluşturulamadı.'];
                }

                $post->update(['ig_media_id' => $containerId]);

                $isVideoMedia = in_array($mediaType, [InstagramMediaType::Reels, InstagramMediaType::Story], true)
                    && ! empty($post->video_path);

                if (! $this->waitForContainerReady($containerId, $token, $post, $isVideoMedia)) {
                    return ['success' => false, 'message' => $post->error_message ?? 'Container yayına hazır duruma gelmedi.'];
                }

                $publishResult = $this->publishContainer($igUserId, $token, $containerId, $post);

                if (! $publishResult['success']) {
                    return $publishResult;
                }

                $permalink = $this->fetchPermalink($publishResult['ig_post_id'], $token);

                $post->update([
                    'status'        => InstagramPostStatus::Published,
                    'ig_post_id'    => $publishResult['ig_post_id'],
                    'permalink'     => $permalink,
                    'published_at'  => now(),
                    'error_message' => null,
                ]);

                return ['success' => true, 'message' => 'Instagram gönderisi başarıyla yayınlandı.'];
            });
        } catch (\Throwable $e) {
            Log::error('Instagram publish exception', [
                'post_id' => $post->id,
                'error'   => $e->getMessage(),
            ]);

            $post->update([
                'status'        => InstagramPostStatus::Failed,
                'error_message' => $e->getMessage(),
                'retry_count'   => $post->retry_count + 1,
            ]);

            return ['success' => false, 'message' => 'Beklenmedik hata: ' . $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        $igUserId = $this->getIgUserId();
        $token = $this->getAccessToken();

        if ($igUserId === null || $token === null) {
            return ['success' => false, 'message' => 'Instagram Access Token ve User ID ayarlarda eksik.'];
        }

        try {
            $response = $this->httpGet(self::GRAPH_BASE . '/' . $igUserId, [
                'fields'       => 'id,username,name',
                'access_token' => $token,
            ]);

            if ($response->failed()) {
                $message = (string) ($response->json('error.message') ?? 'Bilinmeyen API hatası');

                return ['success' => false, 'message' => 'Bağlantı başarısız: ' . $message];
            }

            $username = (string) ($response->json('username') ?? '—');

            return ['success' => true, 'message' => "Bağlantı başarılı. Hesap: @{$username}"];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Bağlantı hatası: ' . $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, message: string, token?: string}
     */
    public function refreshLongLivedToken(): array
    {
        $appId = Setting::getValue('instagram_app_id');
        $appSecret = Setting::getValue('instagram_app_secret');
        $currentToken = $this->getAccessToken();

        if (empty($appId) || empty($appSecret) || $currentToken === null) {
            return ['success' => false, 'message' => 'App ID, App Secret veya mevcut token eksik.'];
        }

        try {
            $response = $this->httpGet(self::GRAPH_BASE . '/oauth/access_token', [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => $appId,
                'client_secret'     => $appSecret,
                'fb_exchange_token' => $currentToken,
            ]);

            if ($response->failed()) {
                return ['success' => false, 'message' => 'Token yenilenemedi: ' . (string) ($response->json('error.message') ?? '')];
            }

            $newToken = (string) $response->json('access_token');

            if ($newToken === '') {
                return ['success' => false, 'message' => 'Yeni token boş döndü.'];
            }

            Setting::setValue('instagram_access_token', $newToken, 'instagram', 'password');

            $expiresIn = (int) ($response->json('expires_in') ?? 0);
            if ($expiresIn > 0) {
                $expiresAt = now()->addSeconds($expiresIn)->toDateTimeString();
                Setting::setValue('instagram_token_expires_at', $expiresAt, 'instagram', 'text');
            }

            return ['success' => true, 'message' => 'Access Token başarıyla yenilendi.', 'token' => $newToken];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Token yenileme hatası: ' . $e->getMessage()];
        }
    }

    public function isTokenExpiringSoon(int $thresholdDays = 10): bool
    {
        $expiresAt = Setting::getValue('instagram_token_expires_at');

        if (empty($expiresAt)) {
            return true;
        }

        try {
            return now()->diffInDays(\Carbon\Carbon::parse($expiresAt), absolute: false) <= $thresholdDays;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Token süresi durumunu UI için hesapla (banner/badge için tek kaynak).
     *
     * @return array{level: 'ok'|'warning'|'critical'|'expired'|'missing'|'unknown', days: int|null, expires_at: \Carbon\Carbon|null, message: string}
     */
    public function getDisplayTokenStatus(): array
    {
        $accessToken = (string) Setting::getValue('instagram_access_token', '');
        if ($accessToken === '') {
            return [
                'level'      => 'missing',
                'days'       => null,
                'expires_at' => null,
                'message'    => 'Instagram access token eksik — entegrasyon kapalı.',
            ];
        }

        $expiresAt = (string) Setting::getValue('instagram_token_expires_at', '');
        if ($expiresAt === '') {
            return [
                'level'      => 'unknown',
                'days'       => null,
                'expires_at' => null,
                'message'    => 'Token süresi bilinmiyor — "Token\'ı Yenile" ile süre bilgisini al.',
            ];
        }

        try {
            $expiry = \Carbon\Carbon::parse($expiresAt);
        } catch (\Throwable) {
            return [
                'level'      => 'unknown',
                'days'       => null,
                'expires_at' => null,
                'message'    => 'Token süresi okunamadı.',
            ];
        }

        // Süre dolmuşsa days hesabı yapmadan doğrudan expired döndür
        // ((int) cast -0.5'i 0 yapıp 'critical' false-positive üretebilirdi.)
        if ($expiry->isPast()) {
            return [
                'level'      => 'expired',
                'days'       => 0,
                'expires_at' => $expiry,
                'message'    => 'Instagram token süresi DOLMUŞ — postlar paylaşılmıyor.',
            ];
        }

        $days = (int) now()->diffInDays($expiry, absolute: false);

        return match (true) {
            $days <= 3  => [
                'level'      => 'critical',
                'days'       => $days,
                'expires_at' => $expiry,
                'message'    => "Instagram token {$days} gün içinde bitecek — hemen yenile.",
            ],
            $days <= 10 => [
                'level'      => 'warning',
                'days'       => $days,
                'expires_at' => $expiry,
                'message'    => "Instagram token {$days} gün sonra bitiyor — yakında yenile.",
            ],
            default     => [
                'level'      => 'ok',
                'days'       => $days,
                'expires_at' => $expiry,
                'message'    => "Instagram token {$days} gün geçerli.",
            ],
        };
    }

    /**
     * Yayınlanmış post için engagement metrics çek (Meta Graph API).
     *
     * Public alanlar (her hesapta var): like_count, comments_count
     * Insights alanlar (business/creator gerektirir): reach, impressions, saved
     *
     * Insights başarısız olsa bile public metrics yine kaydedilir (graceful degradation).
     *
     * @return array{success: bool, message: string}
     */
    public function fetchPostMetrics(InstagramPost $post): array
    {
        if (empty($post->ig_post_id)) {
            return ['success' => false, 'message' => 'Post henüz yayınlanmamış (ig_post_id boş).'];
        }

        $token = $this->getAccessToken();
        if ($token === null) {
            return ['success' => false, 'message' => 'Instagram access token ayarlarda eksik.'];
        }

        $updates = ['metrics_fetched_at' => now()];

        // 1) Public metrics (like + comment count)
        try {
            $publicResponse = $this->httpGet(self::GRAPH_BASE . '/' . $post->ig_post_id, [
                'fields'       => 'like_count,comments_count',
                'access_token' => $token,
            ]);

            if ($publicResponse->successful()) {
                $updates['like_count']     = (int) ($publicResponse->json('like_count') ?? 0);
                $updates['comments_count'] = (int) ($publicResponse->json('comments_count') ?? 0);
            }
        } catch (\Throwable) {
            // Public failure → ConnectionException vs. — devam et insights için dene
        }

        // 2) Insights metrics (reach, impressions, saved) — sadece business/creator
        try {
            $insightsResponse = $this->httpGet(self::GRAPH_BASE . '/' . $post->ig_post_id . '/insights', [
                'metric'       => 'reach,impressions,saved',
                'access_token' => $token,
            ]);

            if ($insightsResponse->successful()) {
                $data = (array) ($insightsResponse->json('data') ?? []);
                foreach ($data as $metric) {
                    $name  = (string) ($metric['name'] ?? '');
                    $value = (int) ($metric['values'][0]['value'] ?? 0);

                    match ($name) {
                        'reach'       => $updates['reach'] = $value,
                        'impressions' => $updates['impressions'] = $value,
                        'saved'       => $updates['saved_count'] = $value,
                        default       => null,
                    };
                }
            }
        } catch (\Throwable) {
            // Insights başarısız (örn. business hesap değil) — devam et
        }

        // En azından metrics_fetched_at güncellensin (yarın tekrar denemeyelim)
        $post->update($updates);

        $hasAny = isset($updates['like_count']) || isset($updates['reach']);

        return [
            'success' => $hasAny,
            'message' => $hasAny
                ? 'Metrics güncellendi.'
                : 'Hiçbir metric alınamadı (token izinleri veya hesap tipi sorunu olabilir).',
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // HTTP helpers with retry
    // ──────────────────────────────────────────────────────────────

    private function httpGet(string $url, array $query = []): Response
    {
        return Http::timeout(15)
            ->retry(3, 2000, fn (\Throwable $e): bool => $this->shouldRetry($e))
            ->get($url, $query);
    }

    private function httpPost(string $url, array $data = []): Response
    {
        return Http::timeout(30)
            ->retry(3, 2000, fn (\Throwable $e): bool => $this->shouldRetry($e))
            ->post($url, $data);
    }

    /**
     * Laravel retry callback'in 2. argümanı PendingRequest'tir (Response değil),
     * bu yüzden response status'ünü exception üzerinden kontrol ediyoruz.
     * Laravel 4xx/5xx için RequestException oluşturup callback'e iletir.
     */
    private function shouldRetry(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException && $e->response !== null) {
            $status = $e->response->status();

            if ($status === 429) {
                RateLimiter::hit(self::RATE_LIMIT_KEY, 3600);

                return false;
            }

            return $status >= 500;
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Carousel post için: önce her görsel için child container oluştur
     * (is_carousel_item=true), sonra parent carousel container'ı oluştur.
     * Spec: https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-user/media#carousel-posts
     */
    private function createCarouselContainer(string $igUserId, string $token, InstagramPost $post, string $caption): ?string
    {
        $allPaths = $post->allImagePaths();

        if (count($allPaths) < 2) {
            $post->update([
                'status'        => InstagramPostStatus::Failed,
                'error_message' => 'Carousel için en az 2 görsel olmalı.',
            ]);

            return null;
        }

        if (count($allPaths) > 10) {
            $post->update([
                'status'        => InstagramPostStatus::Failed,
                'error_message' => "Carousel'a en fazla 10 görsel eklenebilir, " . count($allPaths) . ' var.',
            ]);

            return null;
        }

        $childContainerIds = [];
        $start = hrtime(true);

        foreach ($allPaths as $index => $path) {
            try {
                $imageUrl = $this->buildPublicImageUrl($path);
            } catch (\RuntimeException $e) {
                $post->update([
                    'status'        => InstagramPostStatus::Failed,
                    'error_message' => "Görsel #{$index} URL geçersiz: " . $e->getMessage(),
                    'retry_count'   => $post->retry_count + 1,
                ]);

                return null;
            }

            try {
                RateLimiter::hit(self::RATE_LIMIT_KEY, 3600);

                $response = $this->httpPost(self::GRAPH_BASE . '/' . $igUserId . '/media', [
                    'image_url'        => $imageUrl,
                    'is_carousel_item' => 'true',
                    'access_token'     => $token,
                ]);

                $this->log($post->id, 'create_carousel_child_' . $index, $response->successful() ? 'success' : 'failed', [
                    'image_url' => $imageUrl,
                ], $response, null);

                if ($response->failed()) {
                    $err = (string) ($response->json('error.message') ?? 'Bilinmeyen hata');
                    $post->update([
                        'status'        => InstagramPostStatus::Failed,
                        'error_message' => "Carousel görsel #{$index} container oluşturulamadı: {$err}",
                        'retry_count'   => $post->retry_count + 1,
                    ]);

                    return null;
                }

                $childId = (string) ($response->json('id') ?? '');
                if ($childId === '') {
                    $post->update([
                        'status'        => InstagramPostStatus::Failed,
                        'error_message' => "Carousel görsel #{$index} için child ID alınamadı.",
                    ]);

                    return null;
                }

                $childContainerIds[] = $childId;
            } catch (ConnectionException $e) {
                $post->update([
                    'status'        => InstagramPostStatus::Failed,
                    'error_message' => "Carousel görsel #{$index} bağlantı hatası: " . $e->getMessage(),
                    'retry_count'   => $post->retry_count + 1,
                ]);

                return null;
            }
        }

        // Parent carousel container
        try {
            RateLimiter::hit(self::RATE_LIMIT_KEY, 3600);

            $response = $this->httpPost(self::GRAPH_BASE . '/' . $igUserId . '/media', [
                'media_type'   => 'CAROUSEL',
                'children'     => implode(',', $childContainerIds),
                'caption'      => $caption,
                'access_token' => $token,
            ]);

            $duration = (int) ((hrtime(true) - $start) / 1_000_000);

            $this->log($post->id, 'create_carousel_parent', $response->successful() ? 'success' : 'failed', [
                'children_count' => count($childContainerIds),
                'caption_length' => strlen($caption),
            ], $response, $duration);

            if ($response->failed()) {
                $err = (string) ($response->json('error.message') ?? 'Bilinmeyen hata');
                $post->update([
                    'status'        => InstagramPostStatus::Failed,
                    'error_message' => "Carousel parent container oluşturulamadı: {$err}",
                    'retry_count'   => $post->retry_count + 1,
                ]);

                return null;
            }

            return (string) $response->json('id');
        } catch (ConnectionException $e) {
            $post->update([
                'status'        => InstagramPostStatus::Failed,
                'error_message' => 'Carousel parent bağlantı hatası: ' . $e->getMessage(),
                'retry_count'   => $post->retry_count + 1,
            ]);

            return null;
        }
    }

    private function createMediaContainer(string $igUserId, string $token, string $imageUrl, string $caption, InstagramPost $post): ?string
    {
        $start = hrtime(true);
        $payload = [
            'image_url'    => $imageUrl,
            'caption'      => $caption,
            'access_token' => $token,
        ];

        try {
            RateLimiter::hit(self::RATE_LIMIT_KEY, 3600);

            $response = $this->httpPost(self::GRAPH_BASE . '/' . $igUserId . '/media', $payload);
            $duration = (int) ((hrtime(true) - $start) / 1_000_000);

            $logPayload = $payload;
            unset($logPayload['access_token']);

            $this->log($post->id, 'create_container', $response->successful() ? 'success' : 'failed', $logPayload, $response, $duration);

            if ($response->failed()) {
                $apiError = (string) ($response->json('error.message') ?? 'Bilinmeyen hata');
                $apiCode = $response->json('error.code');
                $message = "Container oluşturulamadı (HTTP {$response->status()}): {$apiError}";

                if ($apiCode) {
                    $message .= " [Meta hata kodu: {$apiCode}]";
                }

                $post->update([
                    'status'        => InstagramPostStatus::Failed,
                    'error_message' => $message,
                    'retry_count'   => $post->retry_count + 1,
                ]);

                return null;
            }

            $id = $response->json('id');

            if (empty($id)) {
                $post->update([
                    'status'        => InstagramPostStatus::Failed,
                    'error_message' => 'API container ID döndürmedi.',
                    'retry_count'   => $post->retry_count + 1,
                ]);

                return null;
            }

            return (string) $id;
        } catch (ConnectionException $e) {
            $this->log($post->id, 'create_container', 'failed', null, null, null, $e->getMessage());
            $post->update([
                'status'        => InstagramPostStatus::Failed,
                'error_message' => 'Bağlantı hatası (sunucu erişilemedi): ' . $e->getMessage(),
                'retry_count'   => $post->retry_count + 1,
            ]);

            return null;
        }
    }

    /**
     * Reels container — video_url + media_type=REELS.
     * Reels'te carousel desteklenmez, tek video.
     */
    private function createReelsContainer(string $igUserId, string $token, InstagramPost $post, string $caption): ?string
    {
        if (empty($post->video_path)) {
            $post->update([
                'status'        => InstagramPostStatus::Failed,
                'error_message' => 'Reels paylaşımı için video gerekli ama video_path boş.',
                'retry_count'   => $post->retry_count + 1,
            ]);

            return null;
        }

        try {
            $videoUrl = $this->buildPublicVideoUrl($post->video_path);
        } catch (\RuntimeException $e) {
            $post->update([
                'status'        => InstagramPostStatus::Failed,
                'error_message' => 'Reels video URL geçersiz: ' . $e->getMessage(),
                'retry_count'   => $post->retry_count + 1,
            ]);

            return null;
        }

        $start = hrtime(true);

        try {
            RateLimiter::hit(self::RATE_LIMIT_KEY, 3600);

            $response = $this->httpPost(self::GRAPH_BASE . '/' . $igUserId . '/media', [
                'media_type'   => 'REELS',
                'video_url'    => $videoUrl,
                'caption'      => $caption,
                'access_token' => $token,
            ]);

            $duration = (int) ((hrtime(true) - $start) / 1_000_000);
            $this->log($post->id, 'create_reels_container', $response->successful() ? 'success' : 'failed', [
                'video_url' => $videoUrl,
                'caption_length' => strlen($caption),
            ], $response, $duration);

            if ($response->failed()) {
                $err = (string) ($response->json('error.message') ?? 'Bilinmeyen hata');
                $post->update([
                    'status'        => InstagramPostStatus::Failed,
                    'error_message' => "Reels container oluşturulamadı: {$err}",
                    'retry_count'   => $post->retry_count + 1,
                ]);

                return null;
            }

            $id = (string) ($response->json('id') ?? '');
            if ($id === '') {
                $post->update([
                    'status'        => InstagramPostStatus::Failed,
                    'error_message' => 'Reels container ID alınamadı.',
                    'retry_count'   => $post->retry_count + 1,
                ]);

                return null;
            }

            return $id;
        } catch (ConnectionException $e) {
            $this->log($post->id, 'create_reels_container', 'failed', null, null, null, $e->getMessage());
            $post->update([
                'status'        => InstagramPostStatus::Failed,
                'error_message' => 'Reels bağlantı hatası: ' . $e->getMessage(),
                'retry_count'   => $post->retry_count + 1,
            ]);

            return null;
        }
    }

    /**
     * Story container — image veya video destekler. Caption Story'de gösterilmez (Meta).
     */
    private function createStoryContainer(string $igUserId, string $token, InstagramPost $post): ?string
    {
        $hasVideo = ! empty($post->video_path);
        $hasImage = ! empty($post->image_path);

        if (! $hasVideo && ! $hasImage) {
            $post->update([
                'status'        => InstagramPostStatus::Failed,
                'error_message' => 'Story için görsel veya video gerekli — ikisi de yok.',
                'retry_count'   => $post->retry_count + 1,
            ]);

            return null;
        }

        try {
            $mediaUrl = $hasVideo
                ? $this->buildPublicVideoUrl($post->video_path)
                : $this->buildPublicImageUrl($post->image_path);
        } catch (\RuntimeException $e) {
            $post->update([
                'status'        => InstagramPostStatus::Failed,
                'error_message' => 'Story media URL geçersiz: ' . $e->getMessage(),
                'retry_count'   => $post->retry_count + 1,
            ]);

            return null;
        }

        $payload = [
            'media_type'   => 'STORIES',
            'access_token' => $token,
        ];
        if ($hasVideo) {
            $payload['video_url'] = $mediaUrl;
        } else {
            $payload['image_url'] = $mediaUrl;
        }

        $start = hrtime(true);

        try {
            RateLimiter::hit(self::RATE_LIMIT_KEY, 3600);

            $response = $this->httpPost(self::GRAPH_BASE . '/' . $igUserId . '/media', $payload);
            $duration = (int) ((hrtime(true) - $start) / 1_000_000);

            $logPayload = $payload;
            unset($logPayload['access_token']);

            $this->log($post->id, 'create_story_container', $response->successful() ? 'success' : 'failed', $logPayload, $response, $duration);

            if ($response->failed()) {
                $err = (string) ($response->json('error.message') ?? 'Bilinmeyen hata');
                $post->update([
                    'status'        => InstagramPostStatus::Failed,
                    'error_message' => "Story container oluşturulamadı: {$err}",
                    'retry_count'   => $post->retry_count + 1,
                ]);

                return null;
            }

            $id = (string) ($response->json('id') ?? '');
            if ($id === '') {
                $post->update([
                    'status'        => InstagramPostStatus::Failed,
                    'error_message' => 'Story container ID alınamadı.',
                    'retry_count'   => $post->retry_count + 1,
                ]);

                return null;
            }

            return $id;
        } catch (ConnectionException $e) {
            $this->log($post->id, 'create_story_container', 'failed', null, null, null, $e->getMessage());
            $post->update([
                'status'        => InstagramPostStatus::Failed,
                'error_message' => 'Story bağlantı hatası: ' . $e->getMessage(),
                'retry_count'   => $post->retry_count + 1,
            ]);

            return null;
        }
    }

    private function waitForContainerReady(string $containerId, string $token, InstagramPost $post, bool $isVideoMedia = false): bool
    {
        $maxAttempts = $isVideoMedia ? self::VIDEO_CONTAINER_READY_ATTEMPTS : self::CONTAINER_READY_ATTEMPTS;
        $sleepSeconds = $isVideoMedia ? self::VIDEO_CONTAINER_READY_SLEEP : self::CONTAINER_READY_SLEEP;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout(15)->get(self::GRAPH_BASE . '/' . $containerId, [
                    'fields'       => 'status_code,status',
                    'access_token' => $token,
                ]);

                if ($response->successful()) {
                    $status = (string) ($response->json('status_code') ?? '');

                    if ($status === 'FINISHED') {
                        return true;
                    }

                    if ($status === 'ERROR' || $status === 'EXPIRED') {
                        $post->update([
                            'status'        => InstagramPostStatus::Failed,
                            'error_message' => "Container hata durumunda: {$status} (deneme {$attempt}/{$maxAttempts})",
                            'retry_count'   => $post->retry_count + 1,
                        ]);

                        return false;
                    }
                }
            } catch (\Throwable) {
                // Ignore transient errors and keep polling
            }

            if ($attempt < $maxAttempts) {
                sleep($sleepSeconds);
            }
        }

        $totalWaitSeconds = $maxAttempts * $sleepSeconds;
        $post->update([
            'status'        => InstagramPostStatus::Failed,
            'error_message' => "Container zaman aşımına uğradı ({$totalWaitSeconds} sn).",
            'retry_count'   => $post->retry_count + 1,
        ]);

        return false;
    }

    /**
     * @return array{success: bool, message: string, ig_post_id?: string}
     */
    private function publishContainer(string $igUserId, string $token, string $containerId, InstagramPost $post): array
    {
        $start = hrtime(true);
        $payload = [
            'creation_id'  => $containerId,
            'access_token' => $token,
        ];

        try {
            RateLimiter::hit(self::RATE_LIMIT_KEY, 3600);

            $response = $this->httpPost(self::GRAPH_BASE . '/' . $igUserId . '/media_publish', $payload);
            $duration = (int) ((hrtime(true) - $start) / 1_000_000);

            $logPayload = $payload;
            unset($logPayload['access_token']);

            $this->log($post->id, 'publish', $response->successful() ? 'success' : 'failed', $logPayload, $response, $duration);

            if ($response->failed()) {
                $apiError = (string) ($response->json('error.message') ?? 'Bilinmeyen hata');
                $apiCode = $response->json('error.code');
                $message = "Yayın başarısız (HTTP {$response->status()}): {$apiError}";

                if ($apiCode) {
                    $message .= " [Meta hata kodu: {$apiCode}]";
                }

                $post->update([
                    'status'        => InstagramPostStatus::Failed,
                    'error_message' => $message,
                    'retry_count'   => $post->retry_count + 1,
                ]);

                return ['success' => false, 'message' => $message];
            }

            $postId = (string) ($response->json('id') ?? '');

            if ($postId === '') {
                $post->update([
                    'status'        => InstagramPostStatus::Failed,
                    'error_message' => 'Yayın başarılı ama post ID alınamadı.',
                ]);

                return ['success' => false, 'message' => 'Post ID alınamadı.'];
            }

            return ['success' => true, 'message' => 'Yayınlandı.', 'ig_post_id' => $postId];
        } catch (ConnectionException $e) {
            $this->log($post->id, 'publish', 'failed', null, null, null, $e->getMessage());

            $post->update([
                'status'        => InstagramPostStatus::Failed,
                'error_message' => 'Bağlantı hatası (sunucu erişilemedi): ' . $e->getMessage(),
                'retry_count'   => $post->retry_count + 1,
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function fetchPermalink(string $igPostId, string $token): ?string
    {
        try {
            $response = Http::timeout(10)->get(self::GRAPH_BASE . '/' . $igPostId, [
                'fields'       => 'permalink',
                'access_token' => $token,
            ]);

            if ($response->successful()) {
                $permalink = $response->json('permalink');

                return is_string($permalink) ? $permalink : null;
            }
        } catch (\Throwable) {
            // Non-fatal
        }

        return null;
    }

    private function getIgUserId(): ?string
    {
        $value = Setting::getValue('instagram_user_id');

        return empty($value) ? null : (string) $value;
    }

    private function getAccessToken(): ?string
    {
        $value = Setting::getValue('instagram_access_token');

        return empty($value) ? null : (string) $value;
    }

    private function buildPublicImageUrl(string $imagePath): string
    {
        $url = UploadService::url($imagePath, 'lg');

        return $this->resolvePublicUrl($url, 'görsel');
    }

    /**
     * Reels/Story video paylaşımı için tam URL — UploadService::url() default size yok,
     * raw path döndürür.
     */
    private function buildPublicVideoUrl(string $videoPath): string
    {
        $url = UploadService::url($videoPath); // size=null, raw path

        return $this->resolvePublicUrl($url, 'video');
    }

    /**
     * URL'i absolute hale getir + Meta API erişebileceğini doğrula.
     *
     * @throws \RuntimeException
     */
    private function resolvePublicUrl(string $url, string $kind): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $finalUrl = $url;
        } else {
            $base = rtrim((string) config('app.url'), '/');
            $finalUrl = $base . '/' . ltrim($url, '/');
        }

        if (! filter_var($finalUrl, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException("Geçersiz {$kind} URL: {$finalUrl}");
        }

        $host = (string) parse_url($finalUrl, PHP_URL_HOST);
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true) || str_ends_with($host, '.local')) {
            throw new \RuntimeException("Meta API localhost URL'lere erişemez. Production URL gerekli: {$finalUrl}");
        }

        return $finalUrl;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function log(?int $postId, string $action, string $status, ?array $payload, ?Response $response, ?int $durationMs, ?string $errorMessage = null): void
    {
        try {
            InstagramPostLog::create([
                'instagram_post_id' => $postId,
                'action'            => $action,
                'status'            => $status,
                'request_payload'   => $payload,
                'response_body'     => $response?->body(),
                'error_message'     => $errorMessage ?? ($response?->failed() ? (string) ($response->json('error.message') ?? $response->body()) : null),
                'api_duration_ms'   => $durationMs,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Instagram log kaydedilemedi', ['error' => $e->getMessage()]);
        }
    }
}
