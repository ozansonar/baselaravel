<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MediaLibraryImage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Bulk import "konu" metni → kütüphaneden en uygun görseli akıllı şekilde seçer.
 *
 * Eşleşme katmanları (sırayla):
 *  1. Tag overlap — konu kelimeleri ile tag'ler arasında ortak kelime
 *  2. Theme fallback — konuda tanınan tema anahtar kelimeleri
 *  3. Season fallback — mevcut ay → mevsim eşleşmesi
 *  4. Mood + tip — son fallback
 *
 * Her seviyede birden fazla aday varsa: least-used + en eski kullanılan önce.
 * Hiç eşleşme yoksa null döner — caller AI fallback'e düşer veya hata gösterir.
 */
final class MediaLibraryMatcher
{
    /**
     * Tema → anahtar kelime sözlüğü (Türkçe).
     * Konu metninde bu kelimelerden biri geçerse o temaya öncelik verilir.
     *
     * @var array<string, list<string>>
     */
    private const THEME_KEYWORDS = [
        MediaLibraryImage::THEME_PRODUCTS  => ['ürün', 'urun', 'süt', 'sut', 'peynir', 'tereyağı', 'tereyagi', 'yoğurt', 'yogurt', 'bal', 'yumurta', 'kefir', 'ayran', 'pekmez', 'reçel', 'recel', 'zeytin', 'çökelek', 'cokelek', 'lor', 'beyaz peynir'],
        MediaLibraryImage::THEME_FARM      => ['çiftlik', 'ciftlik', 'sağım', 'sagim', 'inek', 'koyun', 'tavuk', 'kuzu', 'ahır', 'ahir', 'mera', 'köy', 'koy', 'sabah', 'akşam', 'aksam', 'günaydın', 'gunaydin', 'köy hayatı', 'koy hayati'],
        MediaLibraryImage::THEME_RECIPES   => ['tarif', 'yemek', 'kahvaltı', 'kahvalti', 'sofra', 'pişirme', 'pisirme', 'çorba', 'corba', 'börek', 'borek', 'menemen', 'mantı', 'manti', 'ekmek arası', 'soframıza'],
        MediaLibraryImage::THEME_SEASONAL  => ['ilkbahar', 'yaz', 'sonbahar', 'kış', 'kis', 'mevsim', 'bahar', 'hasat', 'tarhana', 'pekmez kaynatma', 'ayran zamanı'],
        MediaLibraryImage::THEME_HOLIDAYS  => ['bayram', 'kandil', 'ramazan', 'kurban', 'yılbaşı', 'sevgililer', 'anneler günü', 'babalar günü', 'cumhuriyet', 'çocuk bayramı', 'milli', 'şehit'],
        MediaLibraryImage::THEME_NATURE    => ['doğa', 'doga', 'çiçek', 'cicek', 'bitki', 'kır', 'kir', 'orman', 'gökyüzü', 'gokyuzu', 'manzara', 'göl', 'gol', 'dere'],
        MediaLibraryImage::THEME_LIFESTYLE => ['aile', 'paylaş', 'paylas', 'sevdiklerin', 'dost', 'çocuk', 'cocuk', 'misafir', 'ikram', 'huzur'],
    ];

    /**
     * Bulk import için 1 görsel seç.
     */
    public function pickBest(string $konu, string $mediaType, ?string $explicitTheme = null, ?string $explicitMood = null): ?MediaLibraryImage
    {
        $keywords = $this->tokenize($konu);
        $detectedTheme = $explicitTheme !== null && $explicitTheme !== '' ? $explicitTheme : $this->detectTheme($keywords);
        $currentSeason = $this->currentSeason();

        // 1) En sıkı: tag overlap + media_type
        if ($keywords !== []) {
            $img = MediaLibraryImage::query()
                ->ofMediaType($mediaType)
                ->withAnyTag($keywords)
                ->leastUsed()
                ->first();
            if ($img !== null) {
                $this->logMatch('tag_overlap', $img, $konu);
                return $img;
            }
        }

        // 2) Tema bazlı (algılanan veya manuel)
        if ($detectedTheme !== null) {
            $img = MediaLibraryImage::query()
                ->ofMediaType($mediaType)
                ->ofTheme($detectedTheme)
                ->leastUsed()
                ->first();
            if ($img !== null) {
                $this->logMatch('theme', $img, $konu, ['theme' => $detectedTheme]);
                return $img;
            }
        }

        // 3) Mood bazlı (manuel verildiyse)
        if ($explicitMood !== null && $explicitMood !== '') {
            $img = MediaLibraryImage::query()
                ->ofMediaType($mediaType)
                ->ofMood($explicitMood)
                ->leastUsed()
                ->first();
            if ($img !== null) {
                $this->logMatch('mood', $img, $konu, ['mood' => $explicitMood]);
                return $img;
            }
        }

        // 4) Mevsim bazlı
        $img = MediaLibraryImage::query()
            ->ofMediaType($mediaType)
            ->ofSeason($currentSeason)
            ->leastUsed()
            ->first();
        if ($img !== null) {
            $this->logMatch('season', $img, $konu, ['season' => $currentSeason]);
            return $img;
        }

        // 5) Son çare: tipe göre least-used (rastgele bir görsel)
        $img = MediaLibraryImage::query()
            ->ofMediaType($mediaType)
            ->leastUsed()
            ->first();
        if ($img !== null) {
            $this->logMatch('any', $img, $konu);
            return $img;
        }

        return null;
    }

    /**
     * Carousel için N görsel seç (Feed). Eşleşmeler önce, sonra least-used.
     *
     * @return Collection<int, MediaLibraryImage>
     */
    public function pickMultiple(string $konu, string $mediaType, int $count, ?string $explicitTheme = null): Collection
    {
        $count = max(1, min($count, 10));
        $keywords = $this->tokenize($konu);
        $detectedTheme = $explicitTheme !== null && $explicitTheme !== '' ? $explicitTheme : $this->detectTheme($keywords);

        $query = MediaLibraryImage::query()->ofMediaType($mediaType);
        if ($keywords !== []) {
            $query->withAnyTag($keywords);
        }
        if ($detectedTheme !== null) {
            $query->orWhere(function ($q) use ($mediaType, $detectedTheme): void {
                $q->where('media_type', $mediaType)->where('theme', $detectedTheme);
            });
        }

        $matched = $query->leastUsed()->limit($count)->get();

        if ($matched->count() >= $count) {
            return $matched;
        }

        // Yetersiz — kalanını least-used'tan tamamla (recycle dahil)
        $remaining = $count - $matched->count();
        $extra = MediaLibraryImage::query()
            ->ofMediaType($mediaType)
            ->whereNotIn('id', $matched->pluck('id')->all())
            ->leastUsed()
            ->limit($remaining)
            ->get();

        return $matched->concat($extra);
    }

    /**
     * Görseli kullanıldı olarak işaretle (usage_count +1, last_used_at = now).
     */
    public function markUsed(MediaLibraryImage $image): void
    {
        $image->update([
            'usage_count'  => $image->usage_count + 1,
            'last_used_at' => now(),
        ]);
    }

    /**
     * Konu metnini Türkçe-aware tokenize et.
     * - Lowercase + diacritics-insensitive değil (özellikle korunur)
     * - 3+ karakter token'ları al
     * - Stopword'leri çıkar
     *
     * @return list<string>
     */
    public function tokenize(string $text): array
    {
        $stopwords = [
            've', 'ile', 'için', 'icin', 'bir', 'bu', 'şu', 'su', 'o', 'da', 'de',
            'ki', 'ne', 'mi', 'mı', 'mu', 'mü', 'çok', 'cok', 'gibi', 'kadar',
            'her', 'her gün', 'günü', 'gun', 'günde', 'olan', 'olarak',
        ];

        $cleaned = mb_strtolower(trim($text));
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $cleaned) ?? $cleaned;
        $tokens = preg_split('/\s+/', $cleaned) ?: [];

        return array_values(array_unique(array_filter($tokens, static function (string $t) use ($stopwords): bool {
            return mb_strlen($t) >= 3 && ! in_array($t, $stopwords, true);
        })));
    }

    /**
     * Konu kelimelerinden tema tespit et (en çok eşleşen).
     *
     * @param list<string> $keywords
     */
    public function detectTheme(array $keywords): ?string
    {
        if ($keywords === []) {
            return null;
        }

        $scores = [];
        $haystack = mb_strtolower(implode(' ', $keywords));

        foreach (self::THEME_KEYWORDS as $theme => $themeWords) {
            $score = 0;
            foreach ($themeWords as $tw) {
                $tw = mb_strtolower($tw);
                if (str_contains($haystack, $tw)) {
                    $score++;
                }
            }
            if ($score > 0) {
                $scores[$theme] = $score;
            }
        }

        if ($scores === []) {
            return null;
        }

        arsort($scores);
        return (string) array_key_first($scores);
    }

    public function currentSeason(?Carbon $when = null): string
    {
        $month = ($when ?? now())->month;
        return match (true) {
            $month >= 3 && $month <= 5  => MediaLibraryImage::SEASON_ILKBAHAR,
            $month >= 6 && $month <= 8  => MediaLibraryImage::SEASON_YAZ,
            $month >= 9 && $month <= 11 => MediaLibraryImage::SEASON_SONBAHAR,
            default                     => MediaLibraryImage::SEASON_KIS,
        };
    }

    /** @param array<string, mixed> $extra */
    private function logMatch(string $strategy, MediaLibraryImage $img, string $konu, array $extra = []): void
    {
        Log::debug('MediaLibraryMatcher: eşleşme', array_merge([
            'strategy'   => $strategy,
            'image_id'   => $img->id,
            'media_type' => $img->media_type,
            'theme'      => $img->theme,
            'konu'       => mb_strimwidth($konu, 0, 60, '…', 'UTF-8'),
        ], $extra));
    }
}
