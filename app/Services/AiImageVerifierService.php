<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Görsel uygunluk doğrulayıcı — Gemini multimodal vision ile analiz.
 *
 * Kullanım:
 *   $result = $verifier->verifyForBlog($localImagePath, $title, $excerpt);
 *   if ($result['suitable']) { ... }
 *
 * Akış:
 *   1. Görseli base64 encode et
 *   2. Gemini Flash 1.5+ multimodal endpoint'ine gönder
 *   3. Yapılandırılmış prompt ile EVET/HAYIR + sebep iste
 *   4. Yanıtı parse et
 *
 * Maliyet: Gemini Flash multimodal = pratikte ücretsiz tier'da kalır.
 * Hata durumunda graceful fallback: 'suitable' => true (engelleme yok),
 * çünkü verify aksamı blog yayınını blokе etmemeli.
 */
final class AiImageVerifierService
{
    private const string GENERATIVE_LANGUAGE_BASE = 'https://generativelanguage.googleapis.com/v1beta';
    private const string DEFAULT_MODEL = 'gemini-2.5-flash';
    private const int TIMEOUT_SECONDS = 30;
    private const int MAX_IMAGE_BYTES = 8 * 1024 * 1024; // 8MB Gemini inline data limit

    /**
     * Blog kapak görseli için uygunluk doğrulaması.
     *
     * STRICT MOD: %100 alaka politikası — Vision yanıtı belirsizse veya
     * şüphe içeriyorsa REDDEDİLİR (suitable=false). Sadece kesin "EVET"
     * yanıtı görseli geçirir. Caller bu durumda alternatif prompt ile
     * yeniden dener; tüm denemeler başarısız olursa placeholder görsel
     * kullanılır.
     *
     * @param  string  $bodyExcerpt  İçerikten çıkarılmış düz metin (~500 char)
     * @return array{suitable: bool, reason: string, raw_response: ?string}
     */
    public function verifyForBlog(string $imagePath, string $title, string $excerpt, string $bodyExcerpt = ''): array
    {
        $apiKey = trim((string) Setting::getValue('gemini_api_key', ''));
        if ($apiKey === '') {
            return $this->fallback('Gemini API key ayarlarda eksik — verify atlandı.');
        }

        if (! is_file($imagePath)) {
            return $this->fallback("Görsel dosyası bulunamadı: {$imagePath}");
        }

        $bytes = @file_get_contents($imagePath);
        if ($bytes === false || $bytes === '') {
            return ['suitable' => false, 'reason' => 'Görsel dosyası boş veya okunamadı.', 'raw_response' => null];
        }

        if (strlen($bytes) > self::MAX_IMAGE_BYTES) {
            // Çok büyük → graceful skip (Gemini reddedebilir)
            return $this->fallback('Görsel verify için çok büyük (>8MB) — atlandı.');
        }

        $mime = $this->detectMime($imagePath, $bytes);
        $base64 = base64_encode($bytes);

        $model = trim((string) Setting::getValue('ai_primary_model', self::DEFAULT_MODEL));
        $endpoint = self::GENERATIVE_LANGUAGE_BASE . '/models/' . $model . ':generateContent?key=' . urlencode($apiKey);

        $prompt = $this->buildVerifyPrompt($title, $excerpt, $bodyExcerpt);

        try {
            // Laravel retry callback'in 2. argümanı PendingRequest'tir (Response değil),
            // bu yüzden response status'ünü exception üzerinden kontrol ediyoruz.
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->retry(2, 2000, function (\Throwable $e): bool {
                    if ($e instanceof ConnectionException) {
                        return true;
                    }
                    if ($e instanceof RequestException && $e->response !== null) {
                        return $e->response->status() >= 500;
                    }
                    return false;
                })
                ->post($endpoint, [
                    'contents' => [[
                        'role'  => 'user',
                        'parts' => [
                            ['text' => $prompt],
                            ['inlineData' => ['mimeType' => $mime, 'data' => $base64]],
                        ],
                    ]],
                    'generationConfig' => [
                        'temperature'     => 0.2, // tutarlı yanıt
                        'maxOutputTokens' => 200,
                    ],
                ]);

            if ($response->failed()) {
                $err = (string) ($response->json('error.message') ?? "HTTP {$response->status()}");
                Log::warning('AiImageVerifier: Gemini Vision API failed', [
                    'error' => $err,
                    'title' => $title,
                ]);

                return $this->fallback("Vision API başarısız: {$err}");
            }

            $rawText = $this->extractTextFromResponse($response->json());
            if ($rawText === null) {
                return $this->fallback('Vision yanıt parse edilemedi.');
            }

            return $this->parseVerifyResponse($rawText);
        } catch (\Throwable $e) {
            Log::warning('AiImageVerifier: exception', ['error' => $e->getMessage(), 'title' => $title]);

            return $this->fallback('Vision sırasında hata: ' . $e->getMessage());
        }
    }

    /**
     * Strict prompt ile EVET/HAYIR + sebep döndürmeyi zorla.
     * %100 alaka politikası — en ufak şüphede HAYIR talimatı.
     */
    private function buildVerifyPrompt(string $title, string $excerpt, string $bodyExcerpt = ''): string
    {
        $excerptShort = mb_substr(trim($excerpt), 0, 400);
        $bodyShort = mb_substr(trim($bodyExcerpt), 0, 600);
        $bodyLine = $bodyShort !== '' ? "BLOG İÇERİK ÖZETİ:\n{$bodyShort}\n\n" : '';

        return <<<EOT
        Sen titiz bir gıda/çiftlik blog editörsün. Aşağıdaki görselin verilen blog yazısı için
        kapak görseli olarak %100 UYGUN olup olmadığını değerlendir.

        BLOG BAŞLIĞI: {$title}
        BLOG ÖZETİ: {$excerptShort}
        {$bodyLine}DEĞERLENDİRME KRİTERLERİ (HEPSİ SAĞLANMALI — bir tanesi bile eksikse HAYIR):
        1. KONU ALAKASı: Görsel blog konusunun ANA NESNESİNİ açıkça gösteriyor mu?
           (Örn: "süt" blogu için bir bardak/şişe süt; "tereyağı" için tereyağı topağı; "bal" için bal kavanozu/peteği)
           Sadece "kırsal sahne" yetersiz — konu nesnesi BAŞAT olmalı.
        2. Türk köy çiftliği / doğal gıda / kırsal yaşam estetiği var mı?
        3. Görselde TEXT/YAZI/HARF overlay YOK mu? (en ufak yazı varsa HAYIR)
        4. Profesyonel kalitede mi? (bulanık, deforme, garip oranlar, yapay görünüm yoksa)
        5. İnsan yüzü görünmüyor mu? (yüz varsa HAYIR — telif riski)
        6. Görsel blog özetindeki bağlamla TUTARLI mı? (örn: "saklama" konusunda donmuş ürün, "tarif" konusunda yemek tabağı)

        STRICT MOD: %100 emin değilsen HAYIR de. "Belki", "kısmen", "büyük ölçüde" YETERSİZ.
        Görsel ve içerik arasında EN UFAK kopukluk varsa HAYIR.

        ÇIKTI FORMATI (kesinlikle uy):
        ===KARAR===
        EVET veya HAYIR
        ===SEBEP===
        Tek cümle Türkçe açıklama (hangi kriter sağlandı/sağlanmadı).
        EOT;
    }

    /**
     * @param array<string, mixed>|null $json
     */
    private function extractTextFromResponse(?array $json): ?string
    {
        $parts = $json['candidates'][0]['content']['parts'] ?? [];
        if (! is_array($parts)) {
            return null;
        }
        foreach ($parts as $part) {
            $text = $part['text'] ?? null;
            if (is_string($text) && $text !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * @return array{suitable: bool, reason: string, raw_response: string}
     */
    private function parseVerifyResponse(string $text): array
    {
        $text = trim($text);

        if (preg_match('/===\s*KARAR\s*===\s*(.+?)\s*===\s*SEBEP\s*===\s*(.+)/is', $text, $m)) {
            $karar  = trim(mb_strtoupper($m[1]));
            $sebep  = trim($m[2]);
            $suitable = str_contains($karar, 'EVET');

            return [
                'suitable'     => $suitable,
                'reason'       => $sebep !== '' ? $sebep : ($suitable ? 'Uygun.' : 'Uygun değil.'),
                'raw_response' => $text,
            ];
        }

        // Fallback: yanıtın "EVET" geçip geçmediğine bak
        $upper = mb_strtoupper($text);
        $hasEvet  = str_contains($upper, 'EVET');
        $hasHayir = str_contains($upper, 'HAYIR');

        if ($hasHayir) {
            return ['suitable' => false, 'reason' => mb_substr($text, 0, 200), 'raw_response' => $text];
        }
        if ($hasEvet) {
            return ['suitable' => true, 'reason' => 'Uygun (parse fallback).', 'raw_response' => $text];
        }

        // STRICT MOD: Belirsiz yanıt → REDDEDİLİR.
        // %100 alaka politikası gereği şüphede kalan görsel kabul edilmez;
        // caller alternatif prompt ile yeniden dener veya placeholder kullanır.
        return ['suitable' => false, 'reason' => 'Belirsiz Vision yanıtı — strict mod gereği reddedildi.', 'raw_response' => $text];
    }

    /**
     * Verify altyapısı kullanılamadığında — engelleme YOK, true döner.
     *
     * @return array{suitable: bool, reason: string, raw_response: null}
     */
    private function fallback(string $reason): array
    {
        return [
            'suitable'     => true, // engelleme yapma — graceful degradation
            'reason'       => '[Verify atlandı] ' . $reason,
            'raw_response' => null,
        ];
    }

    private function detectMime(string $path, string $bytes): string
    {
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($path);
            if (is_string($detected) && str_starts_with($detected, 'image/')) {
                return $detected;
            }
        }

        // Magic byte fallback
        if (str_starts_with($bytes, "\xFF\xD8\xFF"))               return 'image/jpeg';
        if (str_starts_with($bytes, "\x89PNG"))                    return 'image/png';
        if (str_starts_with($bytes, 'RIFF') && str_contains(substr($bytes, 0, 12), 'WEBP')) return 'image/webp';
        if (str_starts_with($bytes, 'GIF8'))                       return 'image/gif';

        return 'image/jpeg'; // last resort
    }
}
