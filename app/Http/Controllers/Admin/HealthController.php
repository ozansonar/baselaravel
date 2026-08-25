<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * /admin/sistem-saglik — Sistem sağlık kontrol paneli.
 */
final class HealthController extends Controller
{
    public function __construct(
        private readonly HealthCheckService $service,
    ) {}

    public function index(): View
    {
        $this->authorize('view-system-health');

        return view('admin.system-health.index', [
            'health' => $this->service->runAll(),
        ]);
    }

    public function json(): JsonResponse
    {
        $this->authorize('view-system-health');

        return response()->json($this->service->runAll());
    }
}
