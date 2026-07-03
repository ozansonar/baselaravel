<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InstagramPostStatus;
use App\Models\InstagramPost;
use App\Models\Setting;
use App\Services\InstagramService;
use Illuminate\Console\Command;

final class SyncInstagramEngagementCommand extends Command
{
    protected $signature = 'instagram:sync-engagement
                            {--days=30 : Son N gün içinde yayınlanan postları sync et}
                            {--limit=100 : Maksimum işlenecek post sayısı (rate limit)}
                            {--force : Instagram entegrasyonu kapalı olsa bile çalıştır}';

    protected $description = 'Yayınlanmış Instagram postları için engagement metrics çek (likes, comments, reach, impressions)';

    public function handle(InstagramService $service): int
    {
        $enabled = Setting::getValue('instagram_enabled', '0') === '1';

        if (! $enabled && ! $this->option('force')) {
            $this->warn('Instagram entegrasyonu kapalı. Sync atlandı.');

            return self::SUCCESS;
        }

        $days  = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));

        // En az 24 saat önceki sync edilmiş postlar — günde 1 sync yeterli
        $threshold = now()->subDay();

        $posts = InstagramPost::query()
            ->where('status', InstagramPostStatus::Published->value)
            ->whereNotNull('ig_post_id')
            ->where('published_at', '>=', now()->subDays($days))
            ->where(function ($q) use ($threshold) {
                $q->whereNull('metrics_fetched_at')
                  ->orWhere('metrics_fetched_at', '<', $threshold);
            })
            ->orderBy('metrics_fetched_at') // Hiç sync edilmemiş olanlar önce (NULL first)
            ->limit($limit)
            ->get();

        if ($posts->isEmpty()) {
            $this->info('Sync edilecek post yok (hepsi son 24 saat içinde sync edildi veya yayınlanmış post yok).');

            return self::SUCCESS;
        }

        $this->info("{$posts->count()} post sync edilecek.");

        $success = 0;
        $partial = 0;
        $failed  = 0;

        foreach ($posts as $post) {
            $result = $service->fetchPostMetrics($post);

            if ($result['success']) {
                $success++;
                $likes = $post->fresh()->like_count ?? 0;
                $comments = $post->fresh()->comments_count ?? 0;
                $this->line("  ✓ #{$post->id} — {$likes} 👍 / {$comments} 💬");
            } else {
                $failed++;
                $this->warn("  ! #{$post->id} — {$result['message']}");
            }
        }

        $this->info("Bitti. Başarılı: {$success}, başarısız: {$failed}");

        return self::SUCCESS;
    }
}
