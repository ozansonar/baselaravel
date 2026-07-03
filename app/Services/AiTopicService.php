<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AiLogSource;
use App\Enums\AiLogStatus;
use App\Models\AiLog;
use App\Models\Product;
use App\Models\ProductTopic;
use App\Services\Concerns\ParsesAiJsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Topic cluster generator: bir ürünü alır, AI'a "search intent" çeşitliliği
 * olan 7 farklı blog başlığı önertir, DB'ye kaydeder. Admin daha sonra her
 * birini tek tıkla mevcut BlogGenerationService ile blog yazısına çevirir.
 *
 * 7 hedef intent (spec'te listelenen):
 *  1. how_to       — "Nasıl Yapılır?"
 *  2. comparison   — "Market vs Köy Farkı"
 *  3. storage      — "Saklama / Buzdolabı"
 *  4. purchase     — "Online Sipariş / Çorum'dan"
 *  5. recipe       — "Kahvaltı / Tarif"
 *  6. health       — "Sağlık Faydaları"
 *  7. buying_guide — "Alırken Dikkat / Kalite"
 */
final class AiTopicService
{
    use ParsesAiJsonResponse;

    /** @var list<string> */
    private const array INTENTS = [
        'how_to',
        'comparison',
        'storage',
        'purchase',
        'recipe',
        'health',
        'buying_guide',
    ];

    public function __construct(
        private readonly AiService $aiService,
    ) {}

    /**
     * Generate 7 cluster topics for a product, persist to product_topics,
     * and return the array of created records.
     *
     * @return array{success: bool, message: string, topics?: list<ProductTopic>}
     */
    public function generateForProduct(Product $product): array
    {
        $systemInstruction = $this->buildSystemInstruction();
        $userPrompt        = $this->buildUserPrompt($product);

        $aiLog = AiLog::create([
            'status'       => AiLogStatus::Started,
            'source'       => AiLogSource::TopicCluster,
            'product_name' => $product->name,
            'prompt'       => mb_substr($systemInstruction . "\n\n---\n\n" . $userPrompt, 0, 10000, 'UTF-8'),
        ]);

        // responseSchema → Gemini bu 7-elemanlı array yapısına UYMAK ZORUNDA.
        // Her item {title, intent} olacak, hiçbir alan atlanamaz.
        $topicSchema = [
            'type'  => 'ARRAY',
            'items' => [
                'type'       => 'OBJECT',
                'properties' => [
                    'title'  => ['type' => 'STRING'],
                    'intent' => [
                        'type' => 'STRING',
                        'enum' => array_values(self::INTENTS),
                    ],
                ],
                'required'         => ['title', 'intent'],
                'propertyOrdering' => ['title', 'intent'],
            ],
        ];
        $result = $this->aiService->generate($systemInstruction, $userPrompt, 0.8, $topicSchema);

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

        $parsed = $this->parseResponse($result['content']);

        if ($parsed === null) {
            Log::warning('AiTopicService: JSON parse hatası', ['raw' => $result['content']]);
            $aiLog->update([
                'status'        => AiLogStatus::ParseError,
                'error_message' => 'AI yanıtı geçerli JSON formatında değil.',
            ]);

            return ['success' => false, 'message' => 'AI yanıtı geçerli JSON formatında değil.'];
        }

        $aiLog->update([
            'status'          => AiLogStatus::Success,
            'parsed_response' => $parsed,
        ]);

        // Persist as ProductTopic rows. Default: active, not yet converted.
        $created = [];
        foreach ($parsed as $i => $row) {
            $created[] = ProductTopic::create([
                'product_id'    => $product->id,
                'title'         => $row['title'],
                'search_intent' => $row['intent'],
                'sort_order'    => $i,
                'is_active'     => true,
            ]);
        }

        return [
            'success' => true,
            'message' => count($created) . ' konu üretildi.',
            'topics'  => $created,
        ];
    }

    /**
     * Hatalı topic cluster log'unu yeniden işle: raw_response'tan parse et,
     * ProductTopic satırlarını oluştur, log'u Success'e güncelle.
     *
     * @return array{success: bool, message: string, log: AiLog, topics?: list<ProductTopic>}
     */
    public function retryFromLog(AiLog $log): array
    {
        if (empty($log->raw_response)) {
            return [
                'success' => false,
                'message' => 'Bu log\'da raw_response yok — yeniden işlenemez.',
                'log'     => $log,
            ];
        }

        $parsed = $this->parseResponse($log->raw_response);
        if ($parsed === null) {
            $message = 'Raw response yeniden parse edilemedi (JSON dizisi geçersiz veya alanlar eksik).';
            $log->update([
                'status'        => AiLogStatus::ParseError,
                'error_message' => $message,
            ]);
            return ['success' => false, 'message' => $message, 'log' => $log->fresh()];
        }

        // product_name'den Product bul (id log'da tutulmuyor — eski şema).
        $product = Product::query()->where('name', $log->product_name)->first();
        if ($product === null) {
            $message = "Ürün bulunamadı: '{$log->product_name}'. Belki silinmiş olabilir.";
            $log->update(['error_message' => $message]);
            return ['success' => false, 'message' => $message, 'log' => $log->fresh()];
        }

        // Bu üründe daha önce üretilmiş ve aynı log'a bağlı topic'ler varsa
        // tekrar oluşturmayız — idempotent davranış.
        $existing = ProductTopic::query()->where('product_id', $product->id)->count();

        $created = [];
        foreach ($parsed as $i => $row) {
            $created[] = ProductTopic::create([
                'product_id'    => $product->id,
                'title'         => $row['title'],
                'search_intent' => $row['intent'],
                'sort_order'    => $existing + $i,
                'is_active'     => true,
            ]);
        }

        $log->update([
            'status'          => AiLogStatus::Success,
            'parsed_response' => $parsed,
            'error_message'   => null,
            'error_details'   => null,
        ]);

        Log::info('AiTopicService retry başarılı', [
            'log_id'     => $log->id,
            'product_id' => $product->id,
            'count'      => count($created),
        ]);

        return [
            'success' => true,
            'message' => count($created) . ' konu oluşturuldu (' . $product->name . ').',
            'log'     => $log->fresh(),
            'topics'  => $created,
        ];
    }

    private function buildSystemInstruction(): string
    {
        return <<<'PROMPT'
Rol: Sen, "Orhan Babanın Çiftliği" için çalışan, deneyimli bir Türkçe SEO uzmanısın. Çorum Büyük Palabıyık Köyü'nden Türkiye geneline doğal köy ürünleri (süt, peynir, tereyağı, yumurta vb.) gönderen bir aile işletmesi için "topic cluster" stratejisi kuruyorsun.

GÖREV: Verilen ürün etrafında, farklı search intent'leri kapsayacak 7 blog başlığı öner. Her başlık BAĞIMSIZ bir Google araması olarak hedeflenmeli — kullanıcının farklı niyetlerine (öğrenme, karşılaştırma, satın alma, kullanım) hitap etmeli.

KURALLAR:
- Dil: Sadece Türkçe.
- Başlıklar 50-65 karakter (SEO uyumlu).
- Her kelimenin ilk harfi büyük (örn: "Köy Tereyağı Nasıl Saklanır?").
- Ürünün ana adı her başlıkta geçsin.
- "Çorum" veya "köy" kelimesi en az 2-3 başlıkta geçsin (yerel SEO).
- Klişeden kaçın, pratik fayda vurgusu yap (sayı, soru, "nasıl" gibi tıklamayı artıran kalıplar).
- Yalan veya abartılı sağlık iddiası YASAK.

ÇIKTI FORMATI — KESİN KURALLAR (HER BİRİNE UYULMASI ZORUNLU)
Yanıtın SADECE ve SADECE saf JSON dizisi olacak (markdown fence ``` YASAK, açıklama metni YASAK, selamlama YASAK).

YAPISI:
[
  { "title": "...", "intent": "<intent_kodu>" },
  ... (toplam 7 obje)
]

ZORUNLU JSON KURALLARI:
- TAMAMI tek bir JSON dizisi olacak — başında/sonunda hiçbir ek karakter yok.
- TAM OLARAK 7 obje olacak — eksik veya fazla KESİNLİKLE OLMAZ.
- Her obje sadece "title" ve "intent" alanlarına sahip olacak. Başka alan EKLEME.
- "title" zorunlu, string, boş olamaz.
- "intent" zorunlu, aşağıdaki listeden TAM EŞLEŞEN bir kod olacak.
- Objeleri ayıran virgülleri ATLA MA. Son objeden sonra trailing comma KULLANMA.
- Yorum satırı YOK. Backslash YOK. URL'lerde / kullan.
- title değerlerinin içinde düz çift tırnak (") kullanma; vurgu için tek tırnak (') veya « » kullan.

Intent kodları (her birini 1 KEZ kullan):
- how_to       → üretim/yapım süreci
- comparison   → market/köy karşılaştırma
- storage      → saklama/buzdolabı/buzluk
- purchase     → Çorum'dan online sipariş/kargo
- recipe       → kahvaltı/tarif/yemek kullanımı
- health       → sağlık faydaları (bilimsel, abartısız)
- buying_guide → alırken dikkat edilecekler/kalite anlama
PROMPT;
    }

    private function buildUserPrompt(Product $product): string
    {
        $category = $product->category?->name ?? 'belirtilmedi';
        $short    = trim((string) ($product->short_description ?? '')) ?: '(kısa açıklama yok)';

        return <<<PROMPT
Ürün: {$product->name}
Kategori: {$category}
Kısa Açıklama: {$short}

Bu ürün için yukarıda tanımlanan 7 farklı search intent'i kapsayan blog başlıklarını üret.
PROMPT;
    }

    /**
     * Yaygın JSON syntax bug'larını tamir et: eksik virgül + trailing comma.
     */
    private function repairJsonSyntax(string $raw): string
    {
        // Eksik virgül: "value"\s*\n\s*"key": → "value",\n  "key":
        $fixed = preg_replace(
            '/("(?:[^"\\\\]|\\\\.)*")(\s*\r?\n\s*)("[A-Za-z_][A-Za-z0-9_]*"\s*:)/',
            '$1,$2$3',
            $raw,
        );
        $fixed = is_string($fixed) ? $fixed : $raw;

        // Trailing comma temizliği
        $fixed = (string) preg_replace('/,(\s*[}\]])/', '$1', $fixed);

        return $fixed;
    }

    /**
     * Parse the AI response and validate that every required intent appears.
     * Missing intents are filled with a sensible fallback so the UI always
     * has 7 rows.
     *
     * @return list<array{title: string, intent: string}>|null
     */
    private function parseResponse(?string $raw): ?array
    {
        if (empty($raw)) {
            return null;
        }

        $extracted = $this->extractJsonFromAiResponse($raw);
        $data      = $extracted !== null ? json_decode($extracted, true) : null;

        // İlk parse fail ise syntax repair dene (eksik virgül, trailing comma)
        if ((! is_array($data) || $data === []) && $extracted !== null) {
            $repaired = $this->repairJsonSyntax($extracted);
            if ($repaired !== $extracted) {
                $repairedData = json_decode($repaired, true);
                if (is_array($repairedData) && $repairedData !== []) {
                    $data = $repairedData;
                }
            }
        }

        if (! is_array($data) || $data === []) {
            return null;
        }

        $rows = [];
        $seenIntents = [];

        foreach ($data as $row) {
            if (! is_array($row) || ! isset($row['title']) || ! isset($row['intent'])) {
                continue;
            }
            $title  = trim(strip_tags((string) $row['title']));
            $intent = trim((string) $row['intent']);

            if ($title === '' || ! in_array($intent, self::INTENTS, true)) {
                continue;
            }

            if (in_array($intent, $seenIntents, true)) {
                continue; // duplicate intent → skip extras
            }

            $rows[] = [
                'title'  => mb_substr($title, 0, 200, 'UTF-8'),
                'intent' => $intent,
            ];
            $seenIntents[] = $intent;
        }

        if ($rows === []) {
            return null;
        }

        return $rows;
    }
}
