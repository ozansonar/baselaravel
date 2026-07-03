<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiCostReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /admin/ai-costs — AI maliyet raporu sayfası.
 *
 * Filtreleri:
 *  - month (Y-m, default: bu ay)
 *
 * Görünüm:
 *  - 4 stats kart (bu ay/geçen ay/yıllık + en pahalı)
 *  - 3 Chart.js grafik (günlük line, provider pie, source bar)
 *  - Top 10 en pahalı çağrı tablosu
 *  - Bütçe durumu banner
 */
final class AiCostController extends Controller
{
    public function __construct(
        private readonly AiCostReportService $reports,
    ) {}

    public function index(Request $request): View
    {
        // Filtre: ay (Y-m formatı)
        $monthRaw = (string) $request->query('month', now()->format('Y-m'));
        try {
            $month = CarbonImmutable::createFromFormat('Y-m', $monthRaw)->startOfMonth();
        } catch (\Throwable) {
            $month = CarbonImmutable::now()->startOfMonth();
        }

        // Yıllık toplam (Ocak → şu ay)
        $yearStart = $month->startOfYear();
        $yearEnd = $month->endOfMonth();
        $yearlyTotal = 0.0;
        $yearlyMonths = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $yearStart->addMonths($i);
            if ($m->greaterThan($yearEnd)) {
                break;
            }
            $monthly = $this->reports->getMonthlyTotal($m);
            $yearlyMonths[] = [
                'label' => $m->translatedFormat('M'),
                'image' => $monthly['image'],
                'text'  => $monthly['text'],
                'total' => $monthly['total'],
            ];
            $yearlyTotal += $monthly['total'];
        }

        return view('admin.ai-costs.index', [
            'month'              => $month->format('Y-m'),
            'monthLabel'         => $month->translatedFormat('F Y'),
            'monthlyTotal'       => $this->reports->getMonthlyTotal($month),
            'momChange'          => $this->reports->getMonthOverMonthChange(),
            'budgetStatus'       => $this->reports->budgetStatus(),
            'dailyBreakdown'     => $this->reports->getDailyBreakdown($month),
            'providerBreakdown'  => $this->reports->getProviderBreakdown(
                $month->startOfMonth()->toMutable(),
                $month->endOfMonth()->toMutable(),
            ),
            'sourceBreakdown'    => $this->reports->getSourceBreakdown(
                $month->startOfMonth()->toMutable(),
                $month->endOfMonth()->toMutable(),
            ),
            'topExpensive'       => $this->reports->getTopExpensive(
                10,
                $month->startOfMonth()->toMutable(),
                $month->endOfMonth()->toMutable(),
            ),
            'yearlyTotal'        => round($yearlyTotal, 2),
            'yearlyMonths'       => $yearlyMonths,
            'prevMonth'          => $month->subMonth()->format('Y-m'),
            'nextMonth'          => $month->addMonth()->format('Y-m'),
        ]);
    }
}
