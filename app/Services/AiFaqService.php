<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Faq;
use App\Models\Product;
use App\Services\Concerns\ParsesAiJsonResponse;
use Illuminate\Support\Facades\Log;

final class AiFaqService
{
    use ParsesAiJsonResponse;

    public function __construct(
        private readonly AiService $aiService,
    ) {}

    /**
     * @return array{success: bool, message: string, faqs: list<Faq>}
     */
    public function generate(int $productId, int $count = 6): array
    {
        $product = Product::with('category')->findOrFail($productId);
        $count = max(3, min(10, $count));

        $existingQuestions = Faq::forProduct($productId)
            ->pluck('question')
            ->all();

        $systemPrompt = $this->buildSystemPrompt($product, $count, $existingQuestions);
        $userPrompt = $this->buildUserPrompt($product, $count);

        $result = $this->aiService->generate($systemPrompt, $userPrompt, 0.8);

        if (! $result['success']) {
            return ['success' => false, 'message' => 'AI yanıt üretemedi: ' . $result['message'], 'faqs' => []];
        }

        $parsed = $this->parseResponse($result['content']);

        if ($parsed === null || empty($parsed)) {
            Log::error('AiFaqService: JSON parse hatası', ['raw' => $result['content']]);
            return ['success' => false, 'message' => 'AI yanıtı geçerli JSON formatında değil.', 'faqs' => []];
        }

        $faqs = [];
        $maxOrder = (int) Faq::forProduct($productId)->max('sort_order');

        foreach ($parsed as $index => $item) {
            $faqs[] = Faq::create([
                'product_id' => $product->id,
                'question'   => $item['question'],
                'answer'     => $item['answer'],
                'sort_order' => $maxOrder + $index + 1,
                'is_active'  => true,
            ]);
        }

        Log::info('AI ürün SSS üretildi', ['product_id' => $product->id, 'count' => count($faqs)]);

        return [
            'success' => true,
            'message' => count($faqs) . ' soru-cevap üretildi.',
            'faqs'    => $faqs,
        ];
    }

    /**
     * @param list<string> $existingQuestions
     */
    private function buildSystemPrompt(Product $product, int $count, array $existingQuestions): string
    {
        $categoryName = $product->category?->name ?? 'Genel';

        $prompt = 'ROL
Sen, "Orhan Babanın Çiftliği" (Büyük Palabıyık Köyü, Çorum) ürünleri için müşteri perspektifinden gerçekçi SSS (Sıkça Sorulan Sorular) üreten bir asistansın.

ÜRÜN BİLGİSİ
- Ürün: ' . $product->name . '
- Kategori: ' . $categoryName . '
- Açıklama: ' . mb_substr(strip_tags((string) $product->description), 0, 500, 'UTF-8') . '

ZORUNLU KURALLAR
1. ' . $count . ' adet soru-cevap üret.
2. Sorular GERÇEK müşterilerin sorabileceği sorular olmalı — yapay/zorlama olmasın.
3. Her soru farklı bir konudan olmalı: saklama koşulları, raf ömrü, üretim yöntemi, içerik/katkı maddesi, allerjen bilgisi, çocuklara uygunluk, teslimat/kargo, fiyat/miktar, kalite farkı, kullanım önerisi, organik sertifika, sipariş süreci.
4. Cevaplar 2-4 cümle, bilgilendirici ve güven veren tonda olmalı.
5. Cevaplarda "Orhan Babanın Çiftliği" veya "Çorum" doğal şekilde geçebilir (hepsinde değil, %30-40\'ında).
6. Soru formatı: Doğrudan soru cümlesi ("Bu ürün nasıl saklanır?" gibi).
7. Cevap formatı: Düz metin, HTML etiketi KULLANMA.
8. Samimi ama profesyonel ton — reklam gibi değil, bilgilendirici.
9. SEO uyumlu: Sorularda ürün adı veya kategorisi doğal şekilde geçsin.';

        if (! empty($existingQuestions)) {
            $prompt .= "\n\nTEKRAR YASAĞI\nAşağıdaki sorular zaten mevcut — aynı veya benzer soruları TEKRARLAMA:\n- " . implode("\n- ", $existingQuestions);
        }

        return $prompt;
    }

    private function buildUserPrompt(Product $product, int $count): string
    {
        return '"' . $product->name . '" ürünü için ' . $count . ' adet SSS üret.

ÇIKTI FORMATI
- Sadece saf JSON array dön. Markdown bloğu (```json) YASAK.
- JSON dışında HİÇBİR metin yazma.

[
  {
    "question": "Soru metni?",
    "answer": "Cevap metni."
  }
]

Toplam ' . $count . ' adet soru-cevap üret.';
    }

    /**
     * @return list<array{question: string, answer: string}>|null
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

        if (isset($data['faqs']) && is_array($data['faqs'])) {
            $data = $data['faqs'];
        }

        $faqs = [];
        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = trim(strip_tags((string) ($item['question'] ?? '')));
            $answer = trim(strip_tags((string) ($item['answer'] ?? '')));

            if ($question === '' || $answer === '') {
                continue;
            }

            $faqs[] = [
                'question' => mb_substr($question, 0, 500, 'UTF-8'),
                'answer'   => mb_substr($answer, 0, 2000, 'UTF-8'),
            ];
        }

        return empty($faqs) ? null : $faqs;
    }
}
