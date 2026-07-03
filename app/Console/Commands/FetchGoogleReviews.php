<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GoogleReviewService;
use Illuminate\Console\Command;

final class FetchGoogleReviews extends Command
{
    protected $signature = 'google:fetch-reviews';

    protected $description = 'Google Places API üzerinden işletme yorumlarını çeker ve veritabanına kaydeder';

    public function handle(GoogleReviewService $service): int
    {
        $this->info('Google yorumları çekiliyor...');

        try {
            $count = $service->fetchAndSync();

            if ($count > 0) {
                $this->info("{$count} yorum başarıyla senkronize edildi.");
            } else {
                $this->warn('Hiç yorum çekilemedi. API key ve Place ID yapılandırmasını kontrol edin.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
