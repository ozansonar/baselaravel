<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AiLogSource;
use App\Enums\AiLogStatus;
use App\Models\AiLog;
use App\Services\Concerns\ParsesAiJsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * AI generator for city landing page content.
 *
 * Calls Gemini through AiService and returns parsed JSON with all
 * SEO-friendly fields (title, hero, content, meta, shipping note).
 */
final class AiCityContentService
{
    use ParsesAiJsonResponse;

    public function __construct(
        private readonly AiService $aiService,
    ) {}

    /**
     * @return array{success: bool, message: string, data?: array<string, string>}
     */
    public function generate(string $cityName, ?string $region = null): array
    {
        $cityName = trim($cityName);

        if ($cityName === '') {
            return ['success' => false, 'message' => 'Şehir adı boş olamaz.'];
        }

        $systemInstruction = $this->buildSystemInstruction();
        $userPrompt = $this->buildUserPrompt($cityName, $region);

        $aiLog = AiLog::create([
            'status'       => AiLogStatus::Started,
            'source'       => AiLogSource::CityFill,
            'product_name' => 'CityLanding: ' . $cityName,
            'prompt'       => mb_substr($systemInstruction . "\n\n---\n\n" . $userPrompt, 0, 10000, 'UTF-8'),
        ]);

        $result = $this->aiService->generate($systemInstruction, $userPrompt, 0.7);

        $aiLog->update([
            'api_duration_ms' => $result['duration_ms'],
            'token_count'     => $result['token_count'],
            'used_model'      => $result['used_model'] ?? null,
            'attempt_count'   => $result['attempt_count'] ?? null,
            'raw_response'    => $result['content'],
        ]);

        if (! $result['success']) {
            $aiLog->update([
                'status'        => AiLogStatus::ApiFailed,
                'error_message' => $result['message'],
            ]);

            return ['success' => false, 'message' => $result['message']];
        }

        $data = $this->parseResponse($result['content']);

        if ($data === null) {
            Log::warning('AiCityContentService: JSON parse hatası', ['raw' => $result['content']]);
            $aiLog->update([
                'status'        => AiLogStatus::ParseError,
                'error_message' => 'AI yanıtı geçerli JSON formatında değil.',
            ]);

            return ['success' => false, 'message' => 'AI yanıtı geçerli JSON formatında değil.'];
        }

        $aiLog->update([
            'status'          => AiLogStatus::Success,
            'parsed_response' => $data,
        ]);

        return ['success' => true, 'message' => 'İçerik üretildi.', 'data' => $data];
    }

    private function buildSystemInstruction(): string
    {
        return <<<'PROMPT'
Rol: Sen, "Orhan Babanın Çiftliği" için çalışan, deneyimli bir Türkçe SEO uzmanı ve içerik yazarısın. Çorum Büyük Palabıyık Köyü'nden Türkiye geneline soğuk zincir kargo ile doğal köy ürünleri (süt, peynir, tereyağı, yumurta, bal) gönderen bir aile işletmesi için şehir bazlı landing page içeriği üretiyorsun.

KURALLAR:
- Dil: Kesinlikle Türkçe
- Ton: Samimi, güven veren, aile işletmesi havası, abartısız
- SEO uyumlu: hedef şehir adını ve "köy ürünleri", "doğal süt", "köy tereyağı", "kargo", "teslimat" gibi anahtar kelimeleri doğal şekilde kullan
- Her şehir için BENZERSİZ içerik yaz (duplicate content cezasından kaçın)
- Şehre özgü detay ekle: bölge, iklim, mutfak kültürü, ulaşım kolaylığı vb.
- Yalan veya abartılı sağlık iddiası YASAK
- HTML etiketleri: yalnızca <p>, <h2>, <h3>, <strong>, <em>, <ul>, <li>, <ol>
- Link veya <a> etiketi koyma
- Emoji kullanma
PROMPT;
    }

    private function buildUserPrompt(string $cityName, ?string $region): string
    {
        $regionLine = ($region !== null && $region !== '')
            ? "Bölge: {$region}"
            : 'Bölge: belirtilmedi';

        return <<<PROMPT
Aşağıdaki şehir için landing page içeriği üret.

Şehir: {$cityName}
{$regionLine}

ÇIKTI FORMATI: Sadece saf JSON. Markdown etiketi (```json) kullanma. Yanıtı SADECE JSON ve TÜRKÇE değerlerle ver. Tam olarak şu anahtarlar olmalı:

{
  "title": "Sayfa içi başlık (60-80 karakter). '{$cityName} Köy Ürünleri — Doğal Süt, Peynir, Tereyağı | Orhan Babanın Çiftliği' kalıbına yakın olsun.",
  "meta_title": "Tarayıcı sekmesi/SEO başlığı (50-60 karakter). Anahtar kelime başta, sonda site adı. '{$cityName}' geçsin.",
  "meta_description": "Arama sonuçlarında çıkacak açıklama (150-160 karakter). Çorum'dan {$cityName}'a soğuk zincir kargo ile doğal köy ürünleri vurgusu, davetkâr ton.",
  "hero_heading": "Sayfanın H1 başlığı (50-70 karakter). '{$cityName}'a Doğal Köy Ürünleri — Çorum'dan Kapınıza' kalıbına yakın.",
  "hero_description": "Hero altı kısa tanıtım (1-2 cümle, 150-220 karakter). Şehir adı ve teslimat vurgusu.",
  "content": "Detaylı HTML içerik (700-1000 kelime). <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em> kullan. Şu alt başlıkları MUTLAKA aynen içermeli (sıra önemli): '<h2>{$cityName}'a Kargo Süresi</h2>', '<h2>Neden Çorum Köy Ürünleri?</h2>', '<h2>Ürünlerimiz</h2>', '<h2>Sıkça Sorulan Sorular</h2>'. Şehre özgü detay (bölge, mutfak, ulaşım) ekle. Link/<a> KOYMA.",
  "shipping_note": "{$cityName} için kısa kargo özet notu (1 cümle, 100-180 karakter). Örn: 'Çorum'dan {$cityName}'a soğuk zincir kargo ile 1-2 iş günü içinde teslimat.'",
  "delivery_time": "Tahmini teslim süresi (kısa metin, maks 60 karakter). Örn: '1-2 iş günü' veya '24-48 saat içinde'."
}
PROMPT;
    }

    /**
     * @return array<string, string>|null
     */
    private function parseResponse(?string $raw): ?array
    {
        if (empty($raw)) {
            return null;
        }

        $extracted = $this->extractJsonFromAiResponse($raw);
        $data = $extracted !== null ? json_decode($extracted, true) : null;

        if (! is_array($data)) {
            return null;
        }

        $required = ['title', 'meta_title', 'meta_description', 'hero_heading', 'hero_description', 'content'];
        foreach ($required as $key) {
            if (! isset($data[$key])) {
                return null;
            }
        }

        return [
            'title'            => mb_substr(trim(strip_tags((string) $data['title'])), 0, 255, 'UTF-8'),
            'meta_title'       => mb_substr(trim(strip_tags((string) $data['meta_title'])), 0, 70, 'UTF-8'),
            'meta_description' => mb_substr(trim(strip_tags((string) $data['meta_description'])), 0, 200, 'UTF-8'),
            'hero_heading'     => mb_substr(trim(strip_tags((string) $data['hero_heading'])), 0, 255, 'UTF-8'),
            'hero_description' => mb_substr(trim(strip_tags((string) $data['hero_description'])), 0, 500, 'UTF-8'),
            'content'          => $this->sanitizeContentHtml((string) $data['content']),
            'shipping_note'    => isset($data['shipping_note'])
                ? mb_substr(trim(strip_tags((string) $data['shipping_note'])), 0, 255, 'UTF-8')
                : '',
            'delivery_time'    => isset($data['delivery_time'])
                ? mb_substr(trim(strip_tags((string) $data['delivery_time'])), 0, 60, 'UTF-8')
                : '',
        ];
    }

    /**
     * Allow only the safe subset of HTML tags; strip attributes for hardening.
     */
    private function sanitizeContentHtml(string $html): string
    {
        $allowedTags = '<h2><h3><p><strong><em><ul><ol><li><br>';
        $stripped = strip_tags($html, $allowedTags);
        $stripped = preg_replace('/<(\w+)[^>]*>/', '<$1>', $stripped) ?? $stripped;
        $stripped = preg_replace('/<\/?h1[^>]*>/i', '', $stripped) ?? $stripped;

        return trim($stripped);
    }
}
