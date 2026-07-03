<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\InstagramService;
use Illuminate\Console\Command;

final class RefreshInstagramTokenCommand extends Command
{
    protected $signature = 'instagram:refresh-token
                            {--force : Süre kontrolü yapma, direkt yenile}';

    protected $description = 'Instagram long-lived token süresini kontrol edip gerekirse yeniler';

    public function handle(InstagramService $service): int
    {
        $enabled = Setting::getValue('instagram_enabled', '0') === '1';

        if (! $enabled) {
            $this->info('Instagram entegrasyonu kapalı.');

            return self::SUCCESS;
        }

        $token = Setting::getValue('instagram_access_token');

        if (empty($token)) {
            $this->warn('Instagram access token ayarlanmamış.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $service->isTokenExpiringSoon()) {
            $expiresAt = Setting::getValue('instagram_token_expires_at', '—');
            $this->info("Token hâlâ geçerli. Son kullanma: {$expiresAt}");

            return self::SUCCESS;
        }

        $this->line('Token yenileniyor...');

        $result = $service->refreshLongLivedToken();

        if ($result['success']) {
            $expiresAt = Setting::getValue('instagram_token_expires_at', '—');
            $this->info("Token yenilendi. Yeni son kullanma: {$expiresAt}");
        } else {
            $this->error("Token yenilenemedi: {$result['message']}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
