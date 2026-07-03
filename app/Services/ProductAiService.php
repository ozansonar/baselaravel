<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AiLogSource;
use App\Enums\AiLogStatus;
use App\Models\AiLog;
use App\Services\Concerns\ParsesAiJsonResponse;
use Illuminate\Support\Facades\Log;

final class ProductAiService
{
    use ParsesAiJsonResponse;

    public function __construct(
        private readonly AiService $aiService,
    ) {}

    /**
     * Generate full product content from name + category.
     *
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function generate(string $productName, ?string $categoryName = null): array
    {
        $productName = trim($productName);

        if ($productName === '') {
            return ['success' => false, 'message' => 'Ürün adı boş olamaz.'];
        }

        $systemInstruction = $this->buildSystemInstruction();
        $userPrompt = $this->buildUserPrompt($productName, $categoryName);

        $aiLog = AiLog::create([
            'status'       => AiLogStatus::Started,
            'source'       => AiLogSource::ProductFill,
            'product_name' => $productName,
            'prompt'       => mb_substr($systemInstruction . "\n\n---\n\n" . $userPrompt, 0, 10000, 'UTF-8'),
        ]);

        $result = $this->aiService->generate($systemInstruction, $userPrompt);

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
            Log::warning('ProductAI: JSON parse hatası', ['raw' => $result['content']]);
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
        return 'Rol: Sen, "Orhan Babanın Çiftliği" için çalışan, deneyimli bir Türkçe e-ticaret içerik uzmanı ve SEO editörüsün. Aile işletmesinin samimiyetini yansıtan, doğal ve sağlıklı köy ürünlerini tanıtan ikna edici ürün açıklamaları yazıyorsun.

KURALLAR:
- Dil: Kesinlikle Türkçe
- Ton: Samimi, güvenilir, aile işletmesi havası
- SEO uyumlu: anahtar kelimeleri doğal şekilde kullan
- İşletmeci: Orhan Gazi SONAR
- İşletme: Orhan Babanın Çiftliği
- Ürün doğal, sağlıklı, köy ürünü olarak konumlandırılmalı
- Kesinlikle yalan bilgi veya abartılı sağlık iddiaları kullanma
- HTML etiketleri: yalnızca <p>, <h2>, <h3>, <strong>, <em>, <ul>, <li>';
    }

    private function buildUserPrompt(string $productName, ?string $categoryName): string
    {
        $categoryLine = $categoryName !== null && $categoryName !== ''
            ? "Kategori: {$categoryName}"
            : '';

        return <<<PROMPT
Aşağıdaki ürün için tam e-ticaret içerik paketi üret.

Ürün Adı: {$productName}
{$categoryLine}

ÇIKTI FORMATI: Sadece saf JSON. Markdown etiketi (```json) kullanma. Tam olarak şu anahtarlar olmalı:

{
  "short_description": "2-3 cümlelik kısa açıklama (maks 280 karakter). Ürün kartlarında gösterilecek, dikkat çekici olmalı.",
  "description": "Detaylı HTML açıklama. 250-400 kelime. <h2>, <h3>, <p>, <ul>, <li>, <strong> kullan. Ürünün faydalarını, özelliklerini, kullanım önerilerini anlat. Link koyma.",
  "meta_title": "SEO başlık (50-60 karakter). KALIP: 'Doğal {urun} — Çorum Köy Ürünleri' veya 'Doğal {urun} — Çorum'dan Kapınıza' kalıbına uy. 'Doğal' sıfatıyla başla. Her kelimenin ilk harfi büyük. SİTE ADI EKLEME (template otomatik ekler).",
  "meta_description": "SEO açıklama (150-160 karakter). KALIP: 'Çorum Büyük Palabıyık Köyü'nden {urun}. {kısa_avantaj}. Doğal, katkısız. Hızlı kargo ile kapınıza.' kalıbına uy. Tıklanma oranını artıracak, davetkâr.",
  "features": [
    {"icon": "fa-solid fa-leaf", "text": "özellik açıklaması"},
    {"icon": "fa-solid fa-shield-halved", "text": "özellik açıklaması"},
    {"icon": "fa-solid fa-heart", "text": "özellik açıklaması"},
    {"icon": "fa-solid fa-truck", "text": "özellik açıklaması"},
    {"icon": "fa-solid fa-star", "text": "özellik açıklaması"}
  ],
  "nutritions": [
    {"name": "Enerji", "per_value": "350 kcal", "daily_value": "%17"},
    {"name": "Protein", "per_value": "3.5 g", "daily_value": "%7"}
  ]
}

"features" için 5 adet özellik üret. Kullanabileceğin ikonlar:
fa-solid fa-leaf, fa-solid fa-shield-halved, fa-solid fa-heart, fa-solid fa-truck,
fa-solid fa-star, fa-solid fa-snowflake, fa-solid fa-ban, fa-solid fa-undo,
fa-solid fa-certificate, fa-solid fa-sun, fa-solid fa-seedling, fa-solid fa-droplet

"nutritions" yalnızca YENEBİLİR/İÇİLEBİLİR ürünler (süt, peynir, yoğurt, yumurta, bal, pekmez, bulgur, salça, tereyağı vb) için doldurulmalı. 5-8 satır besin değeri ekle: Enerji, Protein, Yağ, Karbonhidrat, Lif, Kalsiyum vb gerçekçi değerlerle.

YENMEYEN ürünler (kurbanlık hayvan, adak hayvan vb) için nutritions boş dizi [] olmalı.

Yanıtını SADECE JSON olarak ve TÜRKÇE değerlerle ver.
PROMPT;
    }

    /**
     * @return array<string, mixed>|null
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

        $out = [
            'short_description' => isset($data['short_description']) ? (string) $data['short_description'] : '',
            'description'       => isset($data['description']) ? (string) $data['description'] : '',
            'meta_title'        => isset($data['meta_title']) ? mb_substr((string) $data['meta_title'], 0, 60, 'UTF-8') : '',
            'meta_description'  => isset($data['meta_description']) ? mb_substr((string) $data['meta_description'], 0, 160, 'UTF-8') : '',
            'features'          => [],
            'nutritions'        => [],
        ];

        if (isset($data['features']) && is_array($data['features'])) {
            foreach ($data['features'] as $feature) {
                if (! is_array($feature)) {
                    continue;
                }
                $icon = isset($feature['icon']) ? trim((string) $feature['icon']) : '';
                $text = isset($feature['text']) ? trim((string) $feature['text']) : '';
                if ($text === '') {
                    continue;
                }
                $out['features'][] = [
                    'icon' => $icon !== '' ? $icon : 'fa-solid fa-check',
                    'text' => $text,
                ];
            }
        }

        if (isset($data['nutritions']) && is_array($data['nutritions'])) {
            foreach ($data['nutritions'] as $nutrition) {
                if (! is_array($nutrition)) {
                    continue;
                }
                $name = isset($nutrition['name']) ? trim((string) $nutrition['name']) : '';
                if ($name === '') {
                    continue;
                }
                $out['nutritions'][] = [
                    'name'        => $name,
                    'per_value'   => isset($nutrition['per_value']) ? trim((string) $nutrition['per_value']) : '',
                    'daily_value' => isset($nutrition['daily_value']) ? trim((string) $nutrition['daily_value']) : '',
                ];
            }
        }

        return $out;
    }
}
