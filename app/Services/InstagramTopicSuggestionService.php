<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Instagram bulk import için "konu havuzu" önerileri üretir.
 *
 * Kullanım: /admin/instagram-posts/bulk → AI Prompt modal → "30 Konu Öner".
 * AI'dan {count} farklı, çeşitli, marka odaklı Instagram post konusu üretir.
 *
 * Caption ÜRETMEZ — sadece konu/başlık önerileri verir.
 * Kullanıcı beğendiklerini seçer → seçili konular AI prompt'a inject edilir.
 */
final class InstagramTopicSuggestionService
{
    private const MIN_COUNT = 10;

    private const MAX_COUNT = 50;

    private const CACHE_TTL_SECONDS = 60;

    private const TOPIC_MAX_LENGTH = 80;

    public function __construct(
        private readonly AiService $ai,
    ) {}

    /**
     * @return array{success: bool, topics: list<string>, message: string}
     */
    public function suggest(int $count = 30, ?string $theme = null): array
    {
        $count = max(self::MIN_COUNT, min($count, self::MAX_COUNT));
        $theme = trim((string) ($theme ?? ''));
        if (mb_strlen($theme) > 100) {
            $theme = mb_substr($theme, 0, 100);
        }

        $cacheKey = 'ig_topic_suggest:' . md5($count . '|' . $theme);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($count, $theme): array {
            return $this->generate($count, $theme);
        });
    }

    /**
     * @return array{success: bool, topics: list<string>, message: string}
     */
    private function generate(int $count, string $theme): array
    {
        $brand = trim((string) Setting::getValue('site_name', 'Orhan Babanın Çiftliği'));
        if ($brand === '') {
            $brand = 'Orhan Babanın Çiftliği';
        }

        $system = $this->buildSystemPrompt($brand, $count, $theme);
        $user   = $this->buildUserPrompt($count, $theme);

        $res = $this->ai->generate($system, $user, 0.85);

        if (! ($res['success'] ?? false)) {
            return [
                'success' => false,
                'topics'  => [],
                'message' => $res['message'] ?? 'AI çağrısı başarısız.',
            ];
        }

        $topics = $this->parseTopics((string) ($res['content'] ?? ''));

        if ($topics === []) {
            return [
                'success' => false,
                'topics'  => [],
                'message' => 'AI yanıtı çözümlenemedi (geçersiz JSON veya boş liste).',
            ];
        }

        // Hedef sayıyı aşma
        $topics = array_slice($topics, 0, $count);

        return [
            'success' => true,
            'topics'  => $topics,
            'message' => sprintf('%d konu önerisi üretildi.', count($topics)),
        ];
    }

    private function buildSystemPrompt(string $brand, int $count, string $theme): string
    {
        $themeBlock = $theme !== ''
            ? "\n\n=== TEMA ===\nTüm konular \"{$theme}\" temasına uygun olmalı. Tema içeriği bağlamında konu çeşitliliği sağla."
            : '';

        return <<<EOT
Sen Çorum'dan köy ürünleri satan "{$brand}" markasının Instagram içerik stratejistisin.

=== MARKA BAĞLAMI ===
- Doğal/organik gıda firması (Çorum/Büyük Palabıyık Köyü)
- Ürünler: süt, yoğurt, ayran, peynir (beyaz/eski/lor/çökelek), tereyağı (yayık), bal, yumurta, tarhana, pekmez, reçel
- Hedef kitle: doğal/sağlıklı beslenme arayan, geleneksel lezzeti seven Türk müşteriler
- Marka tonu: samimi, sıcak, doğal, geleneksel, ev hissi

=== GÖREV ===
Tam olarak {$count} farklı Instagram post KONU başlığı üret.

=== ÇEŞİTLİLİK KURALLARI (uy) ===
1. Aşağıdaki kategorilere DENGELİ dağılım yap (her kategoriden en az 2-3 konu):
   - Ürün tanıtımı (tek bir ürünü öne çıkar)
   - Üretim süreci / perde arkası (yayık, sağım, mayalama, hasat)
   - Çiftlik hayatı / doğa (sabah, mevsim, hayvanlar, doğa fotoğrafları)
   - Tarif önerisi (Türk mutfağı, mevsimsel)
   - Kalite vurgusu / eğitim (doğal vs. endüstriyel, hijyen, katkısızlık)
   - Müşteri / topluluk (yorum, soru-cevap, anket, sipariş hikayesi)
   - Mevsimsel / özel gün (hafta sonu, mevsim geçişi, bayram, özel gün)
2. Konular ÖZGÜN olmalı — klişe, içi boş başlıklar YASAK ("Doğal lezzet", "En iyi peynir" gibi)
3. Her konu somut ve görselleştirilebilir olsun (AI veya panel görseli üretebilsin)
4. Birbirine ÇOK BENZER konu YOK (örn. "Yayık tereyağı" + "Yayık peyniri" iki ayrı konu sayılmaz, çeşitlendir)
5. Her konu maksimum {$this->maxLengthHint()} karakter (kısa, özet, başlık tarzı)
6. Türkçe — yabancı kelime yerine Türkçe karşılığı tercih et{$themeBlock}

=== ÇIKTI FORMATI ===
SADECE geçerli JSON dizi döndür. Markdown, kod bloğu, açıklama, başlık YASAK.

Örnek format:
["Sabah sağımı sonrası taze süt", "Yayık tereyağı yapımı (zaman lapse)", "Çökelek nasıl yapılır?", ...]

Kesinlikle şu format dışında bir şey yazma. Sadece JSON dizi.
EOT;
    }

    private function buildUserPrompt(int $count, string $theme): string
    {
        $themeLine = $theme !== ''
            ? " — \"{$theme}\" teması ekseninde"
            : '';

        return "Bana {$count} farklı, çeşitli, özgün Instagram post konusu üret{$themeLine}. Sadece JSON dizi döndür.";
    }

    private function maxLengthHint(): int
    {
        return self::TOPIC_MAX_LENGTH;
    }

    /**
     * AI yanıtından JSON array çıkarır. Markdown code fence (```json...```) varsa temizler.
     *
     * @return list<string>
     */
    private function parseTopics(string $raw): array
    {
        $cleaned = trim($raw);

        // Markdown code fence temizliği (AI bazen ```json ile sarıyor)
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s*```$/', '', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        // İlk [ ile son ] arasını al (AI öncesi/sonrası açıklama yazsa bile yakalanır)
        $start = strpos($cleaned, '[');
        $end   = strrpos($cleaned, ']');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }
        $jsonSlice = substr($cleaned, $start, $end - $start + 1);

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($jsonSlice, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $topics = [];
        $seen   = [];
        foreach ($decoded as $item) {
            if (! is_string($item)) {
                continue;
            }
            $topic = trim($item);
            if ($topic === '') {
                continue;
            }
            if (mb_strlen($topic) > self::TOPIC_MAX_LENGTH) {
                $topic = rtrim(mb_substr($topic, 0, self::TOPIC_MAX_LENGTH - 1)) . '…';
            }
            // Diacritics-insensitive duplicate check
            $key = mb_strtolower($topic);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $topics[] = $topic;
        }

        return array_values($topics);
    }
}
