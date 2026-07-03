<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AiLogSource;
use App\Enums\AiLogStatus;
use App\Models\AiLog;
use App\Services\Concerns\ParsesAiJsonResponse;
use Illuminate\Support\Facades\Log;

final class PageAiService
{
    use ParsesAiJsonResponse;

    public function __construct(
        private readonly AiService $aiService,
    ) {}

    /**
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function generate(string $title): array
    {
        $title = trim($title);

        if ($title === '') {
            return ['success' => false, 'message' => 'Sayfa başlığı boş olamaz.'];
        }

        $systemInstruction = $this->buildSystemInstruction();
        $userPrompt = $this->buildUserPrompt($title);

        $aiLog = AiLog::create([
            'status'       => AiLogStatus::Started,
            'source'       => AiLogSource::PageFill,
            'product_name' => $title,
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
            Log::warning('PageAI: JSON parse hatası', ['raw' => $result['content']]);
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
        return 'Rol: Sen, "Orhan Babanın Çiftliği" için çalışan, deneyimli bir Türkçe web içerik yazarı ve SEO uzmanısın. İşletmenin hakkında, hizmetler, politikalar gibi statik sayfaları için profesyonel, samimi ve güvenilir içerikler yazıyorsun.

KURALLAR:
- Dil: Kesinlikle Türkçe
- Ton: Samimi, profesyonel, güvenilir, aile işletmesi havası
- SEO uyumlu: anahtar kelimeleri doğal şekilde kullan
- İşletmeci: Orhan Gazi SONAR
- İşletme: Orhan Babanın Çiftliği
- Doğal, sağlıklı ve köy ürünleri odaklı içerikler
- Kesinlikle yalan bilgi veya abartılı iddialar kullanma
- HTML etiketleri: yalnızca <p>, <h2>, <h3>, <strong>, <em>, <ul>, <li>, <ol>
- Link veya a etiketi koyma';
    }

    private function buildUserPrompt(string $title): string
    {
        return <<<PROMPT
Aşağıdaki başlıkla ilgili detaylı bir sayfa içeriği üret.

Sayfa Başlığı: {$title}

ÇIKTI FORMATI: Sadece saf JSON. Markdown etiketi (```json) kullanma. Tam olarak şu anahtarlar olmalı:

{
  "excerpt": "2-3 cümlelik kısa özet (maks 280 karakter). Sayfanın ne hakkında olduğunu özetleyen metin.",
  "content": "Detaylı HTML sayfa içeriği. 300-600 kelime. <h2>, <h3>, <p>, <ul>, <li>, <ol>, <strong>, <em> kullan. Bilgi verici, SEO uyumlu, okunması kolay paragraflar yaz. Alt başlıklarla yapılandır. Link koyma.",
  "meta_title": "SEO başlık (50-60 karakter). Anahtar kelime başta. Her kelimenin ilk harfi büyük.",
  "meta_description": "SEO açıklama (150-160 karakter). Tıklanma oranını artıracak, anahtar kelime içeren davetkâr metin."
}

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

        return [
            'excerpt'          => isset($data['excerpt']) ? mb_substr(trim((string) $data['excerpt']), 0, 300, 'UTF-8') : '',
            'content'          => isset($data['content']) ? (string) $data['content'] : '',
            'meta_title'       => isset($data['meta_title']) ? mb_substr(trim((string) $data['meta_title']), 0, 60, 'UTF-8') : '',
            'meta_description' => isset($data['meta_description']) ? mb_substr(trim((string) $data['meta_description']), 0, 160, 'UTF-8') : '',
        ];
    }
}
