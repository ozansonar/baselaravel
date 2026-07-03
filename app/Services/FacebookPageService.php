<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InstagramMediaType;
use App\Models\InstagramPost;
use App\Models\InstagramPostLog;
use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Facebook Page cross-posting — Instagram ile paralel çalışır.
 *
 * Tek görsel için: POST /{page-id}/photos
 * Carousel için: photos x N (published=false) + /feed (attached_media)
 *
 * Auth: Page Access Token (kalıcı, user token'dan farklı).
 * Setting key: instagram_facebook_page_token
 *
 * Hata yönetimi: Instagram'dan bağımsız — Instagram başarılı + Facebook başarısız
 * olabilir. Bu durumda fb_error_message yazılır, post kaybedilmez.
 */
final class FacebookPageService
{
    private const string GRAPH_BASE = 'https://graph.facebook.com/v21.0';
    private const string RATE_LIMIT_KEY = 'facebook-api-rate-limit';

    /**
     * @return array{success: bool, message: string}
     */
    public function publish(InstagramPost $post): array
    {
        if (! $post->publish_to_facebook) {
            return ['success' => true, 'message' => 'Facebook paylaşımı bu post için kapalı.'];
        }

        // Reels/Story Facebook Page Photos endpoint'iyle desteklenmez — atla.
        $mediaType = $post->media_type ?? InstagramMediaType::Image;
        if (! $mediaType->supportsFacebookCrossPost()) {
            $msg = "Facebook cross-post {$mediaType->label()} için desteklenmiyor — sadece Feed Post.";
            $post->update(['fb_error_message' => $msg]);

            return ['success' => false, 'message' => $msg];
        }

        if (RateLimiter::tooManyAttempts(self::RATE_LIMIT_KEY, 40)) {
            $seconds = RateLimiter::availableIn(self::RATE_LIMIT_KEY);
            $message = "Facebook API rate limit aşıldı. {$seconds} saniye sonra tekrar deneyin.";
            $post->update(['fb_error_message' => $message]);

            return ['success' => false, 'message' => $message];
        }

        $pageId = $this->getPageId();
        $token  = $this->getPageToken();

        if ($pageId === null || $token === null) {
            $msg = 'Facebook Page ID veya Page Token ayarlarda eksik.';
            $post->update(['fb_error_message' => $msg]);

            return ['success' => false, 'message' => $msg];
        }

        $caption = $post->buildFullCaption();

        try {
            // Reels: ayrı API akışı (3 fazlı video upload)
            if ($mediaType === InstagramMediaType::Reels) {
                if (empty($post->video_path)) {
                    $msg = 'Facebook Reels paylaşımı için video gerekli ama video_path boş.';
                    $post->update(['fb_error_message' => $msg]);

                    return ['success' => false, 'message' => $msg];
                }

                return $this->publishReels($pageId, $token, $post, $caption);
            }

            // Image (feed) — mevcut akış
            $allPaths = $post->allImagePaths();
            if ($allPaths === []) {
                return ['success' => false, 'message' => 'Facebook paylaşımı için görsel yok.'];
            }

            if (count($allPaths) === 1) {
                return $this->publishSingle($pageId, $token, $allPaths[0], $caption, $post);
            }

            return $this->publishMultiPhoto($pageId, $token, $allPaths, $caption, $post);
        } catch (\Throwable $e) {
            // Hem Laravel application log'a hem InstagramPostLog tablosuna yaz —
            // kullanıcı admin panelden /admin/instagram-logs sayfasından detayları görsün.
            $errorMessage = $this->formatThrowable($e);

            Log::error('Facebook publish exception', [
                'post_id' => $post->id,
                'error'   => $errorMessage,
                'class'   => get_class($e),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            $this->log($post->id, 'fb_publish_exception', 'failed', [
                'media_type'      => $mediaType?->value ?? 'unknown',
                'exception_class' => get_class($e),
                'exception_file'  => basename($e->getFile()) . ':' . $e->getLine(),
            ], null, null, $errorMessage);

            $post->update(['fb_error_message' => $errorMessage]);

            return ['success' => false, 'message' => 'Beklenmedik hata: ' . $errorMessage];
        }
    }

    /**
     * Throwable mesajını kullanıcı dostu formata çevir — TypeError, RuntimeException
     * gibi farklı tip exception'ların mesajlarını eşit şekilde göster.
     */
    private function formatThrowable(\Throwable $e): string
    {
        $class = (new \ReflectionClass($e))->getShortName();
        $msg = trim($e->getMessage());
        if ($msg === '') {
            $msg = 'Mesaj yok';
        }

        return "[{$class}] {$msg}";
    }

    /**
     * Test page access token validity.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        $pageId = $this->getPageId();
        $token  = $this->getPageToken();

        if ($pageId === null || $token === null) {
            return ['success' => false, 'message' => 'Facebook Page ID veya Page Token eksik.'];
        }

        try {
            $response = Http::timeout(15)->get(self::GRAPH_BASE . '/' . $pageId, [
                'fields'       => 'id,name,fan_count',
                'access_token' => $token,
            ]);

            if ($response->failed()) {
                return ['success' => false, 'message' => 'Facebook bağlantı hatası: ' . (string) ($response->json('error.message') ?? '')];
            }

            $name = (string) ($response->json('name') ?? '—');
            $fans = (int) ($response->json('fan_count') ?? 0);

            return ['success' => true, 'message' => "Facebook bağlandı: {$name} (" . number_format($fans) . ' takipçi)'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Bağlantı hatası: ' . $e->getMessage()];
        }
    }

    /**
     * User token + Page ID ile Page Access Token al ve Settings'e kaydet.
     *
     * @return array{success: bool, message: string}
     */
    public function fetchPageToken(): array
    {
        $userToken = (string) Setting::getValue('instagram_access_token', '');
        $pageId    = $this->getPageId();

        if ($userToken === '' || $pageId === null) {
            return ['success' => false, 'message' => 'User token veya Page ID eksik. Önce Instagram ayarlarını tamamla.'];
        }

        try {
            $response = Http::timeout(15)->get(self::GRAPH_BASE . '/me/accounts', [
                'access_token' => $userToken,
                'limit'        => 100,
            ]);

            if ($response->failed()) {
                return ['success' => false, 'message' => 'Page listesi alınamadı: ' . (string) ($response->json('error.message') ?? '')];
            }

            $pages = $response->json('data') ?? [];
            foreach ($pages as $page) {
                if (isset($page['id'], $page['access_token']) && (string) $page['id'] === $pageId) {
                    Setting::setValue('instagram_facebook_page_token', (string) $page['access_token'], 'instagram', 'password');

                    return ['success' => true, 'message' => 'Facebook Page Token başarıyla alındı ve kaydedildi.'];
                }
            }

            return ['success' => false, 'message' => "Page ID {$pageId} senin yetki listende yok. Meta Business Manager'da page erişimini kontrol et."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Token alma hatası: ' . $e->getMessage()];
        }
    }

    /**
     * Tek görsel post: /{page-id}/photos.
     *
     * @return array{success: bool, message: string}
     */
    private function publishSingle(string $pageId, string $token, string $imagePath, string $caption, InstagramPost $post): array
    {
        $imageUrl = $this->buildPublicImageUrl($imagePath);
        $start    = hrtime(true);

        try {
            RateLimiter::hit(self::RATE_LIMIT_KEY, 3600);

            $response = $this->httpPost(self::GRAPH_BASE . '/' . $pageId . '/photos', [
                'url'          => $imageUrl,
                'message'      => $caption,
                'access_token' => $token,
            ]);

            $duration = (int) ((hrtime(true) - $start) / 1_000_000);
            $this->log($post->id, 'fb_publish_single', $response->successful() ? 'success' : 'failed', [
                'image_url' => $imageUrl,
                'message_length' => strlen($caption),
            ], $response, $duration);

            if ($response->failed()) {
                $err = (string) ($response->json('error.message') ?? 'Bilinmeyen hata');
                $post->update(['fb_error_message' => "Facebook tek-görsel paylaşım hatası: {$err}"]);

                return ['success' => false, 'message' => $err];
            }

            $fbPostId = (string) ($response->json('post_id') ?? $response->json('id') ?? '');
            $permalink = $this->fetchPermalink($fbPostId, $token);

            $post->update([
                'fb_post_id'       => $fbPostId,
                'fb_permalink'     => $permalink,
                'fb_published_at'  => now(),
                'fb_error_message' => null,
            ]);

            return ['success' => true, 'message' => 'Facebook\'ta paylaşıldı.'];
        } catch (ConnectionException $e) {
            $post->update(['fb_error_message' => 'Bağlantı hatası: ' . $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Multi-photo album:
     * 1. Her görseli /photos?published=false ile yükle, media_id al
     * 2. /feed POST ile attached_media[]={media_fbid: id} dizisi gönder
     *
     * @param  list<string>  $imagePaths
     * @return array{success: bool, message: string}
     */
    private function publishMultiPhoto(string $pageId, string $token, array $imagePaths, string $caption, InstagramPost $post): array
    {
        $mediaIds = [];

        foreach ($imagePaths as $index => $path) {
            $imageUrl = $this->buildPublicImageUrl($path);

            try {
                RateLimiter::hit(self::RATE_LIMIT_KEY, 3600);

                $response = $this->httpPost(self::GRAPH_BASE . '/' . $pageId . '/photos', [
                    'url'          => $imageUrl,
                    'published'    => 'false',
                    'access_token' => $token,
                ]);

                $this->log($post->id, 'fb_upload_unpublished_' . $index, $response->successful() ? 'success' : 'failed', [
                    'image_url' => $imageUrl,
                ], $response, null);

                if ($response->failed()) {
                    $err = (string) ($response->json('error.message') ?? 'Bilinmeyen hata');
                    $post->update(['fb_error_message' => "Facebook görsel #{$index} yüklenemedi: {$err}"]);

                    return ['success' => false, 'message' => $err];
                }

                $mediaId = (string) ($response->json('id') ?? '');
                if ($mediaId === '') {
                    $post->update(['fb_error_message' => "Facebook görsel #{$index} için media_id alınamadı."]);

                    return ['success' => false, 'message' => 'media_id alınamadı'];
                }

                $mediaIds[] = $mediaId;
            } catch (ConnectionException $e) {
                $post->update(['fb_error_message' => "Görsel #{$index} bağlantı hatası: " . $e->getMessage()]);

                return ['success' => false, 'message' => $e->getMessage()];
            }
        }

        // Parent feed post
        $start = hrtime(true);
        try {
            RateLimiter::hit(self::RATE_LIMIT_KEY, 3600);

            $attachedMedia = array_map(fn ($id) => json_encode(['media_fbid' => $id]), $mediaIds);

            $payload = [
                'message'      => $caption,
                'access_token' => $token,
            ];
            foreach ($attachedMedia as $i => $am) {
                $payload["attached_media[{$i}]"] = $am;
            }

            $response = $this->httpPost(self::GRAPH_BASE . '/' . $pageId . '/feed', $payload);

            $duration = (int) ((hrtime(true) - $start) / 1_000_000);
            $this->log($post->id, 'fb_publish_multi', $response->successful() ? 'success' : 'failed', [
                'media_count' => count($mediaIds),
            ], $response, $duration);

            if ($response->failed()) {
                $err = (string) ($response->json('error.message') ?? 'Bilinmeyen hata');
                $post->update(['fb_error_message' => "Facebook çoklu paylaşım: {$err}"]);

                return ['success' => false, 'message' => $err];
            }

            $fbPostId = (string) ($response->json('id') ?? '');
            $permalink = $this->fetchPermalink($fbPostId, $token);

            $post->update([
                'fb_post_id'       => $fbPostId,
                'fb_permalink'     => $permalink,
                'fb_published_at'  => now(),
                'fb_error_message' => null,
            ]);

            return ['success' => true, 'message' => 'Facebook\'ta çoklu görsel paylaşıldı.'];
        } catch (ConnectionException $e) {
            $post->update(['fb_error_message' => 'Multi-photo feed bağlantı hatası: ' . $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Facebook Page Reels — 3 fazlı API.
     *
     * Phase 1 (start): /{page-id}/video_reels?upload_phase=start → video_id + upload_url
     * Phase 2 (transfer): {upload_url} ile file_url verilir (Meta videoyu indirir)
     * Phase 3 (finish): /{page-id}/video_reels?upload_phase=finish&video_state=PUBLISHED
     *
     * Meta video processing async — finish çağrısı başarı dönerse "yayınlandı" sayılır,
     * gerçek publish 1-2 dakika sonra olabilir. permalink fetch sonradan retry'lı.
     *
     * @return array{success: bool, message: string}
     */
    private function publishReels(string $pageId, string $token, InstagramPost $post, string $caption): array
    {
        try {
            $videoUrl = $this->buildPublicVideoUrl($post->video_path);
        } catch (\RuntimeException $e) {
            $msg = 'FB Reels video URL geçersiz: ' . $e->getMessage();
            $post->update(['fb_error_message' => $msg]);

            return ['success' => false, 'message' => $msg];
        }

        // ─── Phase 1: start ───
        $videoId = $this->reelsPhaseStart($pageId, $token, $post);
        if ($videoId === null) {
            return ['success' => false, 'message' => $post->fb_error_message ?? 'Reels Phase 1 başarısız.'];
        }

        // ─── Phase 2: transfer (file_url) ───
        if (! $this->reelsPhaseTransfer($pageId, $token, $videoId, $videoUrl, $post)) {
            return ['success' => false, 'message' => $post->fb_error_message ?? 'Reels Phase 2 başarısız.'];
        }

        // ─── Phase 3: finish (publish) ───
        if (! $this->reelsPhaseFinish($pageId, $token, $videoId, $caption, $post)) {
            return ['success' => false, 'message' => $post->fb_error_message ?? 'Reels Phase 3 başarısız.'];
        }

        // Başarılı — fb_post_id olarak video_id, permalink async (varsa)
        $permalink = $this->fetchPermalink($videoId, $token);

        $post->update([
            'fb_post_id'       => $videoId,
            'fb_permalink'     => $permalink,
            'fb_published_at'  => now(),
            'fb_error_message' => null,
        ]);

        return ['success' => true, 'message' => 'Facebook Reels yayınlandı (Meta processing 1-2 dk sürebilir).'];
    }

    /**
     * Phase 1 — Reels container başlat.
     *
     * @return string|null video_id veya hata
     */
    private function reelsPhaseStart(string $pageId, string $token, InstagramPost $post): ?string
    {
        $start = hrtime(true);

        try {
            RateLimiter::hit(self::RATE_LIMIT_KEY, 3600);

            $response = $this->httpPost(self::GRAPH_BASE . '/' . $pageId . '/video_reels', [
                'upload_phase' => 'start',
                'access_token' => $token,
            ]);

            $duration = (int) ((hrtime(true) - $start) / 1_000_000);
            $this->log($post->id, 'fb_publish_reels_start', $response->successful() ? 'success' : 'failed', null, $response, $duration);

            if ($response->failed()) {
                $err = (string) ($response->json('error.message') ?? 'Bilinmeyen hata');
                $msg = "FB Reels Phase 1 (start) başarısız: {$err}";
                $post->update(['fb_error_message' => $msg]);

                return null;
            }

            $videoId = (string) ($response->json('video_id') ?? '');
            if ($videoId === '') {
                $msg = 'FB Reels Phase 1 başarılı ama video_id boş döndü.';
                $post->update(['fb_error_message' => $msg]);

                return null;
            }

            return $videoId;
        } catch (ConnectionException $e) {
            $msg = 'FB Reels Phase 1 bağlantı hatası: ' . $e->getMessage();
            $post->update(['fb_error_message' => $msg]);
            $this->log($post->id, 'fb_publish_reels_start', 'failed', null, null, null, $e->getMessage());

            return null;
        }
    }

    /**
     * Phase 2 — Hosted file_url ile video upload.
     * Meta dokümana göre transfer endpoint'i: rupload.facebook.com/video-upload/...
     * Hosted URL ile başka bir host'tan upload yapıldığı için ayrı bir endpoint.
     */
    private function reelsPhaseTransfer(string $pageId, string $token, string $videoId, string $videoUrl, InstagramPost $post): bool
    {
        $start = hrtime(true);
        $transferUrl = "https://rupload.facebook.com/video-upload/v21.0/{$videoId}";

        try {
            // Transfer endpoint custom headers ister:
            //   Authorization: OAuth {access_token}
            //   file_url: {public video URL}
            $response = Http::withHeaders([
                'Authorization' => "OAuth {$token}",
                'file_url'      => $videoUrl,
            ])
                ->timeout(120)
                ->retry(2, 3000, function (\Throwable $e): bool {
                    // Laravel retry'ın 2. argümanı PendingRequest'tir;
                    // status kontrolünü exception üzerinden yapıyoruz.
                    if ($e instanceof ConnectionException) {
                        return true;
                    }
                    if ($e instanceof RequestException && $e->response !== null) {
                        return $e->response->status() >= 500;
                    }
                    return false;
                })
                ->post($transferUrl);

            $duration = (int) ((hrtime(true) - $start) / 1_000_000);
            $this->log($post->id, 'fb_publish_reels_transfer', $response->successful() ? 'success' : 'failed', [
                'video_id'  => $videoId,
                'video_url' => $videoUrl,
            ], $response, $duration);

            if ($response->failed()) {
                $err = (string) ($response->json('error.message') ?? $response->body());
                $post->update(['fb_error_message' => "FB Reels Phase 2 (transfer) başarısız: {$err}"]);

                return false;
            }

            $success = (bool) ($response->json('success') ?? false);
            if (! $success) {
                $post->update(['fb_error_message' => 'FB Reels Phase 2 success=false döndü.']);

                return false;
            }

            return true;
        } catch (ConnectionException $e) {
            $post->update(['fb_error_message' => 'FB Reels Phase 2 bağlantı hatası: ' . $e->getMessage()]);
            $this->log($post->id, 'fb_publish_reels_transfer', 'failed', null, null, null, $e->getMessage());

            return false;
        }
    }

    /**
     * Phase 3 — finish + PUBLISHED state.
     */
    private function reelsPhaseFinish(string $pageId, string $token, string $videoId, string $caption, InstagramPost $post): bool
    {
        $start = hrtime(true);

        try {
            RateLimiter::hit(self::RATE_LIMIT_KEY, 3600);

            $response = $this->httpPost(self::GRAPH_BASE . '/' . $pageId . '/video_reels', [
                'upload_phase' => 'finish',
                'video_id'     => $videoId,
                'video_state'  => 'PUBLISHED',
                'description'  => $caption,
                'access_token' => $token,
            ]);

            $duration = (int) ((hrtime(true) - $start) / 1_000_000);
            $this->log($post->id, 'fb_publish_reels_finish', $response->successful() ? 'success' : 'failed', [
                'video_id'         => $videoId,
                'description_length' => strlen($caption),
            ], $response, $duration);

            if ($response->failed()) {
                $err = (string) ($response->json('error.message') ?? 'Bilinmeyen hata');
                $post->update(['fb_error_message' => "FB Reels Phase 3 (finish) başarısız: {$err}"]);

                return false;
            }

            return true;
        } catch (ConnectionException $e) {
            $post->update(['fb_error_message' => 'FB Reels Phase 3 bağlantı hatası: ' . $e->getMessage()]);
            $this->log($post->id, 'fb_publish_reels_finish', 'failed', null, null, null, $e->getMessage());

            return false;
        }
    }

    private function fetchPermalink(string $fbPostId, string $token): ?string
    {
        if ($fbPostId === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)->get(self::GRAPH_BASE . '/' . $fbPostId, [
                'fields'       => 'permalink_url',
                'access_token' => $token,
            ]);

            if ($response->successful()) {
                $url = $response->json('permalink_url');

                return is_string($url) ? $url : null;
            }
        } catch (\Throwable) {
            // ignore
        }

        return null;
    }

    private function getPageId(): ?string
    {
        $value = Setting::getValue('instagram_facebook_page_id');

        return empty($value) ? null : (string) $value;
    }

    private function getPageToken(): ?string
    {
        $value = Setting::getValue('instagram_facebook_page_token');
        if (! empty($value)) {
            return (string) $value;
        }

        // Fallback: user token (Meta bazen page-level operations için kabul eder)
        $userToken = Setting::getValue('instagram_access_token');

        return empty($userToken) ? null : (string) $userToken;
    }

    private function buildPublicImageUrl(string $imagePath): string
    {
        return $this->resolvePublicUrl(UploadService::url($imagePath, 'lg'), 'görsel');
    }

    private function buildPublicVideoUrl(string $videoPath): string
    {
        return $this->resolvePublicUrl(UploadService::url($videoPath), 'video');
    }

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
            throw new \RuntimeException("Meta API localhost URL'lere erişemez: {$finalUrl}");
        }

        return $finalUrl;
    }

    /**
     * Facebook Graph API tüm POST endpoint'lerinde application/x-www-form-urlencoded bekler.
     * JSON gövde ile gönderirsen attached_media[N] gibi bracket'lı key'ler doğru parse
     * edilmez ve görseller post'a bağlanmadan, sadece message ile text post oluşur.
     */
    private function httpPost(string $url, array $data = []): Response
    {
        return Http::asForm()
            ->timeout(30)
            ->retry(3, 2000, fn (\Throwable $e): bool => $this->shouldRetry($e))
            ->post($url, $data);
    }

    /**
     * Laravel retry callback'in 2. argümanı PendingRequest'tir (Response değil),
     * bu yüzden response status'ünü exception üzerinden kontrol ediyoruz.
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
            Log::warning('Facebook log kaydedilemedi', ['error' => $e->getMessage()]);
        }
    }
}
