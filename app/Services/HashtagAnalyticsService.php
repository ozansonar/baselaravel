<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InstagramPostStatus;
use App\Models\InstagramPost;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Hashtag performans analizi.
 *
 * Yayınlanmış post'lardaki hashtag'leri tarayıp her hashtag için
 * ortalama like/comment/reach/ER hesaplar. Hangi hashtag'lerin
 * gerçekten engagement getirdiğini görmek için.
 */
final class HashtagAnalyticsService
{
    private const CACHE_TTL = 1800; // 30 dakika

    /**
     * Son N gün içinde yayınlanan post'lardan hashtag bazında performans
     * agregasyonu döndür.
     *
     * @return Collection<int, array{
     *   tag: string,
     *   usage_count: int,
     *   total_likes: int,
     *   total_comments: int,
     *   total_reach: int,
     *   avg_likes: float,
     *   avg_comments: float,
     *   avg_reach: float,
     *   avg_engagement_rate: float,
     *   posts: list<int>
     * }>
     */
    public function aggregate(int $daysBack = 90, int $minUsage = 1): Collection
    {
        $cacheKey = "hashtag_analytics:{$daysBack}:{$minUsage}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($daysBack, $minUsage): Collection {
            $posts = InstagramPost::query()
                ->where('status', InstagramPostStatus::Published->value)
                ->whereNotNull('published_at')
                ->where('published_at', '>=', now()->subDays($daysBack))
                ->whereNotNull('hashtags')
                ->get(['id', 'hashtags', 'like_count', 'comments_count', 'reach', 'published_at']);

            // Her hashtag için aggregator
            /** @var array<string, array{usage_count:int, total_likes:int, total_comments:int, total_reach:int, posts:list<int>}> $bucket */
            $bucket = [];

            foreach ($posts as $post) {
                $tags = is_array($post->hashtags) ? $post->hashtags : [];
                $likes = (int) ($post->like_count ?? 0);
                $comments = (int) ($post->comments_count ?? 0);
                $reach = (int) ($post->reach ?? 0);

                foreach ($tags as $rawTag) {
                    $tag = $this->normalizeTag((string) $rawTag);
                    if ($tag === '') continue;

                    if (! isset($bucket[$tag])) {
                        $bucket[$tag] = [
                            'usage_count'    => 0,
                            'total_likes'    => 0,
                            'total_comments' => 0,
                            'total_reach'    => 0,
                            'posts'          => [],
                        ];
                    }

                    $bucket[$tag]['usage_count']++;
                    $bucket[$tag]['total_likes']    += $likes;
                    $bucket[$tag]['total_comments'] += $comments;
                    $bucket[$tag]['total_reach']    += $reach;
                    $bucket[$tag]['posts'][] = $post->id;
                }
            }

            $result = collect();
            foreach ($bucket as $tag => $stats) {
                if ($stats['usage_count'] < $minUsage) continue;

                $count = $stats['usage_count'];
                $avgLikes    = $count > 0 ? $stats['total_likes'] / $count : 0;
                $avgComments = $count > 0 ? $stats['total_comments'] / $count : 0;
                $avgReach    = $count > 0 ? $stats['total_reach'] / $count : 0;
                $er = $avgReach > 0 ? (($avgLikes + $avgComments) / $avgReach) * 100 : 0.0;

                $result->push([
                    'tag'                 => $tag,
                    'usage_count'         => $count,
                    'total_likes'         => $stats['total_likes'],
                    'total_comments'      => $stats['total_comments'],
                    'total_reach'         => $stats['total_reach'],
                    'avg_likes'           => round($avgLikes, 1),
                    'avg_comments'        => round($avgComments, 1),
                    'avg_reach'           => round($avgReach, 1),
                    'avg_engagement_rate' => round($er, 2),
                    'posts'               => array_values(array_unique($stats['posts'])),
                ]);
            }

            return $result->values();
        });
    }

    /**
     * En yüksek engagement rate'e sahip hashtag'ler (en az 2 kez kullanılmış).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topByEngagement(int $limit = 20, int $daysBack = 90): Collection
    {
        return $this->aggregate($daysBack, minUsage: 2)
            ->sortByDesc('avg_engagement_rate')
            ->take($limit)
            ->values();
    }

    /**
     * En çok kullanılan hashtag'ler (volume bazında).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topByUsage(int $limit = 20, int $daysBack = 90): Collection
    {
        return $this->aggregate($daysBack, minUsage: 1)
            ->sortByDesc('usage_count')
            ->take($limit)
            ->values();
    }

    /**
     * Düşük performanslı hashtag'ler (kullanılıyor ama ER düşük).
     * Min 3 kez kullanılmış olanlar arasından en düşük ER'liler.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function lowPerformers(int $limit = 10, int $daysBack = 90): Collection
    {
        return $this->aggregate($daysBack, minUsage: 3)
            ->filter(fn ($row) => $row['avg_reach'] > 0) // reach=0 olanları filtre dışı
            ->sortBy('avg_engagement_rate')
            ->take($limit)
            ->values();
    }

    /**
     * Genel özet istatistik.
     *
     * @return array{total_unique_tags: int, total_usage: int, avg_tags_per_post: float}
     */
    public function summary(int $daysBack = 90): array
    {
        $all = $this->aggregate($daysBack, minUsage: 1);
        $totalUsage = (int) $all->sum('usage_count');

        $postCount = InstagramPost::query()
            ->where('status', InstagramPostStatus::Published->value)
            ->where('published_at', '>=', now()->subDays($daysBack))
            ->whereNotNull('hashtags')
            ->count();

        return [
            'total_unique_tags'  => $all->count(),
            'total_usage'        => $totalUsage,
            'avg_tags_per_post'  => $postCount > 0 ? round($totalUsage / $postCount, 1) : 0.0,
        ];
    }

    /**
     * Hashtag'i normalize et: küçük harf, # prefix kaldır, trim.
     */
    private function normalizeTag(string $tag): string
    {
        $tag = trim($tag);
        $tag = ltrim($tag, '#');
        $tag = mb_strtolower($tag, 'UTF-8');
        return $tag;
    }
}
