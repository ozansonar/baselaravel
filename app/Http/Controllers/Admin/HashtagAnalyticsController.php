<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HashtagAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /admin/hashtag-raporu — Hashtag performans raporu.
 *
 * Yayınlanmış post'lardaki hashtag'leri tarayıp ortalama engagement
 * gösterir; hangi tag'ler işe yarıyor, hangileri ölü ağırlık.
 */
final class HashtagAnalyticsController extends Controller
{
    public function __construct(
        private readonly HashtagAnalyticsService $service,
    ) {}

    public function index(Request $request): View
    {
        $days = (int) $request->query('days', 90);
        $days = max(7, min(365, $days)); // 7-365 arası sınırla

        $sort = (string) $request->query('sort', 'engagement'); // engagement | usage | low

        $rows = match ($sort) {
            'usage' => $this->service->topByUsage(50, $days),
            'low'   => $this->service->lowPerformers(20, $days),
            default => $this->service->topByEngagement(50, $days),
        };

        return view('admin.hashtag-analytics.index', [
            'rows'    => $rows,
            'summary' => $this->service->summary($days),
            'days'    => $days,
            'sort'    => $sort,
        ]);
    }
}
