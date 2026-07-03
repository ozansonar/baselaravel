<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InstagramPost;
use App\Models\InstagramPostInsight;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Instagram Graph API insights çekici.
 *
 * Yayınlanmış post'lar için engagement metric'lerini çekip
 * instagram_post_insights tablosuna kaydeder.
 *
 * Cron tarafından günde 1 kez çağrılır (yayın sonrası 0-7 gün arası post'lar).
 */
final class InstagramInsightsService
{
    private const GRAPH_BASE = 'https://graph.facebook.com/v21.0/';
    private const HTTP_TIMEOUT = 10;
    private const CACHE_PREFIX = 'ig_insights:';

    /**
     * Bir post için Meta Insights API'den metric çek.
     *
     * @return array{success: bool, insights?: InstagramPostInsight, message: string}
     */
    public function fetchPostInsights(InstagramPost $post): array
    {
        if (empty($post->ig_post_id)) {
            return ['success' => false, 'message' => 'IG post ID yok (post yayınlanmamış olabilir).'];
        }

        $token = trim((string) Setting::getValue('instagram_access_token', ''));
        if ($token === '') {
            return ['success' => false, 'message' => 'Instagram access token tanımlı değil.'];
        }

        // Metric listesi — post tipine göre değişir
        $metrics = $this->metricsForPost($post);

        // Cache: aynı post için son 1 saat içinde fetch yapıldıysa skip
        $cacheKey = self::CACHE_PREFIX . $post->id;
        if (Cache::has($cacheKey)) {
            return ['success' => false, 'message' => 'Son fetch 1 saatten yeni — atla.'];
        }

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->get(self::GRAPH_BASE . $post->ig_post_id . '/insights', [
                    'metric'       => implode(',', $metrics),
                    'access_token' => $token,
                ]);

            if (! $response->successful()) {
                $err = (string) ($response->json('error.message') ?? $response->body());
                Log::warning('IG Insights fetch fail', [
                    'post_id' => $post->id,
                    'status'  => $response->status(),
                    'error'   => $err,
                ]);
                return ['success' => false, 'message' => 'API hatası: ' . mb_strimwidth($err, 0, 200, '…')];
            }

            $data = $response->json();
            $values = $this->parseMetrics($data['data'] ?? []);

            $insight = InstagramPostInsight::updateOrCreate(
                [
                    'instagram_post_id' => $post->id,
                    'fetched_at'        => now()->startOfHour(), // saatlik tekleştirme
                ],
                [
                    'likes'        => $values['likes']        ?? 0,
                    'comments'     => $values['comments']     ?? 0,
                    'reach'        => $values['reach']        ?? 0,
                    'impressions'  => $values['impressions']  ?? 0,
                    'saves'        => $values['saves']        ?? 0,
                    'shares'       => $values['shares']       ?? 0,
                    'plays'        => $values['plays']        ?? 0,
                    'raw_response' => $data,
                ]
            );

            Cache::put($cacheKey, 1, now()->addHour());

            return ['success' => true, 'insights' => $insight, 'message' => 'Insights kaydedildi.'];
        } catch (\Throwable $e) {
            Log::error('IG Insights exception', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Beklenmedik hata: ' . $e->getMessage()];
        }
    }

    /**
     * Yayınlanmış son N günlük post'ların insights'ını topluca çek.
     *
     * @return array{total: int, success: int, failed: int, errors: list<string>}
     */
    public function fetchRecentPostsInsights(int $daysBack = 7): array
    {
        $posts = InstagramPost::query()
            ->whereNotNull('ig_post_id')
            ->whereNotNull('published_at')
            ->where('published_at', '>=', now()->subDays($daysBack))
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        $success = 0;
        $failed  = 0;
        $errors  = [];

        foreach ($posts as $post) {
            $result = $this->fetchPostInsights($post);
            if ($result['success']) {
                $success++;
            } else {
                $failed++;
                $errors[] = "#{$post->id}: " . ($result['message'] ?? '');
                if (count($errors) > 5) array_shift($errors);
            }

            // Rate limit korumak için 200ms bekle
            usleep(200_000);
        }

        return [
            'total'   => $posts->count(),
            'success' => $success,
            'failed'  => $failed,
            'errors'  => $errors,
        ];
    }

    /**
     * Post tipine göre Meta Insights metric isimleri.
     *
     * @return list<string>
     */
    private function metricsForPost(InstagramPost $post): array
    {
        $type = $post->media_type instanceof \App\Enums\InstagramMediaType
            ? $post->media_type->value
            : (string) $post->media_type;

        // Reels: plays, shares, likes, comments, reach, saved
        // Feed: likes, comments, reach, impressions, saved, shares
        // Story: reach, impressions, replies (24sa sonra silinir)
        return match ($type) {
            'reels'  => ['plays', 'likes', 'comments', 'shares', 'saved', 'reach'],
            'story'  => ['reach', 'impressions', 'replies'],
            'image', 'feed' => ['likes', 'comments', 'reach', 'impressions', 'saved', 'shares'],
            default  => ['likes', 'comments', 'reach'],
        };
    }

    /**
     * Meta API response'taki [{name, values:[{value:N}]}] formatını flat key-value'ya çevir.
     *
     * @param array<int, array<string, mixed>> $data
     * @return array<string, int>
     */
    private function parseMetrics(array $data): array
    {
        $result = [];
        foreach ($data as $metric) {
            if (! is_array($metric)) continue;
            $name = (string) ($metric['name'] ?? '');
            $value = $metric['values'][0]['value'] ?? 0;

            // Meta'nın 'saved' isimini biz 'saves' olarak tutuyoruz
            $key = $name === 'saved' ? 'saves' : $name;
            $result[$key] = (int) $value;
        }
        return $result;
    }
}
