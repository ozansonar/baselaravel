<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats'          => $this->dashboardService->getStats(),
            'recentMessages' => $this->dashboardService->recentMessages(),
            'recentPosts'    => $this->dashboardService->recentPosts(),
        ]);
    }
}
