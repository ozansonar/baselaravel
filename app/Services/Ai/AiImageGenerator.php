<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AiGeneratedImage;
use App\Models\Setting;
use App\Services\UploadService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Single source of truth for ALL AI image generation in the system.
 *
 * Hard isolation from text generation:
 *   - API key:    `ai_image_api_key`     (NEVER falls back to gemini_api_key)
 *   - Toggle:     `ai_image_enabled`      (global on/off — disabling stops every entry point)
 *   - Primary:    `ai_image_model`        (single source — no per-feature primary key anymore)
 *   - Fallbacks:  `ai_image_fallback_models` (CSV)
 *   - Timeout:    `ai_image_timeout`      (seconds, clamped 30-300)
 *
 * Every call records a full row in `ai_generated_images`:
 *   prompt, model, aspect_ratio, width×height, cost_usd, generation_time_ms,
 *   attempt_count, source, request_payload, response_payload, error_message.
 *
 * Supports both endpoint flavors transparently:
 *   - `imagen-*`  → :predict (instances/parameters body)
 *   - `gemini-*`  → :generateContent (contents/parts body)
 */
final class AiImageGenerator
{
    private const string BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

    // Nano Banana 2 (gemini-3.1-flash-image-preview) — varsayılan birincil
    private const string DEFAULT_PRIMARY_MODEL = 'gemini-3.1-flash-image-preview';

    // Sıralı fallback: Nano Banana Pro → Nano Banana (2.5) → preview varyant
    private const string DEFAULT_FALLBACK_MODELS = 'gemini-3-pro-image-preview,gemini-2.5-flash-image,gemini-2.5-flash-image-preview';

    private const int DEFAULT_TIMEOUT = 120;

    /** @var list<string> */
    private const array SUPPORTED_RATIOS = ['1:1', '3:4', '4:3', '9:16', '16:9'];

    /**
     * Per-image cost (USD) — best-effort estimates from Google AI Studio pricing.
     * Unknown models log cost_usd = null (don't block generation).
     *
     * @var array<string, float>
     */
    private const array COST_TABLE = [
        'gemini-2.5-flash-image'         => 0.04,
        'gemini-2.5-flash-image-preview' => 0.04,
        'gemini-3.1-flash-image-preview' => 0.07,
        'gemini-3-pro-image-preview'     => 0.10,
        'imagen-3.0-generate-001'        => 0.04,
        'imagen-3.0-generate-002'        => 0.04,
    ];

    private const int RESPONSE_LOG_LIMIT = 8000;

    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    /**
     * Generate an image from a text prompt.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     image: AiGeneratedImage,
     *     image_path: ?string,
     *     url: ?string,
     *     model_used: ?string,
     *     cost_usd: ?float,
     *     width: ?int,
     *     height: ?int,
     * }
     */
    public function generate(
        string $prompt,
        string $aspectRatio = '1:1',
        string $folder = 'ai-images',
        ?string $slugSuffix = null,
        string $source = 'unknown',
        ?int $userId = null,
        ?int $promptTemplateId = null,
        ?int $blogPostId = null,
        ?int $instagramPostId = null,
    ): array {
        $aspectRatio = in_array($aspectRatio, self::SUPPORTED_RATIOS, true) ? $aspectRatio : '1:1';
        $config = $this->loadConfig();

        $image = AiGeneratedImage::create([
            'prompt_template_id' => $promptTemplateId,
            'final_prompt'       => $prompt,
            'provider'           => 'gemini',
            'model'              => $config['primary'],
            'aspect_ratio'       => $aspectRatio,
            'source'             => $source,
            'status'             => AiGeneratedImage::STATUS_PENDING,
            'attempt_count'      => 0,
            'blog_post_id'       => $blogPostId,
            'instagram_post_id'  => $instagramPostId,
            'created_by'         => $userId,
        ]);

        // Hard guards — fail fast with a clear, user-facing reason.
        if (! $config['enabled']) {
            return $this->failResult($image, 'AI Görsel üretimi pasif durumda. Admin → Ayarlar → AI Görsel Üretimi bölümünden aktif edin.');
        }

        if ($config['apiKey'] === '') {
            return $this->failResult($image, 'AI Görsel API Key tanımlı değil. Admin → Ayarlar → AI Görsel Üretimi bölümünden ekleyin (Gemini text key\'inden farklı, ayrı bir key olmalı).');
        }

        // Aylık bütçe kontrolü — block aktifse ve limit aşıldıysa reddet
        $budgetCheck = app(\App\Services\AiCostReportService::class)->shouldBlockImageGeneration();
        if ($budgetCheck['blocked']) {
            return $this->failResult($image, $budgetCheck['message']);
        }

        $promptTrimmed = trim($prompt);
        if (mb_strlen($promptTrimmed) < 5) {
            return $this->failResult($image, 'Prompt çok kısa (min 5 karakter).');
        }
        if (mb_strlen($promptTrimmed) > 2000) {
            return $this->failResult($image, 'Prompt çok uzun (max 2000 karakter).');
        }

        $models = $this->buildModelChain($config['primary'], $config['fallbacks']);
        $lastError = '';
        $attempt = 0;

        foreach ($models as $model) {
            $attempt++;
            $start = hrtime(true);

            try {
                $isImagen = str_starts_with($model, 'imagen-');
                $body = $isImagen
                    ? $this->buildImagenBody($promptTrimmed, $aspectRatio)
                    : $this->buildGeminiBody($promptTrimmed, $aspectRatio);

                $endpoint = self::BASE_URL . $model . ($isImagen ? ':predict' : ':generateContent');

                $response = Http::timeout($config['timeout'])
                    ->withHeaders(['x-goog-api-key' => $config['apiKey']])
                    ->post($endpoint, $body);

                $duration = (int) ((hrtime(true) - $start) / 1_000_000);

                if ($response->status() === 404 || $response->status() === 400) {
                    $lastError = "Model '{$model}' bulunamadı veya desteklenmiyor (HTTP {$response->status()}).";
                    Log::info('AiImageGenerator: model unavailable, trying next', [
                        'model'  => $model,
                        'status' => $response->status(),
                        'source' => $source,
                    ]);
                    continue;
                }

                if ($response->failed()) {
                    $apiError = (string) ($response->json('error.message') ?? $response->body());
                    $code = $response->json('error.code');
                    $message = "Görsel API hatası (HTTP {$response->status()}): {$apiError}";
                    if ($code) {
                        $message .= " [kod: {$code}]";
                    }

                    return $this->failResult($image, $message, $duration, $attempt, $body, $response->json());
                }

                $bytes = $isImagen
                    ? $this->extractImagenBytes($response->json())
                    : $this->extractGeminiBytes($response->json());

                if ($bytes === null) {
                    $textHint = $this->extractTextHint($response->json());
                    $lastError = 'Görsel döndürmedi.' . ($textHint !== null ? ' Yanıt: ' . Str::limit($textHint, 200) : '');
                    Log::info('AiImageGenerator: no image bytes returned', [
                        'model'  => $model,
                        'source' => $source,
                    ]);
                    continue;
                }

                $imagePath = $this->saveImageBytes($bytes, $folder, $slugSuffix ?? $model, $aspectRatio);
                $absolutePath = public_path('uploads/' . $imagePath);
                ['width' => $width, 'height' => $height] = $this->probeDimensions($absolutePath);
                $cost = self::COST_TABLE[$model] ?? null;

                $image->update([
                    'image_path'         => $imagePath,
                    'model'              => $model,
                    'status'             => AiGeneratedImage::STATUS_COMPLETED,
                    'generation_time_ms' => $duration,
                    'cost_usd'           => $cost,
                    'width'              => $width,
                    'height'             => $height,
                    'attempt_count'      => $attempt,
                    'request_payload'    => $this->sanitizeRequestPayload($body),
                    'response_payload'   => $this->sanitizeResponsePayload($response->json()),
                ]);

                $costLabel = $cost !== null ? sprintf(' (~$%.4f)', $cost) : '';

                return [
                    'success'    => true,
                    'message'    => "Görsel üretildi (model: {$model}, {$duration}ms{$costLabel}).",
                    'image'      => $image->fresh(),
                    'image_path' => $imagePath,
                    'url'        => upload_url($imagePath, 'lg'),
                    'model_used' => $model,
                    'cost_usd'   => $cost,
                    'width'      => $width,
                    'height'     => $height,
                ];
            } catch (ConnectionException $e) {
                $lastError = 'Bağlantı hatası: ' . $e->getMessage();
                Log::warning('AiImageGenerator: connection error', [
                    'model'  => $model,
                    'error'  => $e->getMessage(),
                    'source' => $source,
                ]);
                continue;
            } catch (\Throwable $e) {
                Log::error('AiImageGenerator: unexpected exception', [
                    'model'  => $model,
                    'error'  => $e->getMessage(),
                    'source' => $source,
                ]);

                return $this->failResult($image, 'Beklenmedik hata: ' . $e->getMessage(), null, $attempt);
            }
        }

        return $this->failResult(
            $image,
            'Tüm modeller başarısız oldu. Son hata: ' . ($lastError !== '' ? $lastError : 'bilinmeyen.'),
            null,
            $attempt,
        );
    }

    /**
     * Image-to-image flow: send a source image plus an instruction prompt to a Gemini
     * image-capable model. Used by product photo enhancement.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     image: AiGeneratedImage,
     *     image_path: ?string,
     *     url: ?string,
     *     model_used: ?string,
     *     cost_usd: ?float,
     *     width: ?int,
     *     height: ?int,
     * }
     */
    public function enhance(
        string $sourceAbsolutePath,
        string $prompt,
        string $folder,
        ?string $slugSuffix = null,
        string $source = 'unknown',
        ?int $userId = null,
        ?int $promptTemplateId = null,
        ?int $blogPostId = null,
        ?int $instagramPostId = null,
        string $aspectRatio = '1:1',
    ): array {
        $aspectRatio = in_array($aspectRatio, self::SUPPORTED_RATIOS, true) ? $aspectRatio : '1:1';
        $config = $this->loadConfig();

        $image = AiGeneratedImage::create([
            'prompt_template_id' => $promptTemplateId,
            'final_prompt'  => $prompt,
            'provider'      => 'gemini',
            'model'         => $config['primary'],
            'aspect_ratio'  => $aspectRatio,
            'source'        => $source,
            'status'        => AiGeneratedImage::STATUS_PENDING,
            'attempt_count' => 0,
            'created_by'    => $userId,
            'blog_post_id'      => $blogPostId,
            'instagram_post_id' => $instagramPostId,
        ]);

        if (! $config['enabled']) {
            return $this->failResult($image, 'AI Görsel üretimi pasif durumda. Admin → Ayarlar → AI Görsel Üretimi bölümünden aktif edin.');
        }
        if ($config['apiKey'] === '') {
            return $this->failResult($image, 'AI Görsel API Key tanımlı değil. Admin → Ayarlar → AI Görsel Üretimi bölümünden ekleyin.');
        }

        // Aylık bütçe kontrolü — block aktifse ve limit aşıldıysa reddet
        $budgetCheck = app(\App\Services\AiCostReportService::class)->shouldBlockImageGeneration();
        if ($budgetCheck['blocked']) {
            return $this->failResult($image, $budgetCheck['message']);
        }
        if (! is_file($sourceAbsolutePath)) {
            return $this->failResult($image, 'Kaynak görsel bulunamadı.');
        }

        $imageData = file_get_contents($sourceAbsolutePath);
        if ($imageData === false || $imageData === '') {
            return $this->failResult($image, 'Kaynak görsel okunamadı.');
        }

        $base64Source = base64_encode($imageData);
        $mimeType = mime_content_type($sourceAbsolutePath) ?: 'image/jpeg';

        // Enhance only makes sense with Gemini image-edit models — Imagen :predict
        // doesn't accept image inputs in this body shape, skip those in the chain.
        $models = array_values(array_filter(
            $this->buildModelChain($config['primary'], $config['fallbacks']),
            static fn (string $m): bool => ! str_starts_with($m, 'imagen-'),
        ));

        if ($models === []) {
            return $this->failResult($image, 'Görsel iyileştirme için Gemini image-capable model gerekli. Ayarlardaki primary/fallback listede en az bir tane olmalı.');
        }

        $lastError = '';
        $attempt = 0;

        foreach ($models as $model) {
            $attempt++;
            $start = hrtime(true);

            try {
                // Aspect ratio prompt suffix — Gemini imageConfig.aspectRatio bazı
                // image-edit modellerinde sessizce yok sayılır; ek olarak prompt'a
                // da ratio ipucu eklenir.
                $promptWithRatio = $prompt . "\n\n[OUTPUT ASPECT RATIO: {$aspectRatio} — strictly preserve this aspect ratio]";

                $body = [
                    'contents' => [[
                        'parts' => [
                            [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data'     => $base64Source,
                                ],
                            ],
                            ['text' => $promptWithRatio],
                        ],
                    ]],
                    'generationConfig' => [
                        'responseModalities' => ['TEXT', 'IMAGE'],
                        'imageConfig' => [
                            'aspectRatio' => $aspectRatio,
                        ],
                    ],
                ];

                $response = Http::timeout($config['timeout'])
                    ->withHeaders(['x-goog-api-key' => $config['apiKey']])
                    ->post(self::BASE_URL . $model . ':generateContent', $body);

                $duration = (int) ((hrtime(true) - $start) / 1_000_000);

                if ($response->status() === 404 || $response->status() === 400) {
                    $lastError = "Model '{$model}' desteklenmiyor (HTTP {$response->status()}).";
                    continue;
                }

                if ($response->failed()) {
                    $apiError = (string) ($response->json('error.message') ?? $response->body());

                    return $this->failResult($image, "Görsel API hatası: {$apiError}", $duration, $attempt);
                }

                $bytes = $this->extractGeminiBytes($response->json());

                if ($bytes === null) {
                    $hint = $this->extractTextHint($response->json());
                    $lastError = 'Görsel döndürmedi.' . ($hint !== null ? ' Yanıt: ' . Str::limit($hint, 200) : '');
                    continue;
                }

                $imagePath = $this->saveImageBytes($bytes, $folder, $slugSuffix ?? $model, $aspectRatio);
                $absolutePath = public_path('uploads/' . $imagePath);
                ['width' => $width, 'height' => $height] = $this->probeDimensions($absolutePath);
                $cost = self::COST_TABLE[$model] ?? null;

                // Trim base64 source out of stored payload — keeps the row size sane.
                $bodyForLog = $body;
                $bodyForLog['contents'][0]['parts'][0]['inlineData']['data'] = '<base64 stripped>';

                $image->update([
                    'image_path'         => $imagePath,
                    'model'              => $model,
                    'status'             => AiGeneratedImage::STATUS_COMPLETED,
                    'generation_time_ms' => $duration,
                    'cost_usd'           => $cost,
                    'width'              => $width,
                    'height'             => $height,
                    'attempt_count'      => $attempt,
                    'request_payload'    => $bodyForLog,
                    'response_payload'   => $this->sanitizeResponsePayload($response->json()),
                ]);

                $costLabel = $cost !== null ? sprintf(' (~$%.4f)', $cost) : '';

                return [
                    'success'    => true,
                    'message'    => "Görsel iyileştirildi (model: {$model}, {$duration}ms{$costLabel}).",
                    'image'      => $image->fresh(),
                    'image_path' => $imagePath,
                    'url'        => upload_url($imagePath, 'lg'),
                    'model_used' => $model,
                    'cost_usd'   => $cost,
                    'width'      => $width,
                    'height'     => $height,
                ];
            } catch (ConnectionException $e) {
                $lastError = 'Bağlantı hatası: ' . $e->getMessage();
                continue;
            } catch (\Throwable $e) {
                Log::error('AiImageGenerator enhance: unexpected exception', [
                    'model'  => $model,
                    'error'  => $e->getMessage(),
                    'source' => $source,
                ]);

                return $this->failResult($image, 'Beklenmedik hata: ' . $e->getMessage(), null, $attempt);
            }
        }

        return $this->failResult($image, 'Tüm modeller başarısız oldu. Son hata: ' . $lastError, null, $attempt);
    }

    /**
     * @return array{enabled: bool, apiKey: string, primary: string, fallbacks: list<string>, timeout: int}
     */
    private function loadConfig(): array
    {
        $primary = trim((string) Setting::getValue('ai_image_model', self::DEFAULT_PRIMARY_MODEL));
        $fallbackRaw = (string) Setting::getValue('ai_image_fallback_models', self::DEFAULT_FALLBACK_MODELS);
        $fallbacks = array_values(array_filter(array_map('trim', explode(',', $fallbackRaw))));

        $timeoutRaw = (string) Setting::getValue('ai_image_timeout', (string) self::DEFAULT_TIMEOUT);
        $timeout = $timeoutRaw === '' ? self::DEFAULT_TIMEOUT : (int) $timeoutRaw;
        $timeout = max(30, min(300, $timeout));

        return [
            'enabled'   => (string) Setting::getValue('ai_image_enabled', '0') === '1',
            'apiKey'    => trim((string) Setting::getValue('ai_image_api_key', '')),
            'primary'   => $primary !== '' ? $primary : self::DEFAULT_PRIMARY_MODEL,
            'fallbacks' => $fallbacks,
            'timeout'   => $timeout,
        ];
    }

    /**
     * @param  list<string>  $fallbacks
     * @return list<string>
     */
    private function buildModelChain(string $primary, array $fallbacks): array
    {
        return array_values(array_unique(array_filter(array_merge([$primary], $fallbacks))));
    }

    /**
     * @return array{instances: list<array{prompt: string}>, parameters: array<string, mixed>}
     */
    private function buildImagenBody(string $prompt, string $aspectRatio): array
    {
        return [
            'instances'  => [['prompt' => $prompt]],
            'parameters' => [
                'sampleCount'      => 1,
                'aspectRatio'      => $aspectRatio,
                'personGeneration' => 'allow_adult',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGeminiBody(string $prompt, string $aspectRatio): array
    {
        // Gemini image models (Nano Banana family) accept imageConfig.aspectRatio in
        // generationConfig as of late-2025 API updates. Older variants ignore it
        // silently — that's fine, the post-process center-crop guarantees the final
        // file dimensions regardless of which model returns the bytes.
        // Prompt suffix is kept as a redundant hint for older models.
        $promptWithRatio = $prompt . "\n\n[Aspect ratio: {$aspectRatio}]";

        return [
            'contents' => [[
                'role'  => 'user',
                'parts' => [['text' => $promptWithRatio]],
            ]],
            'generationConfig' => [
                'responseModalities' => ['IMAGE'],
                'imageConfig' => [
                    'aspectRatio' => $aspectRatio,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractImagenBytes(?array $json): ?string
    {
        $b64 = $json['predictions'][0]['bytesBase64Encoded'] ?? null;
        if (! is_string($b64) || $b64 === '') {
            return null;
        }
        $decoded = base64_decode($b64, true);

        return $decoded === false ? null : $decoded;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractGeminiBytes(?array $json): ?string
    {
        $parts = $json['candidates'][0]['content']['parts'] ?? [];
        if (! is_array($parts)) {
            return null;
        }
        foreach ($parts as $part) {
            $b64 = $part['inlineData']['data'] ?? $part['inline_data']['data'] ?? null;
            if (is_string($b64) && $b64 !== '') {
                $decoded = base64_decode($b64, true);
                if ($decoded !== false) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractTextHint(?array $json): ?string
    {
        $parts = $json['candidates'][0]['content']['parts'] ?? [];
        if (! is_array($parts)) {
            return null;
        }
        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text']) && $part['text'] !== '') {
                return $part['text'];
            }
        }

        return null;
    }

    private function saveImageBytes(string $bytes, string $folder, string $slugSeed, string $aspectRatio = '1:1'): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'ai_img_');
        if ($tempPath === false) {
            throw new RuntimeException('Geçici dosya oluşturulamadı.');
        }

        if (file_put_contents($tempPath, $bytes) === false) {
            @unlink($tempPath);
            throw new RuntimeException('Görsel temp dosyaya yazılamadı.');
        }

        $mime = function_exists('mime_content_type') ? (string) mime_content_type($tempPath) : 'image/png';
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            $mime = 'image/png';
        }

        // Guarantee the saved bytes match the requested aspect ratio AND target
        // canonical dimensions (1080×1920 for 9:16, 1080×1080 for 1:1, etc.).
        // Gemini image models often return ~768×1376 or 1024×1024 regardless of
        // imageConfig — we crop+resize so downstream consumers (Instagram feed,
        // Reels/Story, blog covers) always get the dimensions Meta requires.
        $this->normalizeToCanonical($tempPath, $mime, $aspectRatio);

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default      => 'png',
        };

        $upload = new UploadedFile(
            $tempPath,
            'ai-generated.' . $extension,
            $mime,
            null,
            true,
        );

        $slug = 'ai-' . Str::slug($slugSeed, '-') . '-' . now()->format('Ymd-His');

        try {
            return $this->uploadService->uploadImage($upload, $folder, $slug);
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Hedef oran için kanonik (Instagram-uyumlu) boyutlar.
     * Meta gereksinimleri:
     *  - 1:1 feed → 1080×1080
     *  - 4:5 feed → 1080×1350 (kullanmıyoruz)
     *  - 9:16 reels/story → 1080×1920
     *  - 16:9 yatay → 1920×1080
     *  - 4:3 → 1080×810
     *  - 3:4 → 810×1080
     */
    private const array CANONICAL_DIMENSIONS = [
        '1:1'  => ['w' => 1080, 'h' => 1080],
        '9:16' => ['w' => 1080, 'h' => 1920],
        '16:9' => ['w' => 1920, 'h' => 1080],
        '4:3'  => ['w' => 1080, 'h' => 810],
        '3:4'  => ['w' => 810,  'h' => 1080],
    ];

    /**
     * Geçici dosyayı hem aspect ratio hem kanonik boyutlara getirir:
     *   1) Center-crop ile hedef oranı yakala
     *   2) Hedef boyutlara (1080×1920 vb.) yeniden örnekle (Lanczos kalitesinde)
     *
     * Nano Banana 2 / Pro genellikle 768×1376 veya 1024×1024 döndürür — Instagram
     * Story 1080×1920 ister, bu yüzden son aşamada normalize edilir.
     *
     * GD decode hatasında veya bilinmeyen oranda no-op (orijinal dosya korunur).
     */
    private function normalizeToCanonical(string $absolutePath, string $mime, string $aspectRatio): void
    {
        if (! isset(self::CANONICAL_DIMENSIONS[$aspectRatio])) {
            return;
        }

        $canonical = self::CANONICAL_DIMENSIONS[$aspectRatio];
        $targetW = $canonical['w'];
        $targetH = $canonical['h'];

        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return;
        }

        $srcW = (int) $info[0];
        $srcH = (int) $info[1];
        if ($srcW <= 0 || $srcH <= 0) {
            return;
        }

        // Source zaten tam canonical boyutlarındaysa hiçbir şey yapma
        if ($srcW === $targetW && $srcH === $targetH) {
            return;
        }

        $targetRatio = $targetW / $targetH;
        $currentRatio = $srcW / $srcH;

        // 1) Center-crop region (oran düzeltme)
        if (abs($currentRatio - $targetRatio) < 0.02) {
            // Oran zaten tutuyor — tüm görseli hedefe resize et (sadece ölçek)
            $cropX = 0;
            $cropY = 0;
            $cropW = $srcW;
            $cropH = $srcH;
        } elseif ($currentRatio > $targetRatio) {
            // Çok geniş → sol/sağ kırp
            $cropW = (int) round($srcH * $targetRatio);
            $cropH = $srcH;
            $cropX = (int) round(($srcW - $cropW) / 2);
            $cropY = 0;
        } else {
            // Çok dar/uzun → üst/alt kırp
            $cropW = $srcW;
            $cropH = (int) round($srcW / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($srcH - $cropH) / 2);
        }

        if ($cropW <= 0 || $cropH <= 0) {
            return;
        }

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($absolutePath),
            'image/png'  => @imagecreatefrompng($absolutePath),
            'image/webp' => @imagecreatefromwebp($absolutePath),
            default      => false,
        };
        if ($src === false) {
            return;
        }

        // 2) Hedef canvas (canonical boyutlar)
        $dst = imagecreatetruecolor($targetW, $targetH);
        if ($dst === false) {
            imagedestroy($src);
            return;
        }

        // Şeffaflık koruma (PNG/WebP)
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $transparent);
            }
        }

        // Crop + resize tek seferde — Lanczos benzeri yüksek kalite
        imagecopyresampled(
            $dst, $src,
            0, 0,                  // dst x, y
            $cropX, $cropY,        // src x, y (crop offset)
            $targetW, $targetH,    // dst w, h (canonical)
            $cropW, $cropH,        // src w, h (crop size)
        );

        match ($mime) {
            'image/jpeg' => imagejpeg($dst, $absolutePath, 92),
            'image/webp' => imagewebp($dst, $absolutePath, 92),
            default      => imagepng($dst, $absolutePath),
        };

        imagedestroy($src);
        imagedestroy($dst);
    }

    /**
     * @return array{width: ?int, height: ?int}
     */
    private function probeDimensions(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            return ['width' => null, 'height' => null];
        }

        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return ['width' => null, 'height' => null];
        }

        return ['width' => (int) $info[0], 'height' => (int) $info[1]];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function sanitizeRequestPayload(array $body): array
    {
        // Strip any inline base64 data so the row stays small.
        if (isset($body['contents']) && is_array($body['contents'])) {
            foreach ($body['contents'] as &$content) {
                if (isset($content['parts']) && is_array($content['parts'])) {
                    foreach ($content['parts'] as &$part) {
                        if (isset($part['inlineData']['data'])) {
                            $part['inlineData']['data'] = '<base64 stripped>';
                        }
                    }
                }
            }
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>|null
     */
    private function sanitizeResponsePayload(?array $json): ?array
    {
        if ($json === null) {
            return null;
        }

        // Strip any base64 image bytes — keep textual context for diagnostics.
        if (isset($json['candidates']) && is_array($json['candidates'])) {
            foreach ($json['candidates'] as &$candidate) {
                $parts = $candidate['content']['parts'] ?? null;
                if (is_array($parts)) {
                    foreach ($parts as &$part) {
                        if (isset($part['inlineData']['data'])) {
                            $part['inlineData']['data'] = '<base64 stripped>';
                        }
                        if (isset($part['inline_data']['data'])) {
                            $part['inline_data']['data'] = '<base64 stripped>';
                        }
                    }
                }
            }
        }

        if (isset($json['predictions']) && is_array($json['predictions'])) {
            foreach ($json['predictions'] as &$prediction) {
                if (isset($prediction['bytesBase64Encoded'])) {
                    $prediction['bytesBase64Encoded'] = '<base64 stripped>';
                }
            }
        }

        // Final guard against pathological payloads.
        $encoded = json_encode($json);
        if (is_string($encoded) && strlen($encoded) > self::RESPONSE_LOG_LIMIT) {
            return ['_truncated' => true, '_size' => strlen($encoded), '_excerpt' => Str::limit($encoded, self::RESPONSE_LOG_LIMIT, '... [truncated]')];
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>|null  $requestBody
     * @param  array<string, mixed>|null  $responseJson
     * @return array{
     *     success: bool,
     *     message: string,
     *     image: AiGeneratedImage,
     *     image_path: ?string,
     *     url: ?string,
     *     model_used: ?string,
     *     cost_usd: ?float,
     *     width: ?int,
     *     height: ?int,
     * }
     */
    private function failResult(
        AiGeneratedImage $image,
        string $message,
        ?int $duration = null,
        int $attempt = 0,
        ?array $requestBody = null,
        ?array $responseJson = null,
    ): array {
        $payload = [
            'status'             => AiGeneratedImage::STATUS_FAILED,
            'error_message'      => $message,
            'generation_time_ms' => $duration,
            'attempt_count'      => $attempt > 0 ? $attempt : ($image->attempt_count ?? 0),
        ];
        if ($requestBody !== null) {
            $payload['request_payload'] = $this->sanitizeRequestPayload($requestBody);
        }
        if ($responseJson !== null) {
            $payload['response_payload'] = $this->sanitizeResponsePayload($responseJson);
        }
        $image->update($payload);

        return [
            'success'    => false,
            'message'    => $message,
            'image'      => $image->fresh(),
            'image_path' => null,
            'url'        => null,
            'model_used' => null,
            'cost_usd'   => null,
            'width'      => null,
            'height'     => null,
        ];
    }
}
