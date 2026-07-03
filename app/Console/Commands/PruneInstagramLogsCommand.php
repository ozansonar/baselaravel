<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InstagramPostLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

final class PruneInstagramLogsCommand extends Command
{
    protected $signature = 'instagram:prune-logs {--days=30 : N günden eski Instagram API loglarını sil}';

    protected $description = 'Instagram API log tablosundan N günden eski kayıtları kalıcı olarak siler';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days en az 1 olmalı.');

            return self::FAILURE;
        }

        $threshold = Carbon::now()->subDays($days);

        $this->info("Threshold: {$threshold->toDateTimeString()} ({$days} gün öncesi)");

        $deleted = InstagramPostLog::query()
            ->where('created_at', '<', $threshold)
            ->delete();

        $this->info("{$deleted} log kaydı silindi.");

        return self::SUCCESS;
    }
}
