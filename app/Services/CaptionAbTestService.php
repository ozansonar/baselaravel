<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InstagramPostStatus;
use App\Models\InstagramPost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Caption A/B test analizi.
 *
 * IG'de gerçek A/B (aynı post farklı caption) mümkün değil. Bunun yerine
 * yayınlanmış post'ları caption özelliklerine göre segmentliyoruz:
 *  - Uzunluk (kısa/orta/uzun)
 *  - Emoji sayısı (yok/az/çok)
 *  - Hashtag sayısı (az/orta/çok)
 *  - Soru içeriyor mu? (? varsa)
 *  - CTA içeriyor mu? (yorum/kaydet/etiketle gibi ifadeler)
 *
 * Her segment için ortalama engagement (likes + 2×comments) ve reach gösterilir.
 * Winner = en yüksek avg engagement segment.
 */
final class CaptionAbTestService
{
    private const CACHE_TTL = 1800;
    private const COMMENT_WEIGHT = 2;

    private const CTA_KEYWORDS = [
        'yorum yap', 'yorum bırak', 'düşünceni', 'fikrini',
        'kaydet', 'beğen', 'paylaş', 'etiketle',
        'tıkla', 'tıklayın', 'link bio', 'biyografi',
        'iletişim', 'whatsapp', 'mesaj at', 'sipariş ver',
    ];

    /**
     * Tüm segment analizini tek seferde döndür.
     *
     * @return array{
     *   length: list<array<string, mixed>>,
     *   emoji: list<array<string, mixed>>,
     *   hashtag: list<array<string, mixed>>,
     *   question: list<array<string, mixed>>,
     *   cta: list<array<string, mixed>>,
     *   total_posts: int
     * }
     */
    public function analyze(int $daysBack = 90): array
    {
        return Cache::remember("caption_ab:{$daysBack}", self::CACHE_TTL, function () use ($daysBack): array {
            $posts = $this->fetchPosts($daysBack);

            return [
                'length'      => $this->segmentByLength($posts),
                'emoji'       => $this->segmentByEmoji($posts),
                'hashtag'     => $this->segmentByHashtagCount($posts),
                'question'    => $this->segmentByQuestion($posts),
                'cta'         => $this->segmentByCta($posts),
                'total_posts' => $posts->count(),
            ];
        });
    }

    /**
     * @param Collection<int, InstagramPost> $posts
     * @return list<array<string, mixed>>
     */
    private function segmentByLength(Collection $posts): array
    {
        $buckets = [
            'short'  => ['label' => 'Kısa (< 80 karakter)',         'min' => 0,   'max' => 80],
            'medium' => ['label' => 'Orta (80-300 karakter)',        'min' => 80,  'max' => 300],
            'long'   => ['label' => 'Uzun (300+ karakter)',          'min' => 300, 'max' => PHP_INT_MAX],
        ];

        return $this->bucketize($posts, $buckets, function (InstagramPost $p): int {
            return mb_strlen((string) $p->caption);
        });
    }

    /**
     * @param Collection<int, InstagramPost> $posts
     * @return list<array<string, mixed>>
     */
    private function segmentByEmoji(Collection $posts): array
    {
        $buckets = [
            'none' => ['label' => 'Emoji yok',         'min' => 0, 'max' => 0],
            'few'  => ['label' => 'Az (1-3 emoji)',    'min' => 1, 'max' => 3],
            'many' => ['label' => 'Çok (4+ emoji)',    'min' => 4, 'max' => PHP_INT_MAX],
        ];

        return $this->bucketize($posts, $buckets, function (InstagramPost $p): int {
            return $this->countEmojis((string) $p->caption);
        });
    }

    /**
     * @param Collection<int, InstagramPost> $posts
     * @return list<array<string, mixed>>
     */
    private function segmentByHashtagCount(Collection $posts): array
    {
        $buckets = [
            'low'    => ['label' => 'Az (0-5)',     'min' => 0,  'max' => 5],
            'mid'    => ['label' => 'Orta (6-15)',  'min' => 6,  'max' => 15],
            'high'   => ['label' => 'Çok (16-25)',  'min' => 16, 'max' => 25],
            'spam'   => ['label' => 'Aşırı (26+)',  'min' => 26, 'max' => PHP_INT_MAX],
        ];

        return $this->bucketize($posts, $buckets, function (InstagramPost $p): int {
            return is_array($p->hashtags) ? count($p->hashtags) : 0;
        });
    }

    /**
     * @param Collection<int, InstagramPost> $posts
     * @return list<array<string, mixed>>
     */
    private function segmentByQuestion(Collection $posts): array
    {
        $buckets = [
            'with'    => ['label' => 'Soru içeriyor (?)', 'flag' => true],
            'without' => ['label' => 'Soru içermiyor',     'flag' => false],
        ];

        return $this->flagSegment($posts, $buckets, function (InstagramPost $p): bool {
            return str_contains((string) $p->caption, '?');
        });
    }

    /**
     * @param Collection<int, InstagramPost> $posts
     * @return list<array<string, mixed>>
     */
    private function segmentByCta(Collection $posts): array
    {
        $buckets = [
            'with'    => ['label' => 'CTA içeriyor',    'flag' => true],
            'without' => ['label' => 'CTA içermiyor',   'flag' => false],
        ];

        return $this->flagSegment($posts, $buckets, function (InstagramPost $p): bool {
            $caption = mb_strtolower((string) $p->caption, 'UTF-8');
            foreach (self::CTA_KEYWORDS as $kw) {
                if (str_contains($caption, $kw)) return true;
            }
            return false;
        });
    }

    /**
     * Sayısal bucket'a göre segmentle.
     *
     * @param Collection<int, InstagramPost> $posts
     * @param array<string, array{label:string, min:int, max:int}> $buckets
     * @param callable(InstagramPost): int $valueFn
     * @return list<array<string, mixed>>
     */
    private function bucketize(Collection $posts, array $buckets, callable $valueFn): array
    {
        $result = [];
        foreach ($buckets as $key => $def) {
            $matching = $posts->filter(function ($p) use ($valueFn, $def) {
                $v = $valueFn($p);
                return $v >= $def['min'] && $v <= $def['max'];
            });
            $result[] = $this->buildSegmentStats($key, $def['label'], $matching);
        }
        return $this->markWinner($result);
    }

    /**
     * Boolean flag'e göre segmentle.
     *
     * @param Collection<int, InstagramPost> $posts
     * @param array<string, array{label:string, flag:bool}> $buckets
     * @param callable(InstagramPost): bool $flagFn
     * @return list<array<string, mixed>>
     */
    private function flagSegment(Collection $posts, array $buckets, callable $flagFn): array
    {
        $result = [];
        foreach ($buckets as $key => $def) {
            $matching = $posts->filter(fn ($p) => $flagFn($p) === $def['flag']);
            $result[] = $this->buildSegmentStats($key, $def['label'], $matching);
        }
        return $this->markWinner($result);
    }

    /**
     * @param Collection<int, InstagramPost> $matching
     * @return array<string, mixed>
     */
    private function buildSegmentStats(string $key, string $label, Collection $matching): array
    {
        $count = $matching->count();
        $sumEng = $matching->sum(fn ($p) => (int) ($p->like_count ?? 0) + self::COMMENT_WEIGHT * (int) ($p->comments_count ?? 0));
        $sumReach = $matching->sum(fn ($p) => (int) ($p->reach ?? 0));

        return [
            'key'             => $key,
            'label'           => $label,
            'post_count'      => $count,
            'avg_engagement'  => $count > 0 ? round($sumEng / $count, 1) : 0.0,
            'avg_reach'       => $count > 0 ? round($sumReach / $count, 1) : 0.0,
            'is_winner'       => false,
        ];
    }

    /**
     * En yüksek avg_engagement'a 'is_winner' işareti koy (en az 2 post varsa).
     *
     * @param list<array<string, mixed>> $segments
     * @return list<array<string, mixed>>
     */
    private function markWinner(array $segments): array
    {
        $best = null;
        foreach ($segments as $i => $s) {
            if ($s['post_count'] < 2) continue;
            if ($best === null || $s['avg_engagement'] > $segments[$best]['avg_engagement']) {
                $best = $i;
            }
        }
        if ($best !== null && $segments[$best]['avg_engagement'] > 0) {
            $segments[$best]['is_winner'] = true;
        }
        return $segments;
    }

    /**
     * Caption'da emoji sayısını yaklaşık say.
     * Unicode emoji ranges (BMP dışı + dingbats + symbols).
     */
    private function countEmojis(string $caption): int
    {
        // Common emoji ranges
        return (int) preg_match_all('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}\x{1FA00}-\x{1FAFF}]/u', $caption);
    }

    /**
     * @return Collection<int, InstagramPost>
     */
    private function fetchPosts(int $daysBack): Collection
    {
        return InstagramPost::query()
            ->where('status', InstagramPostStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '>=', now()->subDays($daysBack))
            ->where(function ($q) {
                $q->where('like_count', '>', 0)
                  ->orWhere('comments_count', '>', 0);
            })
            ->get(['id', 'caption', 'hashtags', 'like_count', 'comments_count', 'reach']);
    }
}
