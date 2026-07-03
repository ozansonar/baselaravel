<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BestTimeAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /admin/en-iyi-saat — Yayınlanmış post'ların gün/saat performansı.
 */
final class BestTimeAnalyticsController extends Controller
{
    public function __construct(
        private readonly BestTimeAnalyticsService $service,
    ) {}

    public function index(Request $request): View
    {
        $days = (int) $request->query('days', 90);
        $days = max(14, min(365, $days));

        return view('admin.best-time-analytics.index', [
            'days'         => $days,
            'byHour'       => $this->service->byHour($days),
            'byDayOfWeek'  => $this->service->byDayOfWeek($days),
            'heatmap'      => $this->service->heatmap($days),
            'topSlots'     => $this->service->topSlots(3, $days),
            'totalPosts'   => $this->service->totalAnalyzedPosts($days),
        ]);
    }
}
