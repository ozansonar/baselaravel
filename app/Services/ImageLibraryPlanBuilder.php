<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MediaLibraryImage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Görsel-İlk plan oluşturucu — kullanıcı tarih aralığı + tip dağılımı seçer,
 * sistem kütüphaneden least-used görselleri seçip plan hazırlar.
 *
 * Akış:
 *   1. Tarih aralığı + tip oranları (60% feed + 25% story + 15% reels gibi)
 *   2. Filtreler: tema, mood, mevsim
 *   3. Her tip için ihtiyaç sayısı kadar least-used görsel çek
 *   4. Tarihler arasına dengeli dağıt (her güne 1 post)
 *   5. Preview döndür — kullanıcı onaylar, bulk import job dispatch
 *
 * AI maliyeti: $0 (sadece caption text üretiminde küçük maliyet ~$0.0001/post)
 */
final class ImageLibraryPlanBuilder
{
    /**
     * @param array{start_date: string, end_date: string, post_per_day: int, distribution: array<string, float>, theme?: string|null, mood?: string|null, season?: string|null} $params
     *
     * @return array{
     *     success: bool,
     *     plan: list<array{date: string, time: string, media_type: string, image_id: int, image_path: string, image_url: string, suggested_konu: string}>,
     *     stats: array<string, mixed>,
     *     message: string
     * }
     */
    public function build(array $params): array
    {
        try {
            $start = Carbon::createFromFormat('Y-m-d', $params['start_date'])->startOfDay();
            $end   = Carbon::createFromFormat('Y-m-d', $params['end_date'])->startOfDay();
        } catch (\Throwable) {
            return ['success' => false, 'plan' => [], 'stats' => [], 'message' => 'Tarih formatı geçersiz (YYYY-MM-DD).'];
        }

        if ($start->greaterThan($end)) {
            return ['success' => false, 'plan' => [], 'stats' => [], 'message' => 'Başlangıç tarihi bitiş tarihinden sonra olamaz.'];
        }

        $totalDays = (int) $start->diffInDays($end) + 1;
        $postPerDay = max(1, min((int) ($params['post_per_day'] ?? 1), 3));
        $totalPosts = $totalDays * $postPerDay;

        if ($totalPosts > 500) {
            return ['success' => false, 'plan' => [], 'stats' => [], 'message' => 'Plan çok büyük (max 500 post). Daha kısa aralık seç.'];
        }

        // Tip dağılımı (feed/story/reels) — yüzde input
        $dist = $params['distribution'] ?? ['feed' => 0.6, 'story' => 0.25, 'reels' => 0.15];

        $needs = [
            'feed'  => (int) round($totalPosts * ($dist['feed']  ?? 0)),
            'story' => (int) round($totalPosts * ($dist['story'] ?? 0)),
            'reels' => (int) round($totalPosts * ($dist['reels'] ?? 0)),
        ];

        // Yuvarlama farkı: toplam ≠ totalPosts olabilir, fark feed'e ekle/çıkar
        $diff = $totalPosts - array_sum($needs);
        $needs['feed'] += $diff;

        // Her tip için görselleri çek (least-used)
        $imagesByType = [];
        $insufficient = [];

        foreach ($needs as $type => $count) {
            if ($count <= 0) continue;

            $query = MediaLibraryImage::query()->ofMediaType($type);
            if (! empty($params['theme']))  $query->where('theme', $params['theme']);
            if (! empty($params['mood']))   $query->where('mood', $params['mood']);
            if (! empty($params['season'])) $query->where('season', $params['season']);

            $available = $query->count();

            if ($available < $count) {
                $insufficient[$type] = ['needed' => $count, 'available' => $available];
            }

            // Tüm uygun görselleri çek (least-used sırasında)
            $images = $query
                ->leastUsed()
                ->limit($count)
                ->get();

            // Yetersizse recycle (mevcut görselleri tekrar kullan)
            if ($images->count() < $count && $images->isNotEmpty()) {
                $needed = $count - $images->count();
                $extra = collect();
                while ($extra->count() < $needed) {
                    foreach ($images as $i) {
                        if ($extra->count() >= $needed) break;
                        $extra->push($i);
                    }
                }
                $images = $images->concat($extra);
            }

            $imagesByType[$type] = $images;
        }

        // Plan oluştur — her güne sırayla feed/story/reels dağıt
        $plan = $this->distributePosts($start, $end, $postPerDay, $imagesByType);

        return [
            'success' => true,
            'plan'    => $plan,
            'stats'   => [
                'total_days'  => $totalDays,
                'total_posts' => $totalPosts,
                'needs'       => $needs,
                'insufficient'=> $insufficient,
                'distribution_used' => $dist,
            ],
            'message' => "{$totalDays} günlük plan oluşturuldu ({$totalPosts} post).",
        ];
    }

    /**
     * Posts'ları günlere dengeli şekilde dağıtır.
     *
     * @param array<string, Collection<int, MediaLibraryImage>> $imagesByType
     *
     * @return list<array<string, mixed>>
     */
    private function distributePosts(Carbon $start, Carbon $end, int $postPerDay, array $imagesByType): array
    {
        $totalDays = (int) $start->diffInDays($end) + 1;
        $plan = [];

        // Tek liste — sırayla feed-feed-feed-story-story-reels gibi gruplu
        $allImages = collect();
        foreach (['feed', 'reels', 'story'] as $type) {
            $allImages = $allImages->concat($imagesByType[$type] ?? collect());
        }

        // Karıştır ki ardışık aynı tip post olmasın (dengeli)
        $allImages = $this->interleave($imagesByType);

        $idx = 0;
        for ($i = 0; $i < $totalDays; $i++) {
            $date = $start->copy()->addDays($i);

            for ($p = 0; $p < $postPerDay; $p++) {
                if (! isset($allImages[$idx])) break 2;

                /** @var MediaLibraryImage $img */
                $img = $allImages[$idx];

                // Saat: post sayısına göre dağıt (1 post = 12:00, 2 = 09:00+19:00, 3 = 09:00+13:00+19:00)
                $time = match ($postPerDay) {
                    1       => '12:00',
                    2       => $p === 0 ? '09:00' : '19:00',
                    default => match ($p) { 0 => '09:00', 1 => '13:00', default => '19:00' },
                };

                $plan[] = [
                    'date'           => $date->toDateString(),
                    'time'           => $time,
                    'media_type'     => $img->media_type === 'feed' ? 'image' : $img->media_type,
                    'image_id'       => $img->id,
                    'image_path'     => $img->image_path,
                    'image_url'      => upload_url($img->image_path, 'thumb'),
                    'suggested_konu' => $img->description ?? $img->alt_text ?? 'Çiftlikten paylaşım',
                    'tags'           => $img->tagListString(),
                ];

                $idx++;
            }
        }

        return $plan;
    }

    /**
     * Tip bazında interleave — feed/story/reels art arda gelmesin diye karıştır.
     * Algoritma: her turda her tipten 1 görsel çekip ortak listeye ekle.
     *
     * @param array<string, Collection<int, MediaLibraryImage>> $imagesByType
     *
     * @return list<MediaLibraryImage>
     */
    private function interleave(array $imagesByType): array
    {
        $cursors = [];
        foreach ($imagesByType as $type => $coll) {
            $cursors[$type] = ['list' => $coll->all(), 'idx' => 0];
        }

        $result = [];
        $remaining = true;
        while ($remaining) {
            $remaining = false;
            foreach ($cursors as $type => &$c) {
                if ($c['idx'] < count($c['list'])) {
                    $result[] = $c['list'][$c['idx']];
                    $c['idx']++;
                    $remaining = true;
                }
            }
            unset($c);
        }

        return $result;
    }
}
