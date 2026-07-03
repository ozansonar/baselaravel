<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SubmitIndexingRequestJob;
use App\Models\GoogleApiLog;
use App\Models\GscPageMetric;
use App\Models\GscQueryMetric;
use App\Models\IndexingRequest;
use App\Services\Google\GoogleAuthService;
use App\Services\Google\IndexingApiService;
use App\Services\Google\SearchConsoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Admin → SEO Performance.
 * GSC verilerini sergiler (lokal DB'den okur, canlı API çağrısı yok).
 * "Manuel veri çek" butonu cron'u tetikler — diğer her şey background.
 */
final class SeoPerformanceController extends Controller
{
    public function __construct(
        private readonly GoogleAuthService $auth,
        private readonly SearchConsoleService $gsc,
        private readonly IndexingApiService $indexing,
    ) {}

    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'queries');
        $tab = in_array($tab, ['queries', 'pages', 'indexing', 'logs'], true) ? $tab : 'queries';

        $type = (string) $request->query('type', '');
        $search = trim((string) $request->query('search', ''));

        $configured = $this->auth->isConfigured();
        $serviceAccountEmail = $this->auth->serviceAccountEmail();

        // ── Stats ──
        $totalQueries = GscQueryMetric::query()->count();
        $totalPages   = GscPageMetric::query()->count();
        $pendingIndex = IndexingRequest::query()->where('status', IndexingRequest::STATUS_PENDING)->count();
        $lastFetch    = GoogleApiLog::query()
            ->service(GoogleApiLog::SERVICE_GSC)
            ->where('status', GoogleApiLog::STATUS_SUCCESS)
            ->latest()
            ->first(['created_at']);

        // ── Top queries ──
        $queries = GscQueryMetric::query()
            ->when($search !== '', fn ($q) => $q->where('query', 'like', '%' . $search . '%'))
            ->orderByDesc('clicks')
            ->limit(100)
            ->get();

        // ── Page metrics ──
        $pagesQuery = GscPageMetric::query()
            ->when($type !== '', fn ($q) => $q->where('page_type', $type))
            ->when($search !== '', fn ($q) => $q->where('page_path', 'like', '%' . $search . '%'))
            ->orderByDesc('clicks');

        $pages = $pagesQuery->paginate(25)->withQueryString();

        // Düşük CTR adayları (pozisyon iyi ama tıklanmıyor)
        $lowCtrPages = GscPageMetric::query()->lowCtr()->orderBy('ctr')->limit(10)->get();

        // ── Indexing requests ──
        $indexingRequests = IndexingRequest::query()
            ->latest()
            ->limit(50)
            ->get();

        // ── API logs ──
        $apiLogs = GoogleApiLog::query()
            ->latest()
            ->limit(100)
            ->get();

        return view('admin.seo-performance.index', [
            'tab'                 => $tab,
            'type'                => $type,
            'search'              => $search,
            'configured'          => $configured,
            'serviceAccountEmail' => $serviceAccountEmail,
            'totalQueries'        => $totalQueries,
            'totalPages'          => $totalPages,
            'pendingIndex'        => $pendingIndex,
            'lastFetch'           => $lastFetch,
            'queries'             => $queries,
            'pages'               => $pages,
            'lowCtrPages'         => $lowCtrPages,
            'indexingRequests'    => $indexingRequests,
            'apiLogs'             => $apiLogs,
        ]);
    }

    public function fetchNow(Request $request): RedirectResponse
    {
        if (! $this->auth->isConfigured()) {
            return back()->with('error', 'Service Account JSON yapılandırılmamış. Önce Google Cloud setup\'ı tamamla.');
        }

        @set_time_limit(180);

        try {
            $q = $this->gsc->fetchAndStoreQueries();
            $p = $this->gsc->fetchAndStorePages();

            return back()->with(
                'success',
                "Veri çekildi: {$q['rows']} arama, {$p['rows']} sayfa metriği güncellendi.",
            );
        } catch (Throwable $e) {
            return back()->with('error', 'Veri çekme hatası: ' . $e->getMessage());
        }
    }

    public function testConnection(): JsonResponse
    {
        if (! $this->auth->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Service Account JSON bulunamadı. storage/app/google/service-account.json yolu kontrol et.',
            ], 422);
        }

        try {
            // Trivial test: 1 günlük top query çek (yan etki yok, sadece auth doğrula)
            $this->gsc->fetchAndStoreQueries(days: 1, limit: 5);

            return response()->json([
                'success' => true,
                'message' => 'Google Search Console bağlantısı başarılı.',
                'email'   => $this->auth->serviceAccountEmail(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bağlantı hatası: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function reindex(IndexingRequest $indexingRequest): RedirectResponse
    {
        $indexingRequest->update([
            'status'        => IndexingRequest::STATUS_PENDING,
            'error_message' => null,
        ]);

        SubmitIndexingRequestJob::dispatch($indexingRequest->id);

        return back()->with('success', 'Yeniden indeksleme isteği kuyruğa eklendi.');
    }
}
