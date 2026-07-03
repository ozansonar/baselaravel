<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\TiktokService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * TikTok access token preemptive refresh.
 *
 * docs/tiktok.md Bölüm 5.6 + Faz 6.
 *
 * Access token TikTok'tan 24 saat geçerli (default), refresh_token 365 gün.
 * Cron günde 1 kez çağrılır; sadece token 3 gün veya daha az kaldıysa
 * refresh tetiklenir (boşa istek atmamak için).
 */
final class RefreshTiktokTokenCommand extends Command
{
    protected $signature = 'tiktok:refresh-token
                            {--force : Süresi dolmasa bile refresh yap}';

    protected $description = 'TikTok access token\'ı (refresh_token ile) yeniler — günde 1 kez cron';

    public function handle(TiktokService $tiktokService): int
    {
        $enabled = Setting::getValue('tiktok_enabled', '0') === '1';
        if (! $enabled && ! $this->option('force')) {
            $this->warn('TikTok pasif. Refresh atlandı.');
            return self::SUCCESS;
        }

        $refreshToken = trim((string) Setting::getValue('tiktok_refresh_token', ''));
        if ($refreshToken === '') {
            $this->error('Refresh token tanımlı değil. Settings → TikTok → Bağla.');
            return self::FAILURE;
        }

        // Süresi 3 günden fazla kaldıysa skip (boşa istek atmamak için)
        if (! $this->option('force')) {
            $expiresAtStr = (string) Setting::getValue('tiktok_expires_at', '');
            if ($expiresAtStr !== '') {
                try {
                    $expiresAt = Carbon::parse($expiresAtStr);
                    if ($expiresAt->gt(now()->addDays(3))) {
                        $this->info('Token hala 3+ gün geçerli, refresh gereksiz.');
                        $this->line('  Bitiş: ' . $expiresAt->translatedFormat('d M Y H:i'));
                        return self::SUCCESS;
                    }
                } catch (\Throwable) {
                    // parse hatası → yine de refresh dene
                }
            }
        }

        $this->info('TikTok token refresh deneniyor...');
        $result = $tiktokService->refreshAccessToken();

        if ($result['success']) {
            $this->info('✓ Token yenilendi.');
            if (isset($result['expires_at'])) {
                $this->line('  Yeni bitiş: ' . $result['expires_at']);
            }
            return self::SUCCESS;
        }

        $this->error('✗ Token refresh başarısız: ' . $result['message']);
        return self::FAILURE;
    }
}
