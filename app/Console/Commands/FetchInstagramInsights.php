<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\InstagramInsightsService;
use Illuminate\Console\Command;

final class FetchInstagramInsights extends Command
{
    protected $signature = 'instagram:fetch-insights
                            {--days=7 : Son N gün içinde yayınlanan postları işle}
                            {--force : Instagram entegrasyonu kapalı olsa bile çalıştır}';

    protected $description = 'Yayınlanmış Instagram postları için Meta Insights API\'den günlük snapshot çek (instagram_post_insights tablosuna)';

    public function handle(InstagramInsightsService $service): int
    {
        $enabled = Setting::getValue('instagram_enabled', '0') === '1';

        if (! $enabled && ! $this->option('force')) {
            $this->warn('Instagram entegrasyonu kapalı. Insights fetch atlandı.');

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));

        $this->info("Son {$days} gün post'ları için insights çekiliyor...");

        $report = $service->fetchRecentPostsInsights($days);

        $this->info("Toplam: {$report['total']}, başarılı: {$report['success']}, başarısız: {$report['failed']}");

        if (! empty($report['errors'])) {
            $this->warn('Hatalar:');
            foreach ($report['errors'] as $err) {
                $this->line('  - ' . $err);
            }
        }

        return self::SUCCESS;
    }
}
