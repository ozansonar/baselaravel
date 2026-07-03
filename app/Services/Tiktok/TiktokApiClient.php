<?php

declare(strict_types=1);

namespace App\Services\Tiktok;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TikTok Content Posting API düşük seviye HTTP wrapper.
 *
 * docs/tiktok.md Bölüm 4.2 — sorumluluğu sadece REST çağrıları.
 * Yüksek seviye iş mantığı TiktokService'te.
 */
final class TiktokApiClient
{
    private const HTTP_TIMEOUT = 30;
    private const RETRY_TIMES = 2;
    private const RETRY_SLEEP_MS = 1000;

    public function __construct(
        private readonly string $accessToken,
        private readonly string $baseUrl,
    ) {}

    /**
     * Photo Mode yayını (slideshow).
     * POST /v2/post/publish/content/init/
     *
     * @param  list<string>  $imageUrls  Public erişilebilir görsel URL'leri (max 35)
     * @param  array<string, mixed>  $options
     * @return array{publish_id: string}
     */
    public function publishPhoto(string $caption, array $imageUrls, array $options = []): array
    {
        $payload = [
            'post_info' => array_merge([
                'title'                  => $caption,
                'description'            => '',
                'privacy_level'          => $options['privacy_level']    ?? 'PUBLIC_TO_EVERYONE',
                'disable_comment'        => $options['disable_comment']  ?? false,
                'auto_add_music'         => $options['auto_add_music']   ?? true,
            ]),
            'source_info' => [
                'source'             => 'PULL_FROM_URL',
                'photo_cover_index'  => 0,
                'photo_images'       => array_map(static fn (string $url): array => ['url' => $url], array_slice($imageUrls, 0, 35)),
            ],
            'post_mode'   => 'DIRECT_POST',
            'media_type'  => 'PHOTO',
        ];

        $response = $this->post('/v2/post/publish/content/init/', $payload);
        $data = $this->extractData($response, 'publish_id');

        return ['publish_id' => (string) $data['publish_id']];
    }

    /**
     * Video yayını — PULL_FROM_URL ile basit init.
     * POST /v2/post/publish/video/init/
     *
     * @param  array<string, mixed>  $options
     * @return array{publish_id: string}
     */
    public function publishVideo(string $caption, string $videoUrl, array $options = []): array
    {
        $payload = [
            'post_info' => [
                'title'           => $caption,
                'privacy_level'   => $options['privacy_level']    ?? 'PUBLIC_TO_EVERYONE',
                'disable_duet'    => $options['disable_duet']     ?? false,
                'disable_comment' => $options['disable_comment']  ?? false,
                'disable_stitch'  => $options['disable_stitch']   ?? false,
            ],
            'source_info' => [
                'source'    => 'PULL_FROM_URL',
                'video_url' => $videoUrl,
            ],
        ];

        $response = $this->post('/v2/post/publish/video/init/', $payload);
        $data = $this->extractData($response, 'publish_id');

        return ['publish_id' => (string) $data['publish_id']];
    }

    /**
     * Inbox modu — Direct Post yerine drafts'a düşür (audit gerekmez).
     * POST /v2/post/publish/inbox/video/init/
     *
     * @param  array<string, mixed>  $options
     * @return array{publish_id: string}
     */
    public function publishToInbox(string $videoUrl, array $options = []): array
    {
        $payload = [
            'source_info' => [
                'source'    => 'PULL_FROM_URL',
                'video_url' => $videoUrl,
            ],
        ];

        $response = $this->post('/v2/post/publish/inbox/video/init/', $payload);
        $data = $this->extractData($response, 'publish_id');

        return ['publish_id' => (string) $data['publish_id']];
    }

    /**
     * Publish status fetch — async yayının tamamlanıp tamamlanmadığını sorgu.
     * POST /v2/post/publish/status/fetch/
     *
     * @return array{status: string, publicly_available_post_id?: list<string>, fail_reason?: string}
     */
    public function getPublishStatus(string $publishId): array
    {
        $response = $this->post('/v2/post/publish/status/fetch/', [
            'publish_id' => $publishId,
        ]);

        $body = $response->json();
        return (array) ($body['data'] ?? []);
    }

    /**
     * Token refresh — refresh_token ile yeni access_token al.
     * POST /v2/oauth/token/
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, ...}
     */
    public function refreshToken(string $refreshToken, string $clientKey, string $clientSecret): array
    {
        // Refresh endpoint her zaman production domain'inde
        $url = 'https://open.tiktokapis.com/v2/oauth/token/';

        $response = Http::asForm()
            ->timeout(15)
            ->post($url, [
                'client_key'    => $clientKey,
                'client_secret' => $clientSecret,
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        $data = $response->json();
        if (! $response->successful() || ! isset($data['access_token'])) {
            throw new TiktokApiException(
                'Token refresh başarısız: ' . ($data['error_description'] ?? $data['error'] ?? 'unknown'),
                (string) ($data['error'] ?? null),
                $data,
            );
        }

        return [
            'access_token'  => (string) $data['access_token'],
            'refresh_token' => (string) ($data['refresh_token'] ?? $refreshToken),
            'expires_in'    => (int) ($data['expires_in'] ?? 86400),
            'open_id'       => (string) ($data['open_id'] ?? ''),
        ];
    }

    /**
     * Bağlı kullanıcı bilgisi.
     * GET /v2/user/info/
     *
     * @return array<string, mixed>
     */
    public function getUserInfo(): array
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(10)
            ->get($this->baseUrl . '/v2/user/info/', [
                'fields' => 'open_id,union_id,avatar_url,display_name,username',
            ]);

        if (! $response->successful()) {
            throw new TiktokApiException(
                'getUserInfo başarısız: HTTP ' . $response->status(),
                null,
                $response->json() ?: [],
            );
        }

        return (array) ($response->json('data.user') ?: []);
    }

    /**
     * Ortak POST wrapper — auth, retry, timeout, log.
     *
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload): Response
    {
        $url = $this->baseUrl . $path;

        $response = Http::withToken($this->accessToken)
            ->timeout(self::HTTP_TIMEOUT)
            ->retry(self::RETRY_TIMES, self::RETRY_SLEEP_MS, throw: false)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            $body = $response->json() ?: [];
            $errMsg = $body['error']['message']
                ?? $body['error']['code']
                ?? ('HTTP ' . $response->status());

            Log::warning('TikTok API fail', [
                'path'   => $path,
                'status' => $response->status(),
                'body'   => $body,
            ]);

            throw new TiktokApiException(
                'TikTok API hatası: ' . $errMsg,
                (string) ($body['error']['code'] ?? null),
                $body,
            );
        }

        return $response;
    }

    /**
     * Response'tan 'data' bloğunu çıkar + verilen key kontrol et.
     *
     * @return array<string, mixed>
     */
    private function extractData(Response $response, string $requiredKey): array
    {
        $data = $response->json('data') ?: [];
        if (! is_array($data) || ! isset($data[$requiredKey])) {
            throw new TiktokApiException(
                "TikTok response data.{$requiredKey} eksik",
                null,
                $response->json() ?: [],
            );
        }
        return $data;
    }
}
