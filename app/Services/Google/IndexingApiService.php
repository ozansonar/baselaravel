<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Models\GoogleApiLog;
use App\Models\IndexingRequest;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Google Indexing API — yeni/güncellenen içeriği Google'a "tara" emri gönderir.
 *
 * Endpoint: POST https://indexing.googleapis.com/v3/urlNotifications:publish
 * Scope:    https://www.googleapis.com/auth/indexing
 *
 * Plan dosyasına uygun (🚨 Hata Yönetimi):
 *   - 200 → IndexingRequest.status = success
 *   - 4xx → status = failed (kalıcı, retry yok)
 *   - 429/5xx → exception throw, queue worker retry alacak
 */
final class IndexingApiService
{
    private const string API_URL = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
    private const string SCOPE   = 'https://www.googleapis.com/auth/indexing';

    public function __construct(
        private readonly GoogleAuthService $auth,
    ) {}

    public function isEnabled(): bool
    {
        $flag = Setting::getValue('google_indexing_enabled', '0');

        return $this->auth->isConfigured() && $flag === '1';
    }

    /**
     * Submit a URL to Google Indexing API. Updates the IndexingRequest row
     * with the result. Throws on retriable errors (queue worker retries).
     */
    public function submit(IndexingRequest $request): void
    {
        if (! $this->isEnabled()) {
            $request->update([
                'status'        => IndexingRequest::STATUS_FAILED,
                'error_message' => 'Indexing API kapalı veya yapılandırılmamış',
            ]);

            return;
        }

        $token   = $this->auth->preferredAccessToken(self::SCOPE);
        $payload = [
            'url'  => $request->url,
            'type' => $request->type,
        ];

        $start = microtime(true);

        try {
            $response = Http::withToken($token)
                ->timeout((int) env('GOOGLE_API_TIMEOUT', 30))
                ->acceptJson()
                ->asJson()
                ->post(self::API_URL, $payload);

            $duration = (int) round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $request->update([
                    'status'   => IndexingRequest::STATUS_SUCCESS,
                    'response' => $response->json(),
                    'sent_at'  => now(),
                ]);

                $this->log('success', 'submit', $response->status(), null, $payload, strlen((string) $response->body()), $duration);

                return;
            }

            $isRetriable = in_array($response->status(), [429, 500, 502, 503, 504], true);
            $errorMsg    = "HTTP {$response->status()} — " . $response->body();

            if ($isRetriable) {
                $request->update([
                    'status'        => IndexingRequest::STATUS_PENDING,
                    'error_message' => mb_substr($errorMsg, 0, 1000),
                ]);

                $this->log('failed', 'submit', $response->status(), $errorMsg, $payload, null, $duration);

                throw new RuntimeException("Indexing API geçici hata, retry: {$errorMsg}");
            }

            $request->update([
                'status'        => IndexingRequest::STATUS_FAILED,
                'error_message' => mb_substr($errorMsg, 0, 1000),
                'sent_at'       => now(),
            ]);

            $this->log('failed', 'submit', $response->status(), $errorMsg, $payload, null, $duration);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);

            $request->update([
                'status'        => IndexingRequest::STATUS_PENDING,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            $this->log('failed', 'submit', null, $e->getMessage(), $payload, null, $duration);

            throw $e;
        }
    }

    /**
     * Queue a URL for Google indexing. Idempotent — if a pending/sent
     * request exists for the same URL within the last 24h, no-op.
     */
    public function queue(string $url, string $type, ?string $relatedType = null, ?int $relatedId = null): IndexingRequest
    {
        $existing = IndexingRequest::query()
            ->where('url', $url)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subHours(24))
            ->whereIn('status', [IndexingRequest::STATUS_PENDING, IndexingRequest::STATUS_SENT, IndexingRequest::STATUS_SUCCESS])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return IndexingRequest::create([
            'url'          => mb_substr($url, 0, 1000),
            'type'         => $type,
            'status'       => IndexingRequest::STATUS_PENDING,
            'related_type' => $relatedType,
            'related_id'   => $relatedId,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function log(
        string $status,
        string $operation,
        ?int $httpCode,
        ?string $errorMessage,
        ?array $payload,
        ?int $responseSize,
        int $durationMs,
    ): void {
        try {
            GoogleApiLog::create([
                'service'         => GoogleApiLog::SERVICE_INDEXING,
                'operation'       => $operation,
                'status'          => $status,
                'http_code'       => $httpCode,
                'error_message'   => $errorMessage !== null ? mb_substr($errorMessage, 0, 2000) : null,
                'request_payload' => $payload,
                'response_size'   => $responseSize,
                'duration_ms'     => $durationMs,
            ]);
        } catch (Throwable $e) {
            Log::warning('GoogleApiLog write failed: ' . $e->getMessage());
        }
    }
}
