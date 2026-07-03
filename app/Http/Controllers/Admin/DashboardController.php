<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InstagramPostStatus;
use App\Http\Controllers\Controller;
use App\Models\GscQueryMetric;
use App\Models\InstagramPost;
use App\Models\InstagramPostLog;
use App\Models\Setting;
use App\Services\AdminInsightsService;
use App\Services\CategoryService;
use App\Services\DashboardService;
use App\Services\InstagramService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly CategoryService $categoryService,
        private readonly InstagramService $instagramService,
        private readonly AdminInsightsService $insightsService,
    ) {}

    public function __invoke(): View
    {
        $topQueries = Cache::remember(
            'gsc.top_queries.dashboard',
            now()->addMinutes(15),
            fn () => GscQueryMetric::query()
                ->orderByDesc('clicks')
                ->limit(10)
                ->get(['query', 'clicks', 'impressions', 'ctr', 'position']),
        );

        $instagramTokenStatus = $this->instagramService->getDisplayTokenStatus();
        $instagramSummary     = $this->buildInstagramSummary();

        return view('admin.dashboard', [
            'stats'                => $this->dashboardService->getStats(),
            'orderStatusCounts'    => $this->dashboardService->orderStatusCounts(),
            'recentOrders'         => $this->dashboardService->recentOrders(),
            'featuredProducts'     => $this->dashboardService->featuredProducts(),
            'recentReviews'        => $this->dashboardService->recentReviews(),
            'recentMessages'       => $this->dashboardService->recentMessages(),
            'categories'           => $this->categoryService->allActive(),
            'topQueries'           => $topQueries,
            'instagramTokenStatus' => $instagramTokenStatus,
            'instagramSummary'     => $instagramSummary,
            'aiCostSummary'        => $this->buildAiCostSummary(),
            'adminInsights'        => $this->insightsService->summary(),
        ]);
    }

    /**
     * AI maliyet widget verisi — bu ay toplam, bütçe durumu, 7-günlük sparkline,
     * provider dağılımı. Cache 1 dk (block mekanizması için kritik).
     *
     * @return array{
     *     budget: array<string, mixed>,
     *     mom: array<string, mixed>,
     *     last_7_days: list<array{date: string, total: float}>,
     *     provider_breakdown: array<string, array{cost: float, count: int}>
     * }|null
     */
    private function buildAiCostSummary(): ?array
    {
        try {
            $svc = app(\App\Services\AiCostReportService::class);

            return [
                'budget'             => $svc->budgetStatus(),
                'mom'                => $svc->getMonthOverMonthChange(),
                'last_7_days'        => $svc->getLastNDaysTotals(7),
                'provider_breakdown' => $svc->getProviderBreakdown(),
            ];
        } catch (\Throwable $e) {
            // Cost report fail ederse dashboard'u kırma — sessizce widget'ı atla
            return null;
        }
    }

    /**
     * Dashboard widget için Instagram özet verisi (cache: 5 dk).
     *
     * @return array{
     *     scheduled: int,
     *     failed_active: int,
     *     success_24h: int,
     *     failed_24h: int,
     *     last_published_at: ?\Illuminate\Support\Carbon,
     *     cron_last_run: ?\Illuminate\Support\Carbon,
     *     cron_healthy: bool,
     * }
     */
    private function buildInstagramSummary(): array
    {
        return Cache::remember('dashboard.instagram_summary', now()->addMinutes(5), function () {
            $since24h = now()->subDay();

            $scheduled = InstagramPost::where('status', InstagramPostStatus::Scheduled->value)->count();
            $failedActive = InstagramPost::where('status', InstagramPostStatus::Failed->value)
                ->where('retry_count', '<', InstagramPost::MAX_RETRY_COUNT)
                ->count();

            $row = InstagramPostLog::query()
                ->where('created_at', '>=', $since24h)
                ->whereIn('action', ['publish', 'fb_publish_single', 'fb_publish_multi'])
                ->selectRaw("SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) AS success")
                ->selectRaw("SUM(CASE WHEN status='failed'  THEN 1 ELSE 0 END) AS failed")
                ->first();

            // value('published_at') raw string döndürür ve cast uygulamaz.
            // Model instance üzerinden alıp datetime cast'inin Carbon dönmesini sağlıyoruz.
            $lastPublishedAt = InstagramPost::where('status', InstagramPostStatus::Published->value)
                ->latest('published_at')
                ->first()?->published_at;

            $cronRaw = (string) Setting::getValue('instagram_cron_last_run', '');
            $cronAt = null;
            $cronHealthy = false;
            if ($cronRaw !== '') {
                try {
                    $cronAt = Carbon::parse($cronRaw);
                    $cronHealthy = $cronAt->gt(now()->subHours(26));
                } catch (\Throwable) {}
            }

            return [
                'scheduled'         => $scheduled,
                'failed_active'     => $failedActive,
                'success_24h'       => (int) ($row?->success ?? 0),
                'failed_24h'        => (int) ($row?->failed ?? 0),
                'last_published_at' => $lastPublishedAt,
                'cron_last_run'     => $cronAt,
                'cron_healthy'      => $cronHealthy,
            ];
        });
    }
}
