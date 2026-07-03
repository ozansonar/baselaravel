<?php

declare(strict_types=1);

namespace App\Services\Vertex;

use App\Models\Setting;
use App\Models\SpecialDayImage;
use App\Models\VertexGeneration;
use App\Models\VertexPrompt;
use App\Services\UploadService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Google Vertex AI image generation service.
 *
 * Endpoint:
 *   https://{endpoint}/v1/publishers/google/models/{model}:streamGenerateContent?key={apiKey}
 *
 * Default endpoint: aiplatform.googleapis.com
 * Default model:    gemini-3.1-flash-image-preview (Nano Banana 2)
 *
 * Diğer Gemini key'lerinden HARD ISOLATED — Setting::getValue('vertex_api_key')
 * dışındaki hiçbir key'e fallback YAPMAZ. Ayarlar Admin → Ayarlar → AI sekmesinden yönetilir.
 */
final class VertexImageService
{
    private const array COST_TABLE = [
        'gemini-2.5-flash-image'         => 0.04,
        'gemini-2.5-flash-image-preview' => 0.04,
        'gemini-3.1-flash-image-preview' => 0.07,
        'gemini-3-pro-image-preview'     => 0.10,
    ];

    public function __construct(
        private readonly UploadService $uploadService,
        private readonly \App\Services\AiService $aiService,
    ) {}

    /**
     * @param  array<string, string> $variables  ['degisken_adi' => 'değer', ...]
     */
    public function createPendingGeneration(VertexPrompt $template, ?int $userId, array $variables = []): VertexGeneration
    {
        // Placeholder'ları doldurulmuş final prompt — snapshot olarak generation'a yazılır.
        // Şablon prompt'u sonradan değişse bile geçmiş bozulmaz.
        $finalPrompt = PromptVariableParser::substitute($template->prompt, $variables);

        return VertexGeneration::create([
            'prompt_id'        => $template->id,
            'prompt_used'      => $finalPrompt,
            'reference_images' => is_array($template->reference_images) && $template->reference_images !== []
                ? $template->reference_images
                : null,
            'model'            => $template->model,
            'aspect_ratio'     => $template->aspect_ratio,
            'image_size'       => $template->image_size,
            'mime_type'        => $template->mime_type,
            'status'           => VertexGeneration::STATUS_PENDING,
            'created_by'       => $userId,
        ]);
    }

    /**
     * Execute the generation synchronously — called from the queued job.
     *
     * @return array{success: bool, message: string, retryable: bool}
     */
    public function execute(VertexGeneration $generation): array
    {
        $template = $generation->prompt;
        if ($template === null) {
            return $this->markFailed($generation, 'Şablon bulunamadı (silinmiş olabilir).');
        }

        $apiKey   = trim((string) Setting::getValue('vertex_api_key', ''));
        $endpoint = trim((string) Setting::getValue('vertex_endpoint', 'aiplatform.googleapis.com')) ?: 'aiplatform.googleapis.com';
        $timeout  = (int) (Setting::getValue('vertex_timeout', '180') ?: 180);
        $timeout  = max(30, min(600, $timeout));

        if ($apiKey === '') {
            return $this->markFailed($generation, 'Vertex API Key tanımlı değil. Admin → Ayarlar → AI → Google Vertex AI bölümünden ekleyin.');
        }

        $generation->update([
            'status'     => VertexGeneration::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        // Referans görselleri → diskten oku, base64 inline gönder
        // (fileUri ile gönderimde Google sunucumuza erişemeyebiliyor — robots.txt, firewall vb.)
        $referenceImages = is_array($generation->reference_images) ? $generation->reference_images : [];
        $referenceParts = [];
        foreach ($referenceImages as $img) {
            $path = $img['path'] ?? null;
            if (! is_string($path) || $path === '') {
                continue;
            }
            $fullPath = public_path('uploads/' . ltrim($path, '/'));
            if (! is_file($fullPath)) {
                Log::info('Vertex referans görsel bulunamadı, atlanıyor', ['path' => $path]);
                continue;
            }
            $bytes = file_get_contents($fullPath);
            if ($bytes === false) {
                continue;
            }
            $referenceParts[] = [
                'base64' => base64_encode($bytes),
                'mime'   => $img['mime'] ?? $this->guessMimeFromPath($path) ?? 'image/png',
            ];
        }

        // Final prompt = template.prompt'un {{variables}} substitute edilmiş hali
        // (zaten generation snapshot'una yazılmış halini kullan).
        $body = $this->buildRequestBody($template, $referenceParts, (string) $generation->prompt_used);

        $url = sprintf(
            'https://%s/v1/publishers/google/models/%s:streamGenerateContent?key=%s',
            $endpoint,
            $template->model,
            urlencode($apiKey),
        );

        $start = hrtime(true);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $body);
        } catch (ConnectionException $e) {
            $generation->update([
                'error_message' => 'Bağlantı hatası: ' . $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Bağlantı hatası: ' . $e->getMessage(), 'retryable' => true];
        } catch (\Throwable $e) {
            return $this->markFailed($generation, 'İstek hatası: ' . $e->getMessage(), $body);
        }

        $durationMs = (int) round((hrtime(true) - $start) / 1_000_000);
        $rawJson = $response->json();

        if (! $response->successful() || ! is_array($rawJson)) {
            $errorMessage = $this->extractApiErrorMessage($rawJson, $response->status());

            if ($response->status() === 429) {
                $generation->update([
                    'error_message' => $errorMessage,
                ]);

                return ['success' => false, 'message' => $errorMessage, 'retryable' => true];
            }

            return $this->markFailed($generation, $errorMessage, $body, $rawJson, $durationMs);
        }

        // streamGenerateContent yanıtı bir DİZİDİR — her chunk normal generateContent yanıtı gibi.
        $imageBytes = $this->extractFirstInlineImage($rawJson);
        if ($imageBytes === null) {
            $textHint = $this->extractFirstText($rawJson);
            $reason = $textHint !== null && $textHint !== ''
                ? 'API görsel döndürmedi. Açıklama: ' . Str::limit($textHint, 400)
                : 'API yanıtında görsel verisi (inlineData) bulunamadı.';
            return $this->markFailed($generation, $reason, $body, $rawJson, $durationMs);
        }

        try {
            $imagePath = $this->saveImageBytes(
                bytes: $imageBytes,
                folder: 'vertex-generations',
                slugSeed: $template->name . '-' . now()->format('Ymd-His'),
                mime: $template->mime_type,
            );
        } catch (\Throwable $e) {
            return $this->markFailed($generation, 'Görsel diske yazılamadı: ' . $e->getMessage(), $body, $rawJson, $durationMs);
        }

        [$width, $height] = $this->probeDimensions($imagePath);

        $tokenCount = $this->extractTokenCount($rawJson);
        $costUsd = self::COST_TABLE[$template->model] ?? null;

        $generation->update([
            'status'             => VertexGeneration::STATUS_COMPLETED,
            'output_image_path'  => $imagePath,
            'width'              => $width,
            'height'             => $height,
            'generation_time_ms' => $durationMs,
            'finished_at'        => now(),
            'token_count'        => $tokenCount,
            'cost_usd'           => $costUsd,
            'request_payload'    => null,
            'response_payload'   => null,
            'error_message'      => null,
        ]);

        Log::info('Auto-caption: kontrol', [
            'generation_id' => $generation->id,
            'aspect_ratio'  => $generation->aspect_ratio,
            'ig_caption'    => $generation->ig_caption,
            'ig_hashtags'   => $generation->ig_hashtags,
        ]);
        if (empty($generation->ig_caption) || empty($generation->ig_title) || empty($generation->ig_hashtags)) {
            $captionData = $this->generateCaptionFromImage($imagePath);
            if ($captionData !== null) {
                $fillOnly = array_filter($captionData, static function ($val, $key) use ($generation): bool {
                    return $val !== null && empty($generation->{$key});
                }, ARRAY_FILTER_USE_BOTH);
                if ($fillOnly !== []) {
                    $generation->update($fillOnly);
                }
                Log::info('Auto-caption: kaydedildi', ['generation_id' => $generation->id, 'data' => $captionData, 'filled' => array_keys($fillOnly)]);
            }
        }

        if ($generation->batch_id !== null) {
            $batch = $generation->batch;
            $batch?->incrementCompleted($durationMs);

            if ($batch !== null && $batch->special_day_id !== null && $batch->media_type !== null) {
                $this->saveAsSpecialDayImage($batch->special_day_id, $imagePath, $batch->media_type, $template->name);
            }
        }

        return [
            'success'   => true,
            'message'   => 'Görsel başarıyla üretildi.',
            'retryable' => false,
        ];
    }

    private function saveAsSpecialDayImage(int $specialDayId, string $imagePath, string $mediaType, string $altText): void
    {
        $maxSort = SpecialDayImage::where('special_day_id', $specialDayId)
            ->where('media_type', $mediaType)
            ->max('sort_order') ?? 0;

        SpecialDayImage::create([
            'special_day_id' => $specialDayId,
            'image_path'     => $imagePath,
            'media_type'     => $mediaType,
            'usage_count'    => 0,
            'sort_order'     => $maxSort + 1,
            'alt_text'       => Str::limit($altText, 200),
        ]);
    }

    private const string CAPTION_SYSTEM = 'Sen bir Instagram içerik uzmanısın. Görseldeki ürünü tanımlayıp Türkçe caption yazıyorsun.';

    private const string CAPTION_PROMPT = <<<'PROMPT'
Bu Instagram görseline bak. Görseldeki ana ürünü tanımla.

1) Görseldeki ürün için kısa bir başlık yaz (3-5 kelime, emoji ile).
2) Kısa, samimi, doğal bir Instagram caption'ı yaz.
   - Türkçe olacak, emoji kullanabilirsin, 2-3 cümle.
   - Çiftlik / köy / doğal ürün temalı, sıcak ve samimi ton.
   - "Orhan Baba'nın Çiftliği" markasına uygun.
3) Ürüne uygun 8-12 hashtag yaz (# işareti ile, boşlukla ayır).

SADECE şu formatta yanıt ver, başka hiçbir şey yazma:
TITLE: [başlık buraya]
CAPTION: [caption metni buraya]
HASHTAGS: [#hashtag1 #hashtag2 #hashtag3]
PROMPT;

    /**
     * @return array{ig_title: ?string, ig_caption: ?string, ig_hashtags: ?string}|null
     */
    public function generateCaptionFromImage(string $imagePath): ?array
    {
        Log::info('Auto-caption: başlıyor', ['image' => $imagePath]);

        $result = $this->aiService->generate(
            systemInstruction: self::CAPTION_SYSTEM,
            userPrompt: self::CAPTION_PROMPT,
            temperature: 0.7,
            imagePath: $imagePath,
        );

        if (! $result['success'] || empty($result['content'])) {
            Log::warning('Auto-caption: AiService başarısız', [
                'message' => $result['message'],
                'model'   => $result['used_model'],
            ]);
            return null;
        }

        Log::info('Auto-caption: yanıt alındı', [
            'text'  => Str::limit($result['content'], 300),
            'model' => $result['used_model'],
        ]);

        $parsed = $this->parseCaptionResponse($result['content']);

        if ($parsed === null) {
            Log::warning('Auto-caption: parse edilemedi', ['text' => Str::limit($result['content'], 300)]);
        }

        return $parsed;
    }

    /**
     * @return array{ig_title: ?string, ig_caption: ?string, ig_hashtags: ?string}|null
     */
    private function parseCaptionResponse(string $text): ?array
    {
        $title = null;
        $caption = null;
        $hashtags = null;

        if (preg_match('/TITLE:\s*(.+?)(?=CAPTION:|$)/si', $text, $m)) {
            $title = trim($m[1]);
        }
        if (preg_match('/CAPTION:\s*(.+?)(?=HASHTAGS:|$)/si', $text, $m)) {
            $caption = trim($m[1]);
        }
        if (preg_match('/HASHTAGS:\s*(.+)/si', $text, $m)) {
            $hashtags = trim($m[1]);
        }

        if ($title === null && $caption === null && $hashtags === null) {
            return null;
        }

        return [
            'ig_title'    => $title,
            'ig_caption'  => $caption,
            'ig_hashtags' => $hashtags,
        ];
    }

    /**
     * Build the full Vertex streamGenerateContent request body.
     *
     * @param  list<array{base64: string, mime: string}> $referenceParts
     * @return array<string, mixed>
     */
    private function buildRequestBody(VertexPrompt $template, array $referenceParts, string $finalPromptText): array
    {
        $parts = [];
        foreach ($referenceParts as $ref) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $ref['mime'],
                    'data'     => $ref['base64'],
                ],
            ];
        }
        $parts[] = ['text' => $finalPromptText];

        $body = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature'        => (float) $template->temperature,
                'maxOutputTokens'    => (int) $template->max_output_tokens,
                'responseModalities' => ['TEXT', 'IMAGE'],
                'topP'               => (float) $template->top_p,
                'imageConfig' => [
                    'aspectRatio'       => $template->aspect_ratio,
                    'imageSize'         => $template->image_size,
                    'imageOutputOptions' => [
                        'mimeType' => $template->mime_type,
                    ],
                    'personGeneration'  => $template->person_generation,
                ],
            ],
        ];

        $supportsThinking = str_contains($template->model, 'flash');
        if ($supportsThinking && $template->thinking_level) {
            $body['generationConfig']['thinkingConfig'] = [
                'thinkingLevel' => $template->thinking_level,
            ];
        }

        if ($template->safety_off) {
            $body['safetySettings'] = [
                ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'OFF'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'OFF'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'OFF'],
                ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'OFF'],
            ];
        }

        return $body;
    }

    /**
     * streamGenerateContent yanıtından ilk inlineData (base64 image) bytes'ını çıkarır.
     */
    private function extractFirstInlineImage(array $response): ?string
    {
        $chunks = $this->normalizeChunks($response);

        foreach ($chunks as $chunk) {
            $candidates = $chunk['candidates'] ?? [];
            if (! is_array($candidates)) {
                continue;
            }
            foreach ($candidates as $candidate) {
                $parts = $candidate['content']['parts'] ?? [];
                if (! is_array($parts)) {
                    continue;
                }
                foreach ($parts as $part) {
                    $b64 = $part['inlineData']['data'] ?? $part['inline_data']['data'] ?? null;
                    if (is_string($b64) && $b64 !== '') {
                        $decoded = base64_decode($b64, true);
                        if (is_string($decoded) && $decoded !== '') {
                            return $decoded;
                        }
                    }
                }
            }
        }

        return null;
    }

    private function extractFirstText(array $response): ?string
    {
        $chunks = $this->normalizeChunks($response);
        $texts = [];

        foreach ($chunks as $chunk) {
            $candidates = $chunk['candidates'] ?? [];
            if (! is_array($candidates)) {
                continue;
            }
            foreach ($candidates as $candidate) {
                $parts = $candidate['content']['parts'] ?? [];
                if (! is_array($parts)) {
                    continue;
                }
                foreach ($parts as $part) {
                    if (! empty($part['thought'])) {
                        continue;
                    }
                    if (isset($part['text']) && is_string($part['text']) && $part['text'] !== '') {
                        $texts[] = $part['text'];
                    }
                }
            }
        }

        return $texts !== [] ? implode("\n", $texts) : null;
    }

    /**
     * streamGenerateContent her zaman array of chunks döner, generateContent ise tek obje.
     * İki form'u da destekle.
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeChunks(array $response): array
    {
        // Eğer ilk eleman array ise → stream yanıtı (list of chunks)
        if (isset($response[0]) && is_array($response[0])) {
            /** @var list<array<string, mixed>> $chunks */
            $chunks = $response;
            return $chunks;
        }
        return [$response];
    }

    private function extractApiErrorMessage(mixed $rawJson, int $status): string
    {
        if (is_array($rawJson)) {
            $chunks = $this->normalizeChunks($rawJson);
            foreach ($chunks as $chunk) {
                if (isset($chunk['error']['message']) && is_string($chunk['error']['message'])) {
                    return 'API hatası (' . $status . '): ' . $chunk['error']['message'];
                }
            }
        }
        return 'API hatası (HTTP ' . $status . ').';
    }

    /**
     * @return array{success: false, message: string, retryable: false}
     */
    private function markFailed(
        VertexGeneration $generation,
        string $message,
        ?array $requestBody = null,
        mixed $rawResponse = null,
        ?int $durationMs = null,
    ): array {
        $updates = [
            'status'        => VertexGeneration::STATUS_FAILED,
            'error_message' => $message,
            'finished_at'   => now(),
        ];
        if ($requestBody !== null) {
            $updates['request_payload'] = $this->scrubForLog($requestBody);
        }
        if (is_array($rawResponse)) {
            $updates['response_payload'] = $this->scrubResponseForLog($rawResponse);
        }
        if ($durationMs !== null) {
            $updates['generation_time_ms'] = $durationMs;
        }
        $generation->update($updates);

        if ($generation->batch_id !== null) {
            $generation->batch?->incrementFailed();
        }

        Log::warning('VertexImageService failed', [
            'generation_id' => $generation->id,
            'batch_id'      => $generation->batch_id,
            'message'       => $message,
        ]);

        return ['success' => false, 'message' => $message, 'retryable' => false];
    }

    private function extractTokenCount(array $response): ?int
    {
        $chunks = $this->normalizeChunks($response);
        foreach ($chunks as $chunk) {
            $usage = $chunk['usageMetadata'] ?? $chunk['usage_metadata'] ?? null;
            if (is_array($usage)) {
                $total = ($usage['totalTokenCount'] ?? $usage['total_token_count'] ?? null);
                if (is_int($total) && $total > 0) {
                    return $total;
                }
                $prompt = (int) ($usage['promptTokenCount'] ?? $usage['prompt_token_count'] ?? 0);
                $candidates = (int) ($usage['candidatesTokenCount'] ?? $usage['candidates_token_count'] ?? 0);
                if ($prompt + $candidates > 0) {
                    return $prompt + $candidates;
                }
            }
        }

        return null;
    }

    private function saveImageBytes(string $bytes, string $folder, string $slugSeed, string $mime): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'vertex_');
        if ($tempPath === false) {
            throw new RuntimeException('Geçici dosya oluşturulamadı.');
        }
        if (file_put_contents($tempPath, $bytes) === false) {
            @unlink($tempPath);
            throw new RuntimeException('Görsel temp dosyaya yazılamadı.');
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default      => 'png',
        };

        $upload = new UploadedFile(
            $tempPath,
            'vertex.' . $extension,
            $mime,
            null,
            true,
        );

        $slug = 'vertex-' . Str::slug($slugSeed, '-');

        try {
            return $this->uploadService->uploadImage($upload, $folder, $slug);
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function probeDimensions(string $relativePath): array
    {
        $full = public_path('uploads/' . $relativePath);
        if (! is_file($full)) {
            return [null, null];
        }
        $info = @getimagesize($full);
        if ($info === false) {
            return [null, null];
        }
        return [(int) $info[0], (int) $info[1]];
    }

    private function guessMimeFromPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => null,
        };
    }

    /**
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function scrubForLog(array $body): array
    {
        if (isset($body['contents']) && is_array($body['contents'])) {
            foreach ($body['contents'] as $ci => $content) {
                if (! isset($content['parts']) || ! is_array($content['parts'])) {
                    continue;
                }
                foreach ($content['parts'] as $pi => $part) {
                    if (isset($part['inlineData']['data'])) {
                        $body['contents'][$ci]['parts'][$pi]['inlineData']['data'] = '<base64 stripped>';
                    }
                }
            }
        }

        return $body;
    }

    /**
     * Strip base64 image bytes from response payload before persisting.
     */
    private function scrubResponseForLog(array $response): array
    {
        $chunks = $this->normalizeChunks($response);
        foreach ($chunks as $i => $chunk) {
            $candidates = $chunk['candidates'] ?? [];
            if (! is_array($candidates)) {
                continue;
            }
            foreach ($candidates as $j => $candidate) {
                $parts = $candidate['content']['parts'] ?? [];
                if (! is_array($parts)) {
                    continue;
                }
                foreach ($parts as $k => $part) {
                    if (isset($part['inlineData']['data'])) {
                        $chunks[$i]['candidates'][$j]['content']['parts'][$k]['inlineData']['data'] = '<base64 stripped>';
                    }
                    if (isset($part['inline_data']['data'])) {
                        $chunks[$i]['candidates'][$j]['content']['parts'][$k]['inline_data']['data'] = '<base64 stripped>';
                    }
                }
            }
        }
        return $chunks;
    }
}
