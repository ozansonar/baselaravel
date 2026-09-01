<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ErrorLogService;
use Illuminate\Console\Command;

/**
 * Eski hata kayıtlarını siler.
 *
 * Ölçüt **son görülme**: aylardır tekrar eden bir hata "eski" değil, hâlâ açık.
 * İlk görülmeye baksaydı en inatçı kusurlar sessizce listeden düşerdi.
 */
final class PruneErrorLogs extends Command
{
    protected $signature = 'error-logs:prune {--days=60 : Bu kadar gündür görülmeyenleri sil}';

    protected $description = 'Uzun süredir tekrar etmeyen hata kayıtlarını temizler (varsayılan: 60 gün)';

    public function handle(ErrorLogService $errorLogs): int
    {
        $days = (int) $this->option('days');
        $deleted = $errorLogs->prune($days);

        $this->info("Silinen kayıt: {$deleted} ({$days} gündür görülmeyen)");

        return self::SUCCESS;
    }
}
