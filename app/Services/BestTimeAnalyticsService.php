<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InstagramPostStatus;
use App\Models\InstagramPost;
use Illuminate\Support\Facades\Cache;

/**
 * En iyi paylaşım saati analizi.
 *
 * Yayınlanmış post'ların published_at saatine ve gününe göre engagement
 * ortalamasını hesaplayıp, "Pazartesi 19:00" gibi en iyi slotları çıkarır.
 *
 * Engagement skoru: like_count + (comments_count * 2)
 * (yorum daha "ağır" — yorum yapmak beğenmekten zor)
 */
final class BestTimeAnalyticsService
{
    private const CACHE_TTL = 1800; // 30 dakika
    public const  COMMENT_WEIGHT = 2;

    private const DAY_LABELS = [
        1 => 'Pazartesi',
        2 => 'Salı',
        3 => 'Çarşamba',
        4 => 'Perşembe',
        5 => 'Cuma',
        6 => 'Cumartesi',
        7 => 'Pazar',
    ];

    /**
     * Saat bazlı agregasyon (0-23 → ortalama engagement).
     *
     * @return array<int, array{hour: int, post_count: int, avg_engagement: float, avg_reach: float}>
     */
    public function byHour(int $daysBack = 90): array
    {
        return Cache::remember("best_time:hour:{$daysBack}", self::CACHE_TTL, function () use ($daysBack): array {
            $posts = $this->fetchPosts($daysBack);

            /** @var array<int, array{count:int, sum_eng:int, sum_reach:int}> $bucket */
            $bucket = array_fill(0, 24, ['count' => 0, 'sum_eng' => 0, 'sum_reach' => 0]);

            foreach ($posts as $p) {
                $h = (int) $p->published_at->format('G');
                $eng = (int) ($p->like_count ?? 0) + (self::COMMENT_WEIGHT * (int) ($p->comments_count ?? 0));
                $bucket[$h]['count']++;
                $bucket[$h]['sum_eng']   += $eng;
                $bucket[$h]['sum_reach'] += (int) ($p->reach ?? 0);
            }

            $rows = [];
            foreach ($bucket as $h => $b) {
                $rows[$h] = [
                    'hour'           => $h,
                    'post_count'     => $b['count'],
                    'avg_engagement' => $b['count'] > 0 ? round($b['sum_eng'] / $b['count'], 1) : 0.0,
                    'avg_reach'      => $b['count'] > 0 ? round($b['sum_reach'] / $b['count'], 1) : 0.0,
                ];
            }
            return $rows;
        });
    }

    /**
     * Gün bazlı agregasyon (Pazartesi-Pazar → ortalama engagement).
     *
     * @return array<int, array{day_num: int, day_label: string, post_count: int, avg_engagement: float, avg_reach: float}>
     */
    public function byDayOfWeek(int $daysBack = 90): array
    {
        return Cache::remember("best_time:dow:{$daysBack}", self::CACHE_TTL, function () use ($daysBack): array {
            $posts = $this->fetchPosts($daysBack);

            /** @var array<int, array{count:int, sum_eng:int, sum_reach:int}> $bucket */
            $bucket = [];
            for ($i = 1; $i <= 7; $i++) {
                $bucket[$i] = ['count' => 0, 'sum_eng' => 0, 'sum_reach' => 0];
            }

            foreach ($posts as $p) {
                $d = (int) $p->published_at->dayOfWeekIso; // 1=Mon..7=Sun
                $eng = (int) ($p->like_count ?? 0) + (self::COMMENT_WEIGHT * (int) ($p->comments_count ?? 0));
                $bucket[$d]['count']++;
                $bucket[$d]['sum_eng']   += $eng;
                $bucket[$d]['sum_reach'] += (int) ($p->reach ?? 0);
            }

            $rows = [];
            foreach ($bucket as $d => $b) {
                $rows[$d] = [
                    'day_num'        => $d,
                    'day_label'      => self::DAY_LABELS[$d],
                    'post_count'     => $b['count'],
                    'avg_engagement' => $b['count'] > 0 ? round($b['sum_eng'] / $b['count'], 1) : 0.0,
                    'avg_reach'      => $b['count'] > 0 ? round($b['sum_reach'] / $b['count'], 1) : 0.0,
                ];
            }
            return $rows;
        });
    }

    /**
     * Gün × saat heatmap (7 gün × 24 saat = 168 hücre).
     *
     * @return array<int, array<int, array{post_count: int, avg_engagement: float}>>
     */
    public function heatmap(int $daysBack = 90): array
    {
        return Cache::remember("best_time:heatmap:{$daysBack}", self::CACHE_TTL, function () use ($daysBack): array {
            $posts = $this->fetchPosts($daysBack);

            /** @var array<int, array<int, array{count:int, sum_eng:int}>> $grid */
            $grid = [];
            for ($d = 1; $d <= 7; $d++) {
                for ($h = 0; $h <= 23; $h++) {
                    $grid[$d][$h] = ['count' => 0, 'sum_eng' => 0];
                }
            }

            foreach ($posts as $p) {
                $d = (int) $p->published_at->dayOfWeekIso;
                $h = (int) $p->published_at->format('G');
                $eng = (int) ($p->like_count ?? 0) + (self::COMMENT_WEIGHT * (int) ($p->comments_count ?? 0));
                $grid[$d][$h]['count']++;
                $grid[$d][$h]['sum_eng'] += $eng;
            }

            $out = [];
            foreach ($grid as $d => $hours) {
                foreach ($hours as $h => $b) {
                    $out[$d][$h] = [
                        'post_count'     => $b['count'],
                        'avg_engagement' => $b['count'] > 0 ? round($b['sum_eng'] / $b['count'], 1) : 0.0,
                    ];
                }
            }
            return $out;
        });
    }

    /**
     * En iyi 3 slot (gün+saat) — min 2 post yayınlanmış olanlar arasından.
     *
     * @return list<array{day_num: int, day_label: string, hour: int, post_count: int, avg_engagement: float}>
     */
    public function topSlots(int $limit = 3, int $daysBack = 90): array
    {
        $heatmap = $this->heatmap($daysBack);
        $candidates = [];

        foreach ($heatmap as $d => $hours) {
            foreach ($hours as $h => $cell) {
                if ($cell['post_count'] < 2) continue; // tek post → istatistik anlamsız
                $candidates[] = [
                    'day_num'        => $d,
                    'day_label'      => self::DAY_LABELS[$d],
                    'hour'           => $h,
                    'post_count'     => $cell['post_count'],
                    'avg_engagement' => $cell['avg_engagement'],
                ];
            }
        }

        usort($candidates, fn ($a, $b) => $b['avg_engagement'] <=> $a['avg_engagement']);
        return array_slice($candidates, 0, $limit);
    }

    /**
     * Toplam analiz edilen post sayısı.
     */
    public function totalAnalyzedPosts(int $daysBack = 90): int
    {
        return Cache::remember("best_time:total:{$daysBack}", self::CACHE_TTL, function () use ($daysBack): int {
            return $this->fetchPosts($daysBack)->count();
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, InstagramPost>
     */
    private function fetchPosts(int $daysBack): \Illuminate\Database\Eloquent\Collection
    {
        return InstagramPost::query()
            ->where('status', InstagramPostStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '>=', now()->subDays($daysBack))
            ->where(function ($q) {
                $q->where('like_count', '>', 0)
                  ->orWhere('comments_count', '>', 0);
            })
            ->get(['id', 'published_at', 'like_count', 'comments_count', 'reach']);
    }
}
