<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSliderRequest;
use App\Http\Requests\UpdateSliderRequest;
use App\Models\Slider;
use App\Services\SliderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SliderController extends Controller
{
    public function __construct(
        private readonly SliderService $sliderService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Slider::class);

        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only(['status', 'search']);

        return view('admin.sliders.index', [
            'sliders'      => $this->sliderService->paginate($perPage, $filters),
            'stats'        => $this->sliderService->getAdminStats(),
            'statusCounts' => $this->sliderService->statusCounts(),
            'perPage'      => $perPage,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Slider::class);

        return view('admin.sliders.create');
    }

    public function store(StoreSliderRequest $request): RedirectResponse
    {
        $this->authorize('create', Slider::class);

        $this->sliderService->create($request->validated());

        return redirect()
            ->route('admin.sliders.index')
            ->with('success', 'Slider başarıyla oluşturuldu.');
    }

    public function edit(Slider $slider): View
    {
        $this->authorize('update', $slider);

        return view('admin.sliders.edit', [
            'slider' => $slider,
        ]);
    }

    public function update(UpdateSliderRequest $request, Slider $slider): RedirectResponse
    {
        $this->authorize('update', $slider);

        $this->sliderService->update($slider, $request->validated());

        return redirect()
            ->route('admin.sliders.index')
            ->with('success', 'Slider başarıyla güncellendi.');
    }

    public function destroy(Slider $slider): RedirectResponse
    {
        $this->authorize('delete', $slider);

        $this->sliderService->delete($slider);

        return redirect()
            ->route('admin.sliders.index')
            ->with('success', 'Slider başarıyla silindi.');
    }

    public function restore(int $id): RedirectResponse
    {
        $slider = Slider::withTrashed()->findOrFail($id);
        $this->authorize('restore', $slider);

        $this->sliderService->restore($id);

        return redirect()
            ->route('admin.sliders.index')
            ->with('success', 'Slider başarıyla geri yüklendi.');
    }
}
