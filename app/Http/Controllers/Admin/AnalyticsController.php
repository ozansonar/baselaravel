<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageView;
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

    /**
     * Ziyaret kaydında sayfa başına seçilebilecek değerler.
     */
    private const VISIT_PER_PAGE = [25, 50, 100, 200];

    /**
     * Listede seçilebilecek sıralamalar. İstekten gelen değer bu kümeyle
     * sınırlı; serbest bırakılsaydı sütun adı doğrudan sorguya girerdi.
     *
     * @var array<string, string>
     */
    private const VISIT_SORT = [
        'recent' => 'Önce en yeni',
        'oldest' => 'Önce en eski',
    ];

    public function visits(Request $request): View
    {
        $this->authorize('view-analytics');

        $perPage = (int) $request->integer('per_page', 50);
        $perPage = in_array($perPage, self::VISIT_PER_PAGE, true) ? $perPage : 50;

        $sort = $request->string('sort')->toString();
        $sort = array_key_exists($sort, self::VISIT_SORT) ? $sort : '';

        $filters = [
            'url'         => (string) $request->string('url')->trim()->value(),
            'is_bot'      => (string) $request->string('is_bot')->value(),
            'device_type' => (string) $request->string('device_type')->value(),
            'browser'     => (string) $request->string('browser')->value(),
            'os'          => (string) $request->string('os')->value(),
            'referrer'    => (string) $request->string('referrer')->value(),
            'visitor'     => (string) $request->string('visitor')->value(),
            'from'        => (string) $request->string('from')->value(),
            'to'          => (string) $request->string('to')->value(),
            'sort'        => $sort,
        ];

        $visits = $this->analyticsService->paginateVisits($filters, $perPage);

        return view('admin.analytics.visits', [
            'visits'  => $visits,
            'filters' => $filters,
            // "0" da bir seçim: array_filter kullanılsaydı "Sadece insan"
            // süzgeci açıkken ekran süzgeç yokmuş gibi davranırdı.
            'filtered' => collect($filters)
                ->except('sort')
                ->filter(fn (string $value): bool => $value !== '')
                ->isNotEmpty(),
            'sortOptions'   => self::VISIT_SORT,
            'perPage'       => $perPage,
            'perPageList'   => self::VISIT_PER_PAGE,
            'filterOptions' => $this->analyticsService->visitFilterOptions(),
            'totalAll'      => PageView::count(),
            'totalHumans'   => PageView::where('is_bot', false)->count(),
            'totalBots'     => PageView::where('is_bot', true)->count(),
            'todayCount'    => PageView::whereDate('viewed_at', today())->count(),
        ]);
    }

    /**
     * Canlı ekranın kendini yenileme aralığı.
     *
     * Ekrandaki bilgi metni, JS yoklaması ve bayatlık göstergesi aynı sayıyı
     * söylemeli; üçü ayrı yerde yazılınca biri değişince diğerleri yalan söyler.
     */
    private const LIVE_REFRESH_SECONDS = 10;

    /**
     * Who is on the site right now.
     *
     * The page itself is a shell; the numbers come from liveData() on a timer,
     * so watching it does not mean reloading the whole panel every few seconds.
     */
    public function live(Request $request): View
    {
        $this->authorize('view-analytics');

        return view('admin.analytics.live', [
            'windowMinutes'  => $this->resolveWindow($request),
            'includeBots'    => $request->boolean('include_bots'),
            'refreshSeconds' => self::LIVE_REFRESH_SECONDS,
        ]);
    }

    /**
     * The polling endpoint behind the live screen.
     */
    public function liveData(Request $request): JsonResponse
    {
        $this->authorize('view-analytics');

        $window = $this->resolveWindow($request);
        $excludeBots = ! $request->boolean('include_bots');
        $afterId = $request->integer('after_id') ?: null;

        $visitors = $this->analyticsService->getActiveVisitors($window, $excludeBots);

        return response()->json([
            'online'      => $this->analyticsService->getOnlineCount($window, $excludeBots),
            'window'      => $window,
            'server_time' => now()->format('H:i:s'),
            'visitors'    => $visitors->values(),
            'pages'       => $this->analyticsService->getActivePages($window),
            'feed'        => $this->analyticsService->getLiveFeed(30, $afterId, $excludeBots)
                ->map(fn ($view): array => [
                    'id'          => $view->id,
                    'url_path'    => $view->url_path,
                    'device_type' => $view->device_type,
                    'browser'     => $view->browser,
                    'is_bot'      => (bool) $view->is_bot,
                    'bot_name'    => $view->bot_name,
                    'user'        => $view->user?->full_name,
                    'session_id'  => substr((string) $view->session_id, 0, 8),
                    'at'          => $view->viewed_at?->format('H:i:s'),
                ])->values(),
        ]);
    }

    /**
     * Keep the window to values the screen offers, so a hand-edited query
     * cannot ask for a year of data on a polling endpoint.
     */
    private function resolveWindow(Request $request): int
    {
        $window = (int) $request->integer('window', AnalyticsService::ACTIVE_WINDOW_MINUTES);

        return in_array($window, [1, 5, 15, 30, 60], true)
            ? $window
            : AnalyticsService::ACTIVE_WINDOW_MINUTES;
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
