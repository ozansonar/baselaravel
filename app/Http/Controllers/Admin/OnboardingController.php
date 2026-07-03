<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * /admin/kurulum — İlk kurulum yönlendirme sihirbazı.
 */
final class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $service,
    ) {}

    public function index(): View
    {
        return view('admin.onboarding.index', [
            'steps'      => $this->service->steps(),
            'completed'  => $this->service->completedCount(),
            'total'      => OnboardingService::TOTAL_STEPS,
            'percent'    => $this->service->progressPercent(),
            'isComplete' => $this->service->isComplete(),
            'nextStep'   => $this->service->nextStep(),
        ]);
    }

    /**
     * Manuel olarak "tamamlandı" işaretle (kullanıcı atlamak isterse).
     */
    public function complete(): RedirectResponse
    {
        $this->service->markComplete();

        return redirect()->route('admin.dashboard')
            ->with('success', 'Kurulum tamamlandı.');
    }

    /**
     * Reset — debug için (geliştirme amaçlı, prod'da görünmez).
     */
    public function reset(): RedirectResponse
    {
        if (! app()->environment(['local', 'development'])) {
            abort(404);
        }
        $this->service->reset();
        return redirect()->route('admin.onboarding.index');
    }
}
