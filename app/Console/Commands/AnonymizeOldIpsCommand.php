<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PageView;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AnonymizeOldIpsCommand extends Command
{
    protected $signature = 'analytics:anonymize-ips {--days=90 : Number of days after which to mask IPs}';

    protected $description = 'Mask the last octet of IP addresses in page_views older than N days (KVKK compliance)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = Carbon::now()->subDays($days);

        $this->info("Anonymizing IPs older than {$threshold->toDateTimeString()}...");

        $total = 0;
        PageView::query()
            ->where('ip_masked', false)
            ->where('viewed_at', '<', $threshold)
            ->chunkById(500, function ($chunk) use (&$total): void {
                foreach ($chunk as $view) {
                    $view->ip_address = $this->maskIp((string) $view->ip_address);
                    $view->ip_masked = true;
                    $view->saveQuietly();
                    $total++;
                }
            });

        $this->info("Anonymized {$total} rows.");

        return self::SUCCESS;
    }

    private function maskIp(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);
            $keep = array_slice($parts, 0, 4);
            return implode(':', $keep) . '::0';
        }

        $parts = explode('.', $ip);
        if (count($parts) !== 4) {
            return $ip;
        }
        $parts[3] = '0';

        return implode('.', $parts);
    }
}
