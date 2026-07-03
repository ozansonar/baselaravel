<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MediaLibraryImage;
use App\Models\Setting;
use App\Services\Concerns\ParsesAiJsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Görsel kütüphanesine yüklenen görseller için Gemini Vision ile otomatik
 * tag/tema/mood/açıklama üretir.
 *
 * Maliyet: ~$0.00015 per görsel (gemini-2.5-flash multimodal text input).
 * Yüksek hacimli yükleme bile pratik olarak ücretsiz: 100 görsel = $0.015.
 *
 * Kullanım: ImageLibraryController::upload sonrasında veya elle "yeniden tag'le"
 * butonu ile çağrılır. AI çıktısı kullanıcıya öneri olarak gelir, kullanıcı
 * onaylar/düzenler — DB'ye doğrudan yazmaz (caller karar verir).
 */
final class MediaLibraryAutoTagger
{
    use ParsesAiJsonResponse;

    private const string BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const int HTTP_TIMEOUT = 25;

    /** Görsel başına maliyet tahmini (USD) — admin loglama için */
    public const float COST_PER_IMAGE = 0.00015;

    /**
     * Verilen görsel için AI'dan tag/tema/mood/açıklama önerisi al.
     *
     * @return array{
     *     success: bool,
     *     tags?: list<string>,
     *     theme?: string,
     *     mood?: string,
     *     season?: string|null,
     *     description?: string,
     *     alt_text?: string,
     *     message: string
     * }
     */
    public function analyzeImage(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            return ['success' => false, 'message' => 'Görsel dosyası bulunamadı: ' . basename($absolutePath)];
        }

        $apiKey = trim((string) Setting::getValue('ai_image_api_key', ''))
            ?: trim((string) Setting::getValue('gemini_api_key', ''));

        if ($apiKey === '') {
            return ['success' => false, 'message' => 'AI Görsel API Key tanımlı değil. /admin/settings → AI tab'];
        }

        $imageData = @file_get_contents($absolutePath);
        if ($imageData === false || $imageData === '') {
            return ['success' => false, 'message' => 'Görsel okunamadı.'];
        }

        // 4 MB üstü görselleri base64 göndermek pahalı — küçült veya skip
        if (strlen($imageData) > 4_500_000) {
            return ['success' => false, 'message' => 'Görsel çok büyük (>4.5 MB). Optimize edip tekrar yükle.'];
        }

        $base64 = base64_encode($imageData);
        $mime   = mime_content_type($absolutePath) ?: 'image/jpeg';

        $body = [
            'contents' => [[
                'parts' => [
                    [
                        'inlineData' => [
                            'mimeType' => $mime,
                            'data'     => $base64,
                        ],
                    ],
                    ['text' => $this->buildPrompt()],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 600,
            ],
        ];

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->post(self::BASE_URL . 'gemini-2.5-flash:generateContent', $body);

            if (! $response->successful()) {
                $err = (string) ($response->json('error.message') ?? $response->body());
                Log::warning('MediaLibraryAutoTagger: API hatası', [
                    'status' => $response->status(),
                    'error'  => $err,
                ]);
                return ['success' => false, 'message' => 'AI hatası: ' . mb_strimwidth($err, 0, 200, '…')];
            }

            $text = (string) ($response->json('candidates.0.content.parts.0.text') ?? '');
            if ($text === '') {
                return ['success' => false, 'message' => 'AI yanıtı boş.'];
            }

            $extracted = $this->extractJsonFromAiResponse($text);
            if ($extracted === null) {
                return ['success' => false, 'message' => 'AI yanıtı geçerli JSON değil.'];
            }

            try {
                /** @var mixed $decoded */
                $decoded = json_decode($extracted, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                return ['success' => false, 'message' => 'JSON parse hatası: ' . $e->getMessage()];
            }

            if (! is_array($decoded)) {
                return ['success' => false, 'message' => 'AI yanıtı dizi/obje değil.'];
            }

            return [
                'success'     => true,
                'tags'        => $this->normalizeTags($decoded['tags'] ?? []),
                'theme'       => $this->normalizeTheme((string) ($decoded['theme'] ?? '')),
                'mood'        => $this->normalizeMood((string) ($decoded['mood'] ?? '')),
                'season'      => $this->normalizeSeason((string) ($decoded['season'] ?? '')),
                'description' => trim(mb_strimwidth((string) ($decoded['description'] ?? ''), 0, 1900, '', 'UTF-8')),
                'alt_text'    => trim(mb_strimwidth((string) ($decoded['alt_text'] ?? ''), 0, 240, '', 'UTF-8')),
                'message'     => 'Auto-tag başarılı.',
            ];
        } catch (\Throwable $e) {
            Log::error('MediaLibraryAutoTagger: beklenmedik hata', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Beklenmedik hata: ' . $e->getMessage()];
        }
    }

    private function buildPrompt(): string
    {
        $themes = implode(', ', MediaLibraryImage::THEMES);
        $moods  = implode(', ', MediaLibraryImage::MOODS);
        $seasons = implode(', ', MediaLibraryImage::SEASONS);

        return <<<EOT
Sen Türkiye'deki "Orhan Babanın Çiftliği" markasının Instagram içerik kütüphanesi
asistanısın. Verilen görseli analiz edip aşağıdaki JSON formatında etiketleyeceksin.

ÇIKTI: SADECE JSON obje, başka hiçbir şey yazma. Markdown YASAK.

Format:
{
  "tags": ["3-8 kelimelik liste, Türkçe, küçük harf, virgülsüz"],
  "theme": "{$themes} arasından SADECE 1 tane",
  "mood": "{$moods} arasından SADECE 1 tane",
  "season": "{$seasons} arasından 1 tane VEYA null (mevsim belirgin değilse)",
  "description": "1-2 cümle Türkçe açıklama (görselin neyi gösterdiği — caption üretirken kullanılacak)",
  "alt_text": "SEO için 1 cümle Türkçe alt-text (max 200 karakter)"
}

KURALLAR:
- Tag'ler tek kelime VEYA 2 kelimelik kısa ifadeler olmalı (örn. "süt", "sabah sağımı")
- Tag'ler Türkçe karakter içerebilir (ğ, ı, ş, ç, ö, ü)
- Yiyecek/ürün varsa → theme="products"
- Çiftlik manzarası, hayvan, doğal alan → theme="farm" veya "nature"
- Yemek/tarif/sofra → theme="recipes" veya "lifestyle"
- Mevsim belirgin değilse season=null
- mood seçimi görselin atmosferine göre (sıcak/samimi → samimi, vurucu/temiz → profesyonel)
- "Orhan Babanın Çiftliği" markasının logosu varsa açıklamada belirt

Sadece JSON döndür, başka açıklama YOK.
EOT;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function normalizeTags(mixed $raw): array
    {
        if (! is_array($raw)) return [];

        $clean = [];
        foreach ($raw as $tag) {
            if (! is_string($tag)) continue;
            $t = trim(mb_strtolower($tag));
            if ($t === '' || mb_strlen($t) > 30) continue;
            $clean[] = $t;
            if (count($clean) >= 10) break;
        }
        return array_values(array_unique($clean));
    }

    private function normalizeTheme(string $raw): string
    {
        $t = mb_strtolower(trim($raw));
        return in_array($t, MediaLibraryImage::THEMES, true) ? $t : '';
    }

    private function normalizeMood(string $raw): string
    {
        $m = mb_strtolower(trim($raw));
        return in_array($m, MediaLibraryImage::MOODS, true) ? $m : '';
    }

    private function normalizeSeason(string $raw): ?string
    {
        $s = mb_strtolower(trim($raw));
        if ($s === '' || $s === 'null') return null;
        return in_array($s, MediaLibraryImage::SEASONS, true) ? $s : null;
    }
}
