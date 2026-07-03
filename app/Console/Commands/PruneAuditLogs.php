<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Command;

final class PruneAuditLogs extends Command
{
    protected $signature = 'audit-logs:prune {--days=90 : Bu kadar günden eskileri sil}';

    protected $description = 'Eski audit log kayıtlarını temizler (default: 90 gün)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $deleted = AuditLogger::pruneOlderThan($days);

        $this->info("Silinen kayıt: {$deleted} ({$days} günden eski)");

        return self::SUCCESS;
    }
}
