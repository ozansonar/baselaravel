<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PageView;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PruneOldPageViewsCommand extends Command
{
    protected $signature = 'analytics:prune-old {--days=365 : Delete page_views older than N days}';

    protected $description = 'Permanently delete page_views older than N days (aggregate data is preserved)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = Carbon::now()->subDays($days);

        $this->info("Pruning page_views older than {$threshold->toDateString()}...");

        $deleted = PageView::query()
            ->where('viewed_at', '<', $threshold)
            ->forceDelete();

        $this->info("Deleted {$deleted} rows.");

        return self::SUCCESS;
    }
}
