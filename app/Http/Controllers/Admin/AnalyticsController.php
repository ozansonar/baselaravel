<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('view-analytics');

        [$from, $to] = $this->resolveDateRange($request);
        $includeBots = $request->boolean('include_bots');

        // ?refresh=1 → analitik cache'ini sıfırla, ardından temiz URL'ye yönlendir.
        if ($request->boolean('refresh')) {
            $this->analyticsService->flushCache();
        }

        // Toplam kayıt: tüm zamanlar (tarih filtresinden bağımsız).
        $totalRecords = \App\Models\PageView::count();

        return view('admin.analytics.index', [
            'from'             => $from,
            'to'               => $to,
            'range'            => $request->input('range', '30d'),
            'includeBots'      => $includeBots,
            'totalRecords'     => $totalRecords,
            'stats'            => $this->analyticsService->getStats($from, $to, !$includeBots),
            'dailyChart'       => $this->analyticsService->getDailyChart($from, $to, !$includeBots),
            'topPages'         => $this->analyticsService->getTopPages($from, $to, 10, !$includeBots),
            'deviceBreakdown'  => $this->analyticsService->getDeviceBreakdown($from, $to),
            'browserBreakdown' => $this->analyticsService->getBrowserBreakdown($from, $to),
            'referrers'        => $this->analyticsService->getReferrerBreakdown($from, $to),
            'botActivity'      => $this->analyticsService->getBotActivity($from, $to),
            'recentVisits'     => $this->analyticsService->getRecentVisits(20, !$includeBots),
        ]);
    }

    public function visits(Request $request): View
    {
        $this->authorize('view-analytics');

        $filters = $request->only(['from', 'to', 'is_bot', 'device_type', 'url']);
        $visits = $this->analyticsService->paginateVisits($filters, 50);

        $totalAll = \App\Models\PageView::count();
        $totalHumans = \App\Models\PageView::where('is_bot', false)->count();
        $totalBots = \App\Models\PageView::where('is_bot', true)->count();
        $todayCount = \App\Models\PageView::whereDate('viewed_at', today())->count();

        return view('admin.analytics.visits', [
            'visits'      => $visits,
            'filters'     => $filters,
            'totalAll'    => $totalAll,
            'totalHumans' => $totalHumans,
            'totalBots'   => $totalBots,
            'todayCount'  => $todayCount,
        ]);
    }

    public function chart(Request $request, string $type): JsonResponse
    {
        $this->authorize('view-analytics');

        [$from, $to] = $this->resolveDateRange($request);
        $includeBots = $request->boolean('include_bots');

        $data = match ($type) {
            'daily'     => $this->analyticsService->getDailyChart($from, $to, !$includeBots),
            'top-pages' => $this->analyticsService->getTopPages($from, $to, 10, !$includeBots),
            'device'    => $this->analyticsService->getDeviceBreakdown($from, $to),
            'browser'   => $this->analyticsService->getBrowserBreakdown($from, $to),
            'referrer'  => $this->analyticsService->getReferrerBreakdown($from, $to),
            'bot'       => $this->analyticsService->getBotActivity($from, $to),
            default     => ['error' => 'Unknown chart type'],
        };

        return response()->json($data);
    }

    private function resolveDateRange(Request $request): array
    {
        $preset = $request->input('range', '30d');

        $to = Carbon::now()->endOfDay();
        $from = match ($preset) {
            'today'   => Carbon::today()->startOfDay(),
            '7d'      => Carbon::now()->subDays(6)->startOfDay(),
            '30d'     => Carbon::now()->subDays(29)->startOfDay(),
            '90d'     => Carbon::now()->subDays(89)->startOfDay(),
            'custom'  => $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : Carbon::now()->subDays(29)->startOfDay(),
            default   => Carbon::now()->subDays(29)->startOfDay(),
        };

        if ($preset === 'custom' && $request->filled('to')) {
            $to = Carbon::parse($request->input('to'))->endOfDay();
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}
