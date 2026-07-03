<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Central AI content generation service.
 *
 * Single entry point for all AI calls (products, blog, etc.).
 * Handles model fallback, retry with jitter, timeout and logging.
 *
 * Settings (admin panel → AI):
 *   - gemini_api_key:     Google AI Studio API key
 *   - ai_primary_model:   first model to try, e.g. gemini-2.5-flash
 *   - ai_fallback_models: comma-separated fallback list
 *   - ai_max_attempts:    retries per model on 5xx/429 (default 5)
 *   - ai_timeout_seconds: HTTP timeout per attempt (default 90)
 *   - ai_initial_backoff: base delay in seconds for exponential backoff (default 4)
 *   - ai_total_budget:    hard upper bound on total wall-clock time (default 240s)
 */
final class AiService
{
    private const string BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /** Status codes worth retrying with backoff (server errors + rate limit). */
    private const array RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    private const string DEFAULT_PRIMARY_MODEL = 'gemini-2.5-flash';

    private const string DEFAULT_FALLBACK_MODELS = 'gemini-2.0-flash,gemini-2.5-flash-lite,gemini-2.0-flash-lite,gemini-1.5-flash';

    private const int DEFAULT_MAX_ATTEMPTS = 5;

    private const int DEFAULT_TIMEOUT = 90;

    private const int DEFAULT_INITIAL_BACKOFF = 4;

    /** Hard cap on total elapsed time (seconds) across all models and retries. */
    private const int DEFAULT_TOTAL_BUDGET = 240;

    /**
     * Generate content. Callers supply only the prompt pair; everything else
     * (model choice, retry, fallback, logging) is handled here.
     *
     * @param  array<string, mixed>|null $responseSchema  Gemini structured-output JSON schema.
     *                                                    Verilirse model bu şemaya uymak ZORUNDA olur,
     *                                                    invalid JSON dönemez. Örnek:
     *                                                    [
     *                                                      'type' => 'OBJECT',
     *                                                      'properties' => [
     *                                                        'title'   => ['type' => 'STRING'],
     *                                                        'content' => ['type' => 'STRING'],
     *                                                      ],
     *                                                      'required' => ['title', 'content'],
     *                                                    ]
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     content: string|null,
     *     duration_ms: int|null,
     *     token_count: int|null,
     *     used_model: string|null,
     *     attempt_count: int|null
     * }
     */
    public function generate(
        string $systemInstruction,
        string $userPrompt,
        ?float $temperature = null,
        ?array $responseSchema = null,
        ?string $imagePath = null,
    ): array {
        $apiKey = (string) Setting::getValue('gemini_api_key', '');

        if ($apiKey === '') {
            return $this->failure('Gemini API anahtarı tanımlanmamış. Admin panelden ayarlayın.');
        }

        $models = $this->resolveModels();
        $maxAttempts = $this->resolveInt('ai_max_attempts', self::DEFAULT_MAX_ATTEMPTS, 1, 10);
        $timeout = $this->resolveInt('ai_timeout_seconds', self::DEFAULT_TIMEOUT, 10, 300);
        $initialBackoff = $this->resolveInt('ai_initial_backoff', self::DEFAULT_INITIAL_BACKOFF, 1, 30);
        $totalBudget = $this->resolveInt('ai_total_budget_seconds', self::DEFAULT_TOTAL_BUDGET, 30, 600);

        $combinedPrompt = "SİSTEM TALİMATI: {$systemInstruction}. Lütfen bu role ve kurallara kesinlikle uyarak aşağıdaki istemi yanıtla: \n\nİSTEM: {$userPrompt}";

        // Temperature: 0.0 (deterministic) ile 1.0 (yaratıcı) arasında. Default 0.7 dengeli.
        $effectiveTemperature = $temperature !== null
            ? max(0.0, min(1.0, $temperature))
            : 0.7;

        $generationConfig = ['temperature' => $effectiveTemperature];

        // Structured output: caller bir JSON schema verirse, model bu şemaya
        // uymak ZORUNDA olur — Gemini'nin tarafında garanti edilir, "missing
        // comma" gibi syntax bug'ları imkansız hale gelir.
        if ($responseSchema !== null) {
            $generationConfig['responseMimeType'] = 'application/json';
            $generationConfig['responseSchema']   = $responseSchema;
        }

        $parts = [];
        if ($imagePath !== null) {
            $imageData = $this->readImageAsBase64($imagePath);
            if ($imageData !== null) {
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => $imageData['mime'],
                        'data'     => $imageData['base64'],
                    ],
                ];
            }
        }
        $parts[] = ['text' => $combinedPrompt];

        if ($imagePath !== null) {
            $generationConfig['thinkingConfig'] = ['thinkingBudget' => 0];
        }

        $requestBody = [
            'generationConfig' => $generationConfig,
            'contents' => [
                ['role' => 'user', 'parts' => $parts],
            ],
        ];

        $startTime = hrtime(true);
        $deadline = $startTime + ($totalBudget * 1_000_000_000);
        $totalAttempts = 0;
        $lastFailure = null;
        $budgetExhausted = false;

        foreach ($models as $model) {
            if (hrtime(true) >= $deadline) {
                $budgetExhausted = true;
                Log::warning('AI toplam süre bütçesi doldu, sonraki modele geçilmiyor', [
                    'skipped_model'  => $model,
                    'budget_seconds' => $totalBudget,
                ]);
                break;
            }

            $result = $this->callModelWithRetry(
                $model,
                $apiKey,
                $requestBody,
                $maxAttempts,
                $timeout,
                $initialBackoff,
                $deadline,
            );
            $totalAttempts += $result['attempts'];

            if ($result['success']) {
                $tokenCount = (int) $result['token_count'];

                return [
                    'success'       => true,
                    'message'       => 'İçerik başarıyla oluşturuldu.',
                    'content'       => $result['content'],
                    'duration_ms'   => (int) ((hrtime(true) - $startTime) / 1_000_000),
                    'token_count'   => $tokenCount,
                    'cost_usd'      => self::calculateCost($tokenCount, $model),
                    'used_model'    => $model,
                    'attempt_count' => $totalAttempts,
                ];
            }

            $lastFailure = $result;

            // Only fall through to next model for retryable/connection errors.
            // Non-retryable errors (auth, bad request) should stop immediately.
            if (! $result['should_fallback']) {
                break;
            }

            Log::warning('AI model başarısız, fallback modele geçiliyor', [
                'failed_model' => $model,
                'status'       => $result['status'],
                'message'      => $result['message'],
            ]);
        }

        if ($budgetExhausted && $lastFailure !== null) {
            $lastFailure['message'] = 'Toplam süre bütçesi (' . $totalBudget . 's) doldu. '
                . ($lastFailure['message'] ?? 'Tüm denemeler başarısız.');
        }

        return [
            'success'       => false,
            'message'       => $lastFailure['message'] ?? 'AI çağrısı başarısız oldu.',
            'content'       => $lastFailure['raw_body'] ?? null,
            'duration_ms'   => (int) ((hrtime(true) - $startTime) / 1_000_000),
            'token_count'   => $lastFailure['token_count'] ?? null,
            'used_model'    => $lastFailure['model'] ?? null,
            'attempt_count' => $totalAttempts,
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveModels(): array
    {
        $primary = trim((string) Setting::getValue('ai_primary_model', self::DEFAULT_PRIMARY_MODEL));
        if ($primary === '') {
            $primary = self::DEFAULT_PRIMARY_MODEL;
        }

        $fallbackRaw = (string) Setting::getValue('ai_fallback_models', self::DEFAULT_FALLBACK_MODELS);
        $fallbacks = array_values(array_filter(array_map(
            static fn (string $m): string => trim($m),
            explode(',', $fallbackRaw),
        )));

        $ordered = array_merge([$primary], $fallbacks);

        return array_values(array_unique($ordered));
    }

    private function resolveInt(string $key, int $default, int $min, int $max): int
    {
        $value = Setting::getValue($key);
        if ($value === null || $value === '') {
            return $default;
        }
        $int = (int) $value;

        return max($min, min($max, $int));
    }

    /**
     * Exponential backoff retry with jitter, for one specific model.
     *
     * @param  array<string, mixed>  $body
     * @return array{
     *     success: bool,
     *     content?: string,
     *     token_count?: int|null,
     *     raw_body?: string|null,
     *     message?: string,
     *     status?: int|null,
     *     model: string,
     *     attempts: int,
     *     should_fallback: bool
     * }
     */
    private function callModelWithRetry(
        string $model,
        string $apiKey,
        array $body,
        int $maxAttempts,
        int $timeout,
        int $initialBackoff,
        float $deadlineNs,
    ): array {
        $url = self::BASE_URL . $model . ':generateContent?key=' . $apiKey;
        $lastStatus = null;
        $lastMessage = null;
        $lastRawBody = null;
        $lastTokenCount = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if (hrtime(true) >= $deadlineNs) {
                Log::warning('AI bütçe doldu, retry iptal', [
                    'model'    => $model,
                    'attempts' => $attempt - 1,
                ]);
                break;
            }

            // Clamp per-attempt HTTP timeout to remaining budget (with small safety margin).
            $remainingSeconds = max(1, (int) floor(($deadlineNs - hrtime(true)) / 1_000_000_000));
            $effectiveTimeout = min($timeout, $remainingSeconds);

            try {
                $response = Http::timeout($effectiveTimeout)->post($url, $body);

                if ($response->successful()) {
                    $responseText = $this->extractContentText($response->json());
                    $tokenCount = $response->json('usageMetadata.totalTokenCount');
                    $tokenCountInt = $tokenCount !== null ? (int) $tokenCount : null;

                    if (empty($responseText)) {
                        return [
                            'success'        => false,
                            'message'        => 'API yanıt verdi ancak geçerli içerik oluşturulamadı.',
                            'raw_body'       => $response->body(),
                            'status'         => $response->status(),
                            'token_count'    => $tokenCountInt,
                            'model'          => $model,
                            'attempts'       => $attempt,
                            'should_fallback' => true,
                        ];
                    }

                    return [
                        'success'     => true,
                        'content'     => trim((string) $responseText),
                        'token_count' => $tokenCountInt,
                        'model'       => $model,
                        'attempts'    => $attempt,
                        'should_fallback' => false,
                    ];
                }

                $lastStatus = $response->status();
                $lastRawBody = $response->body();
                $lastMessage = $this->humanizeApiError($response);
                $lastTokenCount = null;

                // Non-retryable → return immediately, do not fall back for auth/bad request
                if (! in_array($lastStatus, self::RETRYABLE_STATUSES, true)) {
                    return [
                        'success'        => false,
                        'message'        => $lastMessage,
                        'raw_body'       => $lastRawBody,
                        'status'         => $lastStatus,
                        'token_count'    => null,
                        'model'          => $model,
                        'attempts'       => $attempt,
                        'should_fallback' => in_array($lastStatus, [400, 404], true), // bad request / model not found → try next model
                    ];
                }

                Log::warning('AI retry olacak', [
                    'model'   => $model,
                    'attempt' => $attempt,
                    'status'  => $lastStatus,
                    'body'    => mb_substr((string) $lastRawBody, 0, 300),
                ]);
            } catch (ConnectionException $e) {
                $lastStatus = null;
                $lastMessage = 'Gemini API\'ye bağlanılamadı: ' . $e->getMessage();
                Log::warning('AI bağlantı hatası, retry', [
                    'model'   => $model,
                    'attempt' => $attempt,
                    'error'   => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                $lastStatus = null;
                $lastMessage = 'Beklenmedik hata: ' . $e->getMessage();
                Log::error('AI beklenmedik hata', [
                    'model'   => $model,
                    'attempt' => $attempt,
                    'error'   => $e->getMessage(),
                ]);
            }

            if ($attempt < $maxAttempts) {
                $this->sleepWithJitter($initialBackoff, $attempt, $deadlineNs);
            }
        }

        return [
            'success'         => false,
            'message'         => $lastMessage ?? 'AI çağrısı başarısız oldu.',
            'raw_body'        => $lastRawBody,
            'status'          => $lastStatus,
            'token_count'     => $lastTokenCount,
            'model'           => $model,
            'attempts'        => $maxAttempts,
            'should_fallback' => true,
        ];
    }

    /**
     * Exponential backoff with ±25% jitter, clamped to remaining budget.
     * attempt=1 → base, attempt=2 → 2·base, attempt=3 → 4·base, capped at 60s.
     * Skipped entirely if fewer than 2 seconds remain in the budget.
     */
    private function sleepWithJitter(int $baseSeconds, int $attempt, float $deadlineNs): void
    {
        $delay = min(60, $baseSeconds * (2 ** ($attempt - 1)));
        $jitter = (int) round($delay * (mt_rand(-25, 25) / 100));
        $total = max(1, $delay + $jitter);

        $remainingSeconds = (int) floor(($deadlineNs - hrtime(true)) / 1_000_000_000);
        if ($remainingSeconds < 2) {
            return;
        }

        sleep(min($total, $remainingSeconds - 1));
    }

    /**
     * Translate common Gemini HTTP errors into Turkish user-friendly messages.
     */
    private function humanizeApiError(Response $response): string
    {
        $status = $response->status();
        $apiMessage = (string) ($response->json('error.message') ?? '');

        return match (true) {
            $status === 503 => 'Gemini modeli şu anda yüksek yoğunlukta. Lütfen birkaç dakika sonra tekrar deneyin.',
            $status === 429 => 'API istek limiti aşıldı. Lütfen biraz bekleyip tekrar deneyin.',
            in_array($status, [500, 502, 504], true) => 'Gemini servisi geçici olarak erişilemez durumda (HTTP ' . $status . '). Lütfen tekrar deneyin.',
            $status === 401 || $status === 403 => 'Gemini API anahtarı geçersiz veya yetki yok. Admin panelden kontrol edin.',
            $status === 404 => 'Seçilen model bulunamadı (HTTP 404). Ayarlardan model isimlerini kontrol edin.',
            $status === 400 => 'İstek hatalı: ' . ($apiMessage !== '' ? $apiMessage : 'bilinmeyen sebep'),
            default => 'API Hatası (' . $status . '): ' . ($apiMessage !== '' ? $apiMessage : 'bilinmeyen hata'),
        };
    }

    /**
     * @return array{success: false, message: string, content: null, duration_ms: null, token_count: null, used_model: null, attempt_count: null}
     */
    private function failure(string $message): array
    {
        return [
            'success'       => false,
            'message'       => $message,
            'content'       => null,
            'duration_ms'   => null,
            'token_count'   => null,
            'used_model'    => null,
            'attempt_count' => null,
        ];
    }

    private function extractContentText(mixed $json): ?string
    {
        $parts = $json['candidates'][0]['content']['parts'] ?? [];
        if (! is_array($parts)) {
            return null;
        }

        $texts = [];
        foreach ($parts as $part) {
            if (! empty($part['thought'])) {
                continue;
            }
            if (isset($part['text']) && is_string($part['text']) && $part['text'] !== '') {
                $texts[] = $part['text'];
            }
        }

        return $texts !== [] ? implode("\n", $texts) : null;
    }

    /**
     * @return array{mime: string, base64: string}|null
     */
    private function readImageAsBase64(string $path): ?array
    {
        $fullPath = public_path('uploads/' . ltrim($path, '/'));
        if (! is_file($fullPath)) {
            return null;
        }
        $bytes = file_get_contents($fullPath);
        if ($bytes === false) {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'image/png',
        };

        return ['mime' => $mime, 'base64' => base64_encode($bytes)];
    }

    /**
     * Token sayısı + model adı → USD maliyeti hesapla.
     *
     * config/ai-pricing.php dosyasındaki blended rate ($/1M token) kullanılır.
     * Bilinmeyen model için _default rate.
     *
     * Static method — hem bu serviste hem dışarıdan (cost report) çağrılabilir.
     */
    public static function calculateCost(int $tokenCount, ?string $model): float
    {
        if ($tokenCount <= 0) {
            return 0.0;
        }

        $rates = (array) config('ai-pricing.text_models', []);
        $key = $model !== null && $model !== '' ? mb_strtolower($model) : '_default';
        $rate = (float) ($rates[$key] ?? $rates['_default'] ?? 1.00);

        return round(($tokenCount / 1_000_000) * $rate, 6);
    }
}
