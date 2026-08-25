<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AnalyticsDailyStat;
use App\Models\PageView;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AggregateDailyStatsCommand extends Command
{
    protected $signature = 'analytics:aggregate-daily {--date= : Specific date (YYYY-MM-DD), defaults to yesterday}';

    protected $description = 'Aggregate page_views into analytics_daily_stats for fast dashboard queries';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : Carbon::yesterday();

        $this->info("Aggregating stats for {$date->toDateString()}...");

        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $base = PageView::query()->whereBetween('viewed_at', [$start, $end]);
        $humans = (clone $base)->where('is_bot', false);

        $totalViews = (clone $humans)->count();
        $uniqueVisitors = (clone $humans)->distinct('session_id')->count('session_id');
        $botViews = (clone $base)->where('is_bot', true)->count();

        $deviceCounts = (clone $humans)
            ->selectRaw('device_type, COUNT(*) as count')
            ->groupBy('device_type')
            ->pluck('count', 'device_type')
            ->all();

        $topPages = (clone $humans)
            ->selectRaw('url_path, COUNT(*) as count')
            ->groupBy('url_path')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->map(fn ($r) => ['path' => $r->url_path, 'count' => (int) $r->count])
            ->all();

        $topReferrers = (clone $humans)
            ->selectRaw("COALESCE(referrer_domain, 'direct') as source, COUNT(*) as count")
            ->groupBy('source')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->map(fn ($r) => ['source' => $r->source, 'count' => (int) $r->count])
            ->all();

        $topBrowsers = (clone $humans)
            ->selectRaw("COALESCE(browser, 'unknown') as browser, COUNT(*) as count")
            ->groupBy('browser')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->map(fn ($r) => ['browser' => $r->browser, 'count' => (int) $r->count])
            ->all();

        // The date column is unique, so a soft-deleted row for this date would
        // stay invisible to a plain updateOrCreate and the insert would then
        // hit the unique index. Look through trashed rows and revive instead.
        // whereDate keeps the lookup working whether the driver stores a bare
        // date or a full timestamp.
        $stat = AnalyticsDailyStat::withTrashed()
            ->whereDate('date', $date->toDateString())
            ->first()
            ?? new AnalyticsDailyStat(['date' => $date->toDateString()]);

        if ($stat->trashed()) {
            $stat->restore();
        }

        $stat->fill([
            'total_views'     => $totalViews,
            'unique_visitors' => $uniqueVisitors,
            'bot_views'       => $botViews,
            'desktop_views'   => (int) ($deviceCounts['desktop'] ?? 0),
            'mobile_views'    => (int) ($deviceCounts['mobile'] ?? 0),
            'tablet_views'    => (int) ($deviceCounts['tablet'] ?? 0),
            'top_pages'       => $topPages,
            'top_referrers'   => $topReferrers,
            'top_browsers'    => $topBrowsers,
        ])->save();

        $this->info("Done. total={$totalViews}, unique={$uniqueVisitors}, bots={$botViews}");

        return self::SUCCESS;
    }
}
