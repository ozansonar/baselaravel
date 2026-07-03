<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Google\SearchConsoleService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Cron: Google Search Console verilerini çek (top queries + per-page).
 * Schedule: 6 saatte bir (routes/console.php).
 *
 * Komut:  php artisan gsc:fetch
 * Opsiyonel: --days=28 (default)
 */
final class FetchGscMetricsCommand extends Command
{
    protected $signature = 'gsc:fetch {--days=28}';

    protected $description = 'Google Search Console arama ve sayfa metriklerini çek (cron)';

    public function handle(SearchConsoleService $gsc): int
    {
        if (Setting::getValue('google_gsc_enabled', '1') !== '1') {
            $this->info('GSC entegrasyonu kapalı (settings: google_gsc_enabled = 0). Atlanıyor.');

            return self::SUCCESS;
        }

        if (! $gsc->isEnabled()) {
            $this->warn('Service Account JSON yapılandırılmamış. Atlanıyor.');

            return self::SUCCESS;
        }

        $days = (int) $this->option('days');

        try {
            $this->line("→ Top queries çekiliyor (son {$days} gün)...");
            $q = $gsc->fetchAndStoreQueries($days);
            $this->info("  ✓ {$q['rows']} sorgu kaydedildi ({$q['from']} - {$q['to']})");

            $this->line("→ Per-page metrics çekiliyor (son {$days} gün)...");
            $p = $gsc->fetchAndStorePages($days);
            $this->info("  ✓ {$p['rows']} sayfa kaydedildi ({$p['from']} - {$p['to']})");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('GSC metrics çekme hatası: ' . $e->getMessage());

            report($e);

            return self::FAILURE;
        }
    }
}
