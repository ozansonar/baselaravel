<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MediaLibraryImage;
use App\Services\Concerns\ParsesAiJsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Görsel kütüphanesi gap (eksik) analizi: hangi tip/tema/mevsim için ne kadar
 * görsel eksik, AI'dan hangi konularda fotoğraf çekilmesi önerildiğini getirir.
 *
 * 365 günlük yıllık plan hedefine göre:
 *   - Tip dağılımı: 60% feed + 25% story + 15% reels = 219 + 91 + 55
 *   - Tema dağılımı: 40% products + 30% farm + 15% recipes + 10% seasonal + 5% holidays
 *
 * Her tip × tema kombinasyonu için ayrı hedef hesaplar, mevcut kütüphane ile karşılaştırır,
 * eksik kalemler için AI'dan öneri ister (Gemini Flash, ~$0.001/çağrı).
 */
final class MediaLibraryGapAnalyzer
{
    use ParsesAiJsonResponse;

    /** Yıllık hedef post sayısı */
    private const ANNUAL_TARGET = 365;

    /** Tip dağılımı (yıllık 365 üzerinden) */
    private const TYPE_DISTRIBUTION = [
        'feed'  => 0.60,
        'story' => 0.25,
        'reels' => 0.15,
    ];

    /** Tema dağılımı */
    private const THEME_DISTRIBUTION = [
        MediaLibraryImage::THEME_PRODUCTS  => 0.40,
        MediaLibraryImage::THEME_FARM      => 0.25,
        MediaLibraryImage::THEME_RECIPES   => 0.15,
        MediaLibraryImage::THEME_SEASONAL  => 0.10,
        MediaLibraryImage::THEME_HOLIDAYS  => 0.05,
        MediaLibraryImage::THEME_NATURE    => 0.03,
        MediaLibraryImage::THEME_LIFESTYLE => 0.02,
    ];

    public function __construct(
        private readonly AiService $ai,
    ) {}

    /**
     * Tüm gap analizini topla.
     *
     * @return array{
     *     summary: array<string, mixed>,
     *     by_type: array<string, array<string, mixed>>,
     *     by_theme_type: array<string, array<string, array<string, int>>>,
     *     unused: array{count: int, by_age: array<string, int>},
     *     plan_simulation: array<string, mixed>
     * }
     */
    public function analyze(): array
    {
        return [
            'summary'         => $this->buildSummary(),
            'by_type'         => $this->analyzeByType(),
            'by_theme_type'   => $this->analyzeByThemeType(),
            'unused'          => $this->analyzeUnused(),
            'plan_simulation' => $this->simulatePlan(50), // 50 günlük plan
            'disk_usage'      => $this->analyzeDiskUsage(),
        ];
    }

    /**
     * Disk kullanım analizi: toplam boyut + en büyük 10 dosya + tip bazında.
     *
     * @return array{total_bytes: int, total_human: string, count_with_files: int, by_type_bytes: array<string, int>, top_largest: list<array<string, mixed>>}
     */
    private function analyzeDiskUsage(): array
    {
        $images = MediaLibraryImage::query()
            ->select('id', 'image_path', 'video_path', 'is_video', 'media_type', 'description')
            ->get();

        $totalBytes = 0;
        $byTypeBytes = ['feed' => 0, 'reels' => 0, 'story' => 0];
        $countWithFiles = 0;
        $sizes = [];

        foreach ($images as $img) {
            $size = 0;
            // Görsel
            $imgPath = public_path('uploads/' . $img->image_path);
            if (is_file($imgPath)) {
                $size += @filesize($imgPath) ?: 0;
            }
            // Video (varsa)
            if ($img->is_video && $img->video_path) {
                $vidPath = public_path('uploads/' . $img->video_path);
                if (is_file($vidPath)) {
                    $size += @filesize($vidPath) ?: 0;
                }
            }

            if ($size > 0) {
                $countWithFiles++;
                $totalBytes += $size;
                $byTypeBytes[$img->media_type] = ($byTypeBytes[$img->media_type] ?? 0) + $size;

                $sizes[] = [
                    'id'         => $img->id,
                    'path'       => $img->image_path,
                    'is_video'   => (bool) $img->is_video,
                    'media_type' => $img->media_type,
                    'size'       => $size,
                    'description'=> $img->description ? mb_strimwidth($img->description, 0, 60, '…', 'UTF-8') : null,
                ];
            }
        }

        // En büyük 10 dosya
        usort($sizes, static fn ($a, $b) => $b['size'] <=> $a['size']);
        $top10 = array_slice($sizes, 0, 10);

        // Human-readable boyutlar ekle
        foreach ($top10 as &$item) {
            $item['size_human'] = $this->humanBytes($item['size']);
        }
        unset($item);

        return [
            'total_bytes'      => $totalBytes,
            'total_human'      => $this->humanBytes($totalBytes),
            'count_with_files' => $countWithFiles,
            'by_type_bytes'    => array_map(fn ($b) => $this->humanBytes($b), $byTypeBytes),
            'by_type_raw'      => $byTypeBytes,
            'top_largest'      => $top10,
        ];
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1_048_576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1_073_741_824) return round($bytes / 1_048_576, 1) . ' MB';
        return round($bytes / 1_073_741_824, 2) . ' GB';
    }

    /**
     * AI'dan hangi konularda görsel çekilmeli sorularına yanıt al.
     *
     * @param list<array{type: string, theme: string, missing: int}> $gaps
     * @return array{success: bool, suggestions: array<int, array<string, mixed>>, message: string}
     */
    public function aiSuggestions(array $gaps): array
    {
        if ($gaps === []) {
            return ['success' => true, 'suggestions' => [], 'message' => 'Eksik kategori yok — kütüphane yeterli görünüyor.'];
        }

        // Sadece ilk 5 boşluğu AI'a sor (maliyet kontrolü)
        $topGaps = array_slice($gaps, 0, 5);

        $system = $this->buildAiSystemPrompt();
        $user   = $this->buildAiUserPrompt($topGaps);

        $res = $this->ai->generate($system, $user, 0.7);

        if (! ($res['success'] ?? false)) {
            return ['success' => false, 'suggestions' => [], 'message' => 'AI çağrısı başarısız: ' . ($res['message'] ?? 'bilinmeyen')];
        }

        $extracted = $this->extractJsonFromAiResponse((string) ($res['content'] ?? ''));
        if ($extracted === null) {
            return ['success' => false, 'suggestions' => [], 'message' => 'AI yanıtı geçerli JSON değil.'];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($extracted, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['success' => false, 'suggestions' => [], 'message' => 'JSON parse hatası.'];
        }

        if (! is_array($decoded)) {
            return ['success' => false, 'suggestions' => [], 'message' => 'AI yanıtı dizi değil.'];
        }

        $suggestions = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) continue;
            $suggestions[] = [
                'type'    => (string) ($item['type'] ?? ''),
                'theme'   => (string) ($item['theme'] ?? ''),
                'missing' => (int) ($item['missing'] ?? 0),
                'topics'  => array_values(array_filter(array_map(
                    static fn ($t) => is_string($t) ? trim($t) : null,
                    (array) ($item['topics'] ?? []),
                ))),
            ];
        }

        Log::info('MediaLibraryGapAnalyzer: AI öneri üretildi', ['count' => count($suggestions)]);

        return [
            'success'     => true,
            'suggestions' => $suggestions,
            'message'     => count($suggestions) . ' kategori için AI öneri hazırlandı.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(): array
    {
        $total = MediaLibraryImage::count();
        $usage = (int) MediaLibraryImage::sum('usage_count');
        $never = MediaLibraryImage::where('usage_count', 0)->count();

        return [
            'total'        => $total,
            'never_used'   => $never,
            'used_count'   => $total - $never,
            'total_usage'  => $usage,
            'avg_usage'    => $total > 0 ? round($usage / $total, 2) : 0,
            'annual_target' => self::ANNUAL_TARGET,
            'completion_pct' => $total > 0 ? min(100, (int) round(($total / self::ANNUAL_TARGET) * 100)) : 0,
        ];
    }

    /**
     * Tip bazında stok durumu.
     *
     * @return array<string, array<string, int>>
     */
    private function analyzeByType(): array
    {
        $counts = MediaLibraryImage::query()
            ->select('media_type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('media_type')
            ->pluck('cnt', 'media_type')
            ->all();

        $result = [];
        foreach (self::TYPE_DISTRIBUTION as $type => $ratio) {
            $target  = (int) round(self::ANNUAL_TARGET * $ratio);
            $current = (int) ($counts[$type] ?? 0);
            $missing = max(0, $target - $current);

            $result[$type] = [
                'target'  => $target,
                'current' => $current,
                'missing' => $missing,
                'pct'     => $target > 0 ? min(100, (int) round(($current / $target) * 100)) : 0,
            ];
        }

        return $result;
    }

    /**
     * Tip × tema matrisi.
     *
     * @return array<string, array<string, array<string, int>>>
     */
    private function analyzeByThemeType(): array
    {
        $rows = MediaLibraryImage::query()
            ->select('media_type', 'theme', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('theme')
            ->groupBy('media_type', 'theme')
            ->get()
            ->groupBy('media_type');

        $result = [];
        foreach (self::TYPE_DISTRIBUTION as $type => $typeRatio) {
            $typeTarget = (int) round(self::ANNUAL_TARGET * $typeRatio);
            $themesData = $rows->get($type, collect())->keyBy('theme');

            foreach (self::THEME_DISTRIBUTION as $theme => $themeRatio) {
                $target  = (int) round($typeTarget * $themeRatio);
                $current = (int) ($themesData->get($theme)->cnt ?? 0);
                $missing = max(0, $target - $current);

                $result[$type][$theme] = [
                    'target'  => $target,
                    'current' => $current,
                    'missing' => $missing,
                    'pct'     => $target > 0 ? min(100, (int) round(($current / $target) * 100)) : 0,
                ];
            }
        }

        return $result;
    }

    /**
     * Kullanılmayan görsel analizi.
     *
     * @return array{count: int, by_age: array<string, int>}
     */
    private function analyzeUnused(): array
    {
        $total = MediaLibraryImage::where('usage_count', 0)->count();

        $byAge = [
            'last_week'   => MediaLibraryImage::where('usage_count', 0)->where('created_at', '>=', now()->subWeek())->count(),
            'last_month'  => MediaLibraryImage::where('usage_count', 0)->where('created_at', '>=', now()->subMonth())->count(),
            'older'       => MediaLibraryImage::where('usage_count', 0)->where('created_at', '<', now()->subMonth())->count(),
        ];

        return [
            'count'  => $total,
            'by_age' => $byAge,
        ];
    }

    /**
     * X günlük plan simülasyonu — kütüphane bu sayıyı karşılayabilir mi?
     *
     * @return array<string, mixed>
     */
    private function simulatePlan(int $days = 50): array
    {
        $needs = [
            'feed'  => (int) round($days * 0.60),
            'story' => (int) round($days * 0.25),
            'reels' => (int) round($days * 0.15),
        ];

        $available = [
            'feed'  => MediaLibraryImage::ofMediaType('feed')->count(),
            'story' => MediaLibraryImage::ofMediaType('story')->count(),
            'reels' => MediaLibraryImage::ofMediaType('reels')->count(),
        ];

        $shortfall = [
            'feed'  => max(0, $needs['feed']  - $available['feed']),
            'story' => max(0, $needs['story'] - $available['story']),
            'reels' => max(0, $needs['reels'] - $available['reels']),
        ];

        $totalShortfall = array_sum($shortfall);

        return [
            'days'         => $days,
            'needs'        => $needs,
            'available'    => $available,
            'shortfall'    => $shortfall,
            'can_fulfill'  => $totalShortfall === 0,
            'total_short'  => $totalShortfall,
        ];
    }

    private function buildAiSystemPrompt(): string
    {
        return <<<'EOT'
Sen "Orhan Babanın Çiftliği" (Çorum, doğal/organik gıda) markası için Instagram
içerik stratejistisin. Kullanıcının görsel kütüphanesinde eksik olan kategoriler
için hangi konularda fotoğraf/video çekilmesi gerektiğini öneriyorsun.

ÇIKTI: SADECE JSON dizi, başka hiçbir şey yazma. Markdown YASAK.

Format:
[
  {
    "type": "feed|story|reels",
    "theme": "products|farm|recipes|seasonal|holidays|nature|lifestyle",
    "missing": 5,
    "topics": [
      "Konu 1 (somut, fotoğraf/video çekimi için yönlendirici)",
      "Konu 2",
      "..."
    ]
  }
]

KURALLAR:
- Her kategoride 3-6 somut konu öner
- Konular fotoğraf/video çekiminde yönlendirici olmalı (örn. "Yayık tereyağı yapımı (60sn timelapse)" değil "Yayık tereyağı")
- Reels için video formatı belirt (timelapse, slow-mo, perde arkası vb.)
- Story için anlık/günlük içerik (sabah günaydın, yeni ürün, hızlı duyuru)
- Feed için kalıcı/estetik (ürün fotoğrafı, sofra düzeni, manzara)
- Ürünler: süt, peynir, tereyağı, yoğurt, bal, yumurta, çökelek, lor
- Çiftlik: sağım, sabah, akşam, hayvanlar, mera, ahır
- Tarif: kahvaltı, çorba, börek, mevsimsel yemek
- Mevsim: bahar otları, yaz cacığı, sonbahar reçeli, kış tarhanası
EOT;
    }

    /**
     * @param list<array{type: string, theme: string, missing: int}> $gaps
     */
    private function buildAiUserPrompt(array $gaps): string
    {
        $lines = [];
        foreach ($gaps as $g) {
            $lines[] = sprintf("- %s (%s): %d görsel eksik", $g['type'], $g['theme'], $g['missing']);
        }

        return "Görsel kütüphanesinde şu kategoriler eksik:\n\n"
            . implode("\n", $lines) . "\n\n"
            . "Her kategori için 3-6 somut çekim konusu öner. SADECE JSON dizi döndür.";
    }
}
