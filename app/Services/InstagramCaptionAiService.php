<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HashtagPool;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Instagram caption AI üretici — Gemini ile çalışır.
 *
 * Senaryo:
 *  - Admin "AI ile Üret" butonuna tıklar
 *  - Ürün seçeneği varsa ürünün adı/açıklaması context olarak verilir
 *  - Yoksa serbest tema input'undan üretir
 *  - Caption + 10-15 hashtag döner
 *  - HashtagPool havuzundaki aktif tag'ler context'e verilir (öncelikli kullanım)
 *  - Üretim sonrası havuzdaki kullanılan tag'lerin usage_count artar
 */
final class InstagramCaptionAiService
{
    public function __construct(
        private readonly AiService $ai,
    ) {}

    /**
     * Aktif hashtag havuzu — AI prompt'a inject edilir.
     */
    private function poolHints(): string
    {
        $pool = HashtagPool::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->limit(50)
            ->pluck('tag')
            ->all();

        if ($pool === []) {
            return '';
        }

        return "\nKULLANABİLECEĞİN MEVCUT HAVUZ HASHTAG'LERİ (öncelikli kullan, eksiklerini tamamla):\n#"
            . implode(' #', $pool);
    }

    /**
     * Üretilen hashtag'lerden havuzda olanların usage_count'ını artır.
     *
     * @param  list<string>  $hashtags
     */
    private function incrementPoolUsage(array $hashtags): void
    {
        if ($hashtags === []) {
            return;
        }

        $normalized = array_map(fn (string $t) => HashtagPool::normalize($t), $hashtags);
        $normalized = array_filter($normalized);
        if ($normalized === []) {
            return;
        }

        $matching = HashtagPool::query()->whereIn('tag', $normalized)->get();
        foreach ($matching as $row) {
            $row->incrementUsage();
        }
    }

    /**
     * @return array{success: bool, message: string, caption?: string, hashtags?: list<string>}
     */
    public function generateForProduct(Product $product, ?string $tone = null): array
    {
        $tone        = $tone ?? 'samimi ve doğal';
        $description = trim((string) ($product->description ?? $product->short_description ?? ''));
        $poolHints   = $this->poolHints();

        $systemInstruction = <<<EOT
        Sen bir Türk doğal/organik gıda firmasının Instagram sosyal medya yöneticisisin.
        Çorum'dan köy ürünleri satan "Orhan Babanın Çiftliği" adına Instagram caption'ları yazıyorsun.

        KURALLAR:
        - Türkçe yaz
        - Tonlama: {$tone}
        - 600-1200 karakter arası caption
        - Emoji kullan ama abartma (3-7 emoji yeterli)
        - Kısa, akıcı, müşterinin merak edeceği vurgular
        - Sonda CTA olsun (örn: "Sipariş için DM'den ulaşabilirsiniz" veya "Linkten incele")
        - "Bio'daki link" değil, "DM'den" kullan
        - 10-15 hashtag öner (Türkçe + gıda + bölge ağırlıklı)
        - Hashtag'lerde alakasız trend hashtag'ler kullanma{$poolHints}

        ÇIKTI FORMATI (kesinlikle uy):
        ===CAPTION===
        [caption metni burada]
        ===HASHTAGS===
        hashtag1, hashtag2, hashtag3, ...
        EOT;

        $userPrompt = <<<EOT
        Aşağıdaki ürün için Instagram post caption ve hashtag öner.

        ÜRÜN ADI: {$product->name}
        AÇIKLAMA: {$description}
        EOT;

        $result = $this->ai->generate($systemInstruction, $userPrompt, 0.85);

        if (! ($result['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'AI içerik üretemedi.',
            ];
        }

        $parsed = $this->parseResponse((string) ($result['content'] ?? ''));
        if ($parsed['success'] ?? false) {
            $this->incrementPoolUsage($parsed['hashtags'] ?? []);
        }

        return $parsed;
    }

    /**
     * @return array{success: bool, message: string, caption?: string, hashtags?: list<string>}
     */
    public function generateFromTopic(string $topic, ?string $tone = null): array
    {
        $tone = $tone ?? 'samimi ve doğal';
        $poolHints = $this->poolHints();

        // Yasak etiket örnekleri config'ten — AI'a "bunlar gibi olmasın" diye yollanır.
        // Filtreleme InstagramHashtagBuilder tarafında ayrıca blacklist/whitelist
        // testleriyle yapılır; bu prompt sadece AI'ı doğru yöne yönlendirir.
        $forbiddenSamples = (array) config('instagram-hashtag-domain.forbidden_examples', [
            'kesfet', 'viral', 'foryou', 'trend', 'takipet',
        ]);
        $forbiddenLine = '#' . implode(', #', $forbiddenSamples);

        $systemInstruction = <<<EOT
        Sen bir Türk doğal/organik gıda firmasının Instagram sosyal medya yöneticisisin.
        Çorum'dan köy ürünleri satan "Orhan Babanın Çiftliği" adına Instagram caption'ları yazıyorsun.

        KURALLAR:
        - Türkçe yaz, ton: {$tone}
        - 600-1200 karakter arası caption
        - 3-7 emoji
        - Sonda CTA (DM, sipariş veya soru)
        - 10-15 hashtag (Türkçe + gıda + bölge){$poolHints}

        HASHTAG KISITLAMALARI (SIKI — uy):
        - SADECE konu ile alakalı olsun: çiftlik, tarım, hayvancılık, doğal/organik gıda,
          köy ürünleri (peynir, tereyağı, süt, bal vb.), Türk mutfağı, lezzet/tarif,
          kalite (taze, ev yapımı, katkısız), şehir adı (yalnız konuda geçenler).
        - YASAK ETİKETLER (kesinlikle kullanma):
          {$forbiddenLine}
        - Discovery / takipleşme spam'i YASAK (keşfet, viral, foryou, trend, takipet vb.).
        - Siyaset, parti, siyasetçi, asker, şehit YASAK.
        - Ünlü, futbol takımı, magazin YASAK.
        - Genel duygu (sevgi, mutluluk, günaydın, tatil) YASAK — konu dışı.
        - Argo, küfür, +18, içki/sigara, uyuşturucu, şiddet YASAK.
        - Konu dışı hiçbir şey YASAK — yalnızca ürün/çiftlik/tarım odaklı kal.

        ÇIKTI FORMATI (kesinlikle uy):
        ===CAPTION===
        [caption metni]
        ===HASHTAGS===
        hashtag1, hashtag2, hashtag3, ...
        EOT;

        $userPrompt = "Konu: {$topic}\n\nBu konu için Instagram post caption ve hashtag öner.";

        $result = $this->ai->generate($systemInstruction, $userPrompt, 0.85);

        if (! ($result['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'AI içerik üretemedi.',
            ];
        }

        $parsed = $this->parseResponse((string) ($result['content'] ?? ''));
        if ($parsed['success'] ?? false) {
            $this->incrementPoolUsage($parsed['hashtags'] ?? []);
        }

        return $parsed;
    }

    /**
     * @return array{success: bool, message: string, caption?: string, hashtags?: list<string>}
     */
    private function parseResponse(string $content): array
    {
        $content = trim($content);

        // ===CAPTION=== ve ===HASHTAGS=== bölümlerini ayır
        if (preg_match('/===\s*CAPTION\s*===\s*(.+?)\s*===\s*HASHTAGS\s*===\s*(.+)/is', $content, $m)) {
            $caption = trim($m[1]);
            $hashtagsRaw = trim($m[2]);
        } else {
            // Fallback: tüm içeriği caption olarak al, hashtag çıkarmayı dene
            $caption = preg_replace('/#[a-zA-ZçğıöşüÇĞİÖŞÜ0-9_]+/u', '', $content) ?? $content;
            $caption = trim((string) preg_replace('/\s+/', ' ', $caption));
            preg_match_all('/#([a-zA-ZçğıöşüÇĞİÖŞÜ0-9_]+)/u', $content, $tagMatches);
            $hashtagsRaw = implode(', ', $tagMatches[1] ?? []);
        }

        if ($caption === '') {
            return [
                'success' => false,
                'message' => 'AI çıktısı parse edilemedi (caption boş).',
            ];
        }

        $hashtags = $this->normalizeHashtags($hashtagsRaw);

        return [
            'success'  => true,
            'message'  => 'AI başarıyla içerik üretti.',
            'caption'  => Str::limit($caption, 2000, ''),
            'hashtags' => $hashtags,
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeHashtags(string $raw): array
    {
        $parts = preg_split('/[\s,]+/u', $raw) ?: [];
        $clean = [];

        foreach ($parts as $part) {
            $tag = trim($part, " \t\n\r\0\x0B#");
            if ($tag === '') {
                continue;
            }
            $clean[] = $tag;
        }

        return array_slice(array_values(array_unique($clean)), 0, 30);
    }
}
