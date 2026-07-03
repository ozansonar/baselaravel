<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\YouTubeService;
use Illuminate\Console\Command;

final class FetchYouTubeVideos extends Command
{
    protected $signature = 'youtube:fetch-videos';

    protected $description = 'YouTube Data API üzerinden kanal videolarını çeker ve veritabanına kaydeder';

    public function handle(YouTubeService $service): int
    {
        $this->info('YouTube videoları çekiliyor...');

        try {
            $count = $service->fetchAndSync();

            if ($count > 0) {
                $this->info("{$count} video başarıyla senkronize edildi.");
            } else {
                $this->warn('Hiç video çekilemedi. API key ve Channel ID yapılandırmasını kontrol edin.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
