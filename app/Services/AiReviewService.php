<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\Concerns\ParsesAiJsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class AiReviewService
{
    use ParsesAiJsonResponse;

    public function __construct(
        private readonly AiService $aiService,
    ) {}

    /**
     * @return array{success: bool, message: string, reviews: list<ProductReview>}
     */
    public function generate(int $productId, int $count): array
    {
        $product = Product::with('category')->findOrFail($productId);

        $count = max(1, min(20, $count));

        $existingNames = $this->getExistingReviewerNames($productId);
        $existingComments = $this->getExistingCommentSnippets($productId);
        $systemPrompt = $this->buildSystemPrompt($product, $count, $existingNames, $existingComments);
        $userPrompt = $this->buildUserPrompt($product, $count);

        $result = $this->aiService->generate($systemPrompt, $userPrompt, 0.9);

        if (! $result['success']) {
            return [
                'success' => false,
                'message' => 'AI yanıt üretemedi: ' . $result['message'],
                'reviews' => [],
            ];
        }

        $parsed = $this->parseResponse($result['content']);

        if ($parsed === null || empty($parsed)) {
            Log::error('AiReviewService: JSON parse hatası', ['raw' => $result['content']]);

            return [
                'success' => false,
                'message' => 'AI yanıtı geçerli JSON formatında değil. Lütfen tekrar deneyin.',
                'reviews' => [],
            ];
        }

        $reviews = [];
        $randomDates = $this->generateRandomDates($product, count($parsed));

        foreach ($parsed as $index => $item) {
            $reviews[] = ProductReview::create([
                'product_id' => $product->id,
                'name'       => $item['name'],
                'email'      => 'ai-' . Str::uuid()->toString() . '@generated.local',
                'phone'      => null,
                'rating'     => $item['rating'],
                'comment'    => $item['comment'],
                'status'     => ReviewStatus::Pending,
                'ip_address' => null,
                'created_at' => $randomDates[$index],
                'updated_at' => $randomDates[$index],
            ]);
        }

        Log::info('AI yorumlar üretildi', [
            'product_id' => $product->id,
            'count'      => count($reviews),
        ]);

        return [
            'success' => true,
            'message' => count($reviews) . ' yorum üretildi ve onay bekliyor.',
            'reviews' => $reviews,
        ];
    }

    /**
     * @param list<string> $existingNames
     * @param list<string> $existingComments
     */
    private function buildSystemPrompt(Product $product, int $count, array $existingNames, array $existingComments): string
    {
        $categoryName = $product->category?->name ?? 'Genel';

        $prompt = 'ROL
Sen, "Orhan Babanın Çiftliği" ürünleri için gerçekçi müşteri yorumları üreten bir asistansın.

İŞLETME BAĞLAMI
- İşletme: Orhan Babanın Çiftliği (Büyük Palabıyık Köyü, Çorum)
- Bölge: İç Anadolu, Çorum
- Sektör: Doğal köy ürünleri, organik çiftlik

ÜRÜN BİLGİSİ
- Ürün adı: ' . $product->name . '
- Kategori: ' . $categoryName . '
- Açıklama: ' . mb_substr(strip_tags((string) $product->description), 0, 500, 'UTF-8') . '

ZORUNLU KURALLAR
1. ' . $count . ' adet farklı yorum üret.
2. Her yorum FARKLI bir müşteri profilinden olmalı: anne, baba, sporcu, yaşlı, genç çift, diyetisyen, öğretmen, emekli, ev hanımı, üniversite öğrencisi vb.
3. İsimler gerçekçi Türk isimleri olmalı (ad + soyadın ilk harfi). Örnek: "Ayşe Y.", "Mehmet K.", "Zeynep A."
4. Her yorum 30-150 kelime arası olmalı.
5. Yorumlar samimi, doğal Türkçe ile yazılmalı — reklam gibi değil, gerçek müşteri gibi.
6. Yıldız dağılımı: yaklaşık %60 → 5 yıldız, %40 → 4 yıldız. Asla 3 veya altı olmasın.
7. Farklı açılardan yaz: lezzet, kalite, paketleme, teslimat hızı, aile tepkisi, sağlık faydası, fiyat-performans, tekrar alım.
8. Bazı yorumlarda "Çorum", "köy ürünü", "doğal" gibi ifadeler doğal şekilde geçsin (hepsinde değil, %30-40\'ında).
9. Klişe ifadelerden kaçın. Her yorum özgün olsun.
10. Bir iki yorumda küçük bir "ama" olabilir (4 yıldız yorumlarında): "paketleme biraz daha iyi olabilir", "kargo 1 gün gecikti ama ürün mükemmel" gibi.';

        if (! empty($existingNames)) {
            $prompt .= "\n\nİSİM TEKRAR YASAĞI\nAşağıdaki isimler daha önce bu ürüne yorum yapmış — bunları ve bunlara benzer isimleri KESİNLİKLE kullanma. Tamamen farklı ad ve soyad baş harfi seç:\n- " . implode("\n- ", $existingNames);
        }

        if (! empty($existingComments)) {
            $prompt .= "\n\nİÇERİK TEKRAR YASAĞI\nAşağıdaki yorumlar daha önce bu ürün için yazılmış. Bunlarla aynı cümle kalıplarını, benzer açılış/kapanışları, aynı konuları KULLANMA. Her yeni yorum tamamen farklı bir bakış açısı, farklı bir deneyim, farklı bir anlatım tarzı olmalı:\n- " . implode("\n- ", $existingComments);
        }

        return $prompt;
    }

    private function buildUserPrompt(Product $product, int $count): string
    {
        return 'Yukarıdaki kurallara göre "' . $product->name . '" ürünü için ' . $count . ' adet müşteri yorumu üret.

ÇIKTI FORMATI
- Sadece saf JSON array dön. Markdown bloğu (```json) YASAK.
- JSON dışında HİÇBİR metin yazma.
- Her eleman şu 3 anahtar içermeli:

[
  {
    "name": "Ayşe Y.",
    "rating": 5,
    "comment": "Yorum metni burada..."
  }
]

- name: Türk ismi + soyadın ilk harfi ve nokta
- rating: 4 veya 5 (tam sayı)
- comment: 30-150 kelime, samimi Türkçe

Toplam ' . $count . ' adet yorum üret.';
    }

    /**
     * @return list<array{name: string, rating: int, comment: string}>|null
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

        // Normalize: could be [{...}] or {"reviews": [{...}]}
        if (isset($data['reviews']) && is_array($data['reviews'])) {
            $data = $data['reviews'];
        }

        $reviews = [];
        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim(strip_tags((string) ($item['name'] ?? '')));
            $rating = (int) ($item['rating'] ?? 5);
            $comment = trim(strip_tags((string) ($item['comment'] ?? '')));

            if ($name === '' || $comment === '') {
                continue;
            }

            $rating = max(4, min(5, $rating));

            $reviews[] = [
                'name'    => mb_substr($name, 0, 100, 'UTF-8'),
                'rating'  => $rating,
                'comment' => mb_substr($comment, 0, 1000, 'UTF-8'),
            ];
        }

        return empty($reviews) ? null : $reviews;
    }

    /**
     * @return list<string>
     */
    private function getExistingCommentSnippets(int $productId): array
    {
        return ProductReview::where('product_id', $productId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->pluck('comment')
            ->map(fn (string $c) => mb_substr($c, 0, 80, 'UTF-8') . '...')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function getExistingReviewerNames(int $productId): array
    {
        return ProductReview::where('product_id', $productId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<Carbon>
     */
    private function generateRandomDates(Product $product, int $count): array
    {
        $start = $product->created_at ?? Carbon::now()->subDays(90);
        $end = Carbon::now();
        $totalDays = max(1, (int) $start->diffInDays($end));

        // Yeterli gün varsa her yoruma farklı gün ver
        if ($totalDays >= $count) {
            $dayOffsets = [];
            $attempts = 0;
            while (count($dayOffsets) < $count && $attempts < $count * 10) {
                $day = random_int(0, $totalDays);
                if (! in_array($day, $dayOffsets, true)) {
                    $dayOffsets[] = $day;
                }
                $attempts++;
            }
            // Yeterli unique gün bulunamadıysa kalan slotları doldur
            while (count($dayOffsets) < $count) {
                $dayOffsets[] = random_int(0, $totalDays);
            }
            sort($dayOffsets);

            $dates = [];
            foreach ($dayOffsets as $dayOffset) {
                $dates[] = $start->copy()
                    ->addDays($dayOffset)
                    ->addHours(random_int(7, 23))
                    ->addMinutes(random_int(0, 59));
            }

            return $dates;
        }

        // Gün sayısı yorum sayısından azsa eşit aralıklı dağıt
        $intervalSeconds = max(1, (int) ($start->diffInSeconds($end) / $count));
        $dates = [];
        for ($i = 0; $i < $count; $i++) {
            $dates[] = $start->copy()
                ->addSeconds($intervalSeconds * $i)
                ->addSeconds(random_int(0, max(1, (int) ($intervalSeconds * 0.5))));
        }

        return $dates;
    }
}
