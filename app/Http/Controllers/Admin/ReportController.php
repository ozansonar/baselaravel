<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ReportFrequency;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreReportScheduleRequest;
use App\Models\AuditLog;
use App\Models\ReportSchedule;
use App\Services\ReportScheduleService;
use App\Services\ReportService;
use App\Support\LikeSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Rapor merkezi.
 *
 * Ekran üç şey yapıyor: raporu görüntülemek, indirmek ve düzenli gönderime
 * bağlamak. İndirme kendi denetleyicisinde değil, kit'in genel dışa aktarma
 * yolunda (`/admin/disa-aktar/reports/{format}`) — rapor da sonuçta bir liste ve
 * ikinci bir indirme kodu yazmanın anlamı yok.
 */
final class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportScheduleService $schedules,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('view-reports');

        $type = ReportType::tryFrom((string) $request->query('type', '')) ?? ReportType::Traffic;

        [$from, $to] = $this->reports->resolveRange(
            $request->query('range') !== null ? (string) $request->query('range') : null,
            $request->query('from') !== null ? (string) $request->query('from') : null,
            $request->query('to') !== null ? (string) $request->query('to') : null,
        );

        $report = $this->reports->build($type, $from, $to);
        $report['rows'] = $this->reports->filterRows($report['rows'], (string) $request->query('search', ''));

        return view('admin.reports.index', [
            'report'    => $report,
            'type'      => $type,
            'from'      => $from,
            'to'        => $to,
            'range'     => (string) $request->query('range', '30'),
            'search'    => (string) $request->query('search', ''),
            'summary'   => $this->reports->summary($from, $to),
            'types'     => ReportType::cases(),
            'ranges'    => ReportService::RANGES,
            'frequencies' => ReportFrequency::cases(),
            'schedules' => ReportSchedule::with('user')->orderByDesc('created_at')->get(),
            'downloads' => $this->recentDownloads(),
        ]);
    }

    /**
     * Kartın "önizle" düğmesi — raporun ilk satırları, sayfa yenilenmeden.
     */
    public function preview(Request $request, string $type): JsonResponse
    {
        Gate::authorize('view-reports');

        $reportType = ReportType::tryFrom($type);

        if ($reportType === null) {
            return response()->json(['message' => 'Tanımsız rapor türü.'], 404);
        }

        [$from, $to] = $this->reports->resolveRange(
            $request->query('range') !== null ? (string) $request->query('range') : null,
        );

        $report = $this->reports->build($reportType, $from, $to);

        return response()->json([
            'title'   => $reportType->label(),
            'range'   => $from->format('d.m.Y') . ' – ' . $to->format('d.m.Y'),
            'metrics' => $report['metrics'],
            'columns' => $report['columns'],
            // Önizleme yalnız ilk satırlar: modal bir tabloyu tamamen
            // göstermek için değil, doğru raporu seçtiğini anlamak için var.
            'rows'  => array_slice($report['rows'], 0, 10),
            'total' => count($report['rows']),
        ]);
    }

    public function storeSchedule(StoreReportScheduleRequest $request): RedirectResponse
    {
        Gate::authorize('manage-reports');

        ReportSchedule::create($request->validated() + ['user_id' => $request->user()?->getKey()]);

        return redirect()->route('admin.reports.index')->with('success', 'Zamanlanmış rapor oluşturuldu.');
    }

    public function updateSchedule(StoreReportScheduleRequest $request, ReportSchedule $schedule): RedirectResponse
    {
        Gate::authorize('manage-reports');

        $schedule->update($request->validated());

        return redirect()->route('admin.reports.index')->with('success', 'Zamanlanmış rapor güncellendi.');
    }

    public function destroySchedule(ReportSchedule $schedule): RedirectResponse
    {
        Gate::authorize('manage-reports');

        $schedule->delete();

        return redirect()->route('admin.reports.index')->with('success', 'Zamanlanmış rapor silindi.');
    }

    /**
     * "Şimdi çalıştır" — sırasını beklemeden gönderir.
     *
     * Tanımı kurarken çalıştığını görmek isteyen yöneticiye lazım; bir hafta
     * bekleyip gelmediğini fark etmek iyi bir öğrenme yolu değil.
     */
    public function runSchedule(ReportSchedule $schedule): RedirectResponse
    {
        Gate::authorize('manage-reports');

        $ok = $this->schedules->run($schedule);

        return redirect()->route('admin.reports.index')->with(
            $ok ? 'success' : 'error',
            $ok
                ? 'Rapor üretildi ve alıcılara kuyruğa alındı.'
                : 'Rapor gönderilemedi: ' . (string) $schedule->fresh()?->last_error,
        );
    }

    /**
     * Son indirilen raporlar.
     *
     * Ayrı bir tablo tutulmuyor: dışa aktarma zaten denetim izine düşüyor
     * (ExportService::recordAudit) ve ikinci bir kayıt, ikisinin ayrışması
     * demekti.
     *
     * @return \Illuminate\Support\Collection<int, AuditLog>
     */
    private function recentDownloads(): \Illuminate\Support\Collection
    {
        return AuditLog::with('user')
            // Desen elle kurulmuyor: LIKE'ın kaçış karakteri ve büyük/küçük
            // harf davranışı sürücüden sürücüye değişiyor (LikeSearch).
            ->whereRaw(LikeSearch::clause('label'), [LikeSearch::term('dışa aktarıldı')])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }
}
