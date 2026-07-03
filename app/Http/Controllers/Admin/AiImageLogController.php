<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiGeneratedImage;
use App\Services\AiImageLogService;
use App\Services\UploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AiImageLogController extends Controller
{
    public function __construct(
        private readonly AiImageLogService $logService,
        private readonly UploadService $uploadService,
    ) {}

    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only(['source', 'status', 'model', 'q', 'date_from', 'date_to']);

        return view('admin.ai-image-logs.index', [
            'logs'            => $this->logService->paginate($perPage, $filters),
            'summary'         => $this->logService->summary(),
            'sourceLabels'    => AiImageLogService::SOURCE_LABELS,
            'sourceBreakdown' => $this->logService->sourceBreakdown(),
            'distinctModels'  => $this->logService->distinctModels(),
            'perPage'         => $perPage,
            'filters'         => $filters,
        ]);
    }

    public function show(AiGeneratedImage $aiImageLog): View
    {
        $aiImageLog->load(['promptTemplate', 'author']);

        return view('admin.ai-image-logs.show', [
            'log' => $aiImageLog,
        ]);
    }

    public function destroy(AiGeneratedImage $aiImageLog): RedirectResponse
    {
        if ($aiImageLog->image_path) {
            $this->uploadService->deleteImage($aiImageLog->image_path);
        }

        $aiImageLog->delete();

        return redirect()
            ->route('admin.ai-image-logs.index')
            ->with('success', 'AI görsel log kaydı silindi.');
    }
}
