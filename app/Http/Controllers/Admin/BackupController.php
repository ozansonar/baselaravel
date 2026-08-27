<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkDeleteBackupsRequest;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * /admin/yedekler — Otomatik yedek yönetimi.
 */
final class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('view-backups');

        $filters = [
            'q'    => $request->string('q')->trim()->value(),
            'sort' => $request->string('sort')->value(),
        ];

        return view('admin.backups.index', [
            'backups' => $this->service->list($filters),
            'stats'   => $this->service->stats(),
            'filters' => $filters,
        ]);
    }

    public function create(): JsonResponse
    {
        $this->authorize('manage-backups');

        $result = $this->service->create();
        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function download(string $filename): BinaryFileResponse|RedirectResponse
    {
        $this->authorize('manage-backups');

        $path = $this->service->downloadPath($filename);

        if ($path === null) {
            return redirect()->route('admin.backups.index')
                ->with('error', 'Yedek bulunamadı: ' . $filename);
        }

        return response()->download($path);
    }

    public function destroy(Request $request, string $filename): RedirectResponse
    {
        $this->authorize('delete-backups');

        $ok = $this->service->delete($filename);

        return redirect()->route('admin.backups.index')
            ->with($ok ? 'success' : 'error',
                $ok ? "Yedek silindi: {$filename}" : "Silinemedi: {$filename}");
    }

    /**
     * Listeden seçilen yedekleri tek işlemde siler.
     *
     * Kullanıcı hangi filtreyle bakıyorsa oraya döner; silinemeyen dosya varsa
     * kaçının gittiği ve kaçının kaldığı ayrı ayrı bildirilir.
     */
    public function bulkDestroy(BulkDeleteBackupsRequest $request): RedirectResponse
    {
        $this->authorize('delete-backups');

        $result = $this->service->deleteMany($request->filenames());

        $deleted = count($result['deleted']);
        $failed = count($result['failed']);

        $target = redirect()->route('admin.backups.index', $request->only(['q', 'sort']));

        if ($deleted === 0) {
            return $target->with('error', 'Hiçbir yedek silinemedi.');
        }

        if ($failed > 0) {
            return $target->with('warning', "{$deleted} yedek silindi, {$failed} tanesi silinemedi.");
        }

        return $target->with('success', "{$deleted} yedek silindi.");
    }
}
