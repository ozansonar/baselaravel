<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ReturnsToList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkSliderRequest;
use App\Http\Requests\Admin\StoreTranslatedSliderRequest;
use App\Http\Requests\StoreSliderRequest;
use App\Http\Requests\UpdateSliderRequest;
use App\Models\Slider;
use App\Services\SliderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SliderController extends Controller
{
    use ReturnsToList;

    public function __construct(
        private readonly SliderService $sliderService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Slider::class);

        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only($this->sliderService->filterKeys());

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

        return view('admin.sliders.create', ['formLanguages' => $this->sliderService->formLanguages()]);
    }

    public function store(StoreTranslatedSliderRequest $request): RedirectResponse
    {
        $this->authorize('create', Slider::class);

        $this->sliderService->createTranslated($request->validated('translations'));

        return redirect()
            ->route('admin.sliders.index')
            ->with('success', 'Slider başarıyla oluşturuldu.');
    }

    public function edit(Slider $slider): View
    {
        $this->authorize('update', $slider);

        return view('admin.sliders.edit', [
            'slider'        => $slider,
            'formLanguages' => $this->sliderService->formLanguages(),
        ]);
    }

    public function update(StoreTranslatedSliderRequest $request, Slider $slider): RedirectResponse
    {
        $this->authorize('update', $slider);

        $this->sliderService->updateTranslated($slider, $request->validated('translations'));

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

    /**
     * Listede seçilen sliderleri tek seferde siler.
     *
     * Liste ekranındaki seçim kutuları buraya bağlı; önceden yalnız arayüz
     * vardı, "Sil" kutuları temizliyor ama sunucuya istek gitmiyordu.
     */
    public function bulkDestroy(BulkSliderRequest $request): RedirectResponse
    {
        $this->authorize('delete', new Slider());

        $silinen = $this->sliderService->deleteMany($request->ids());

        return $this->backToList($request, 'admin.sliders.index')->with(
            $silinen > 0 ? 'success' : 'error',
            $silinen > 0 ? "{$silinen} kayıt silindi." : 'Hiçbir kayıt silinemedi.',
        );
    }

    /**
     * Çöpteki sliderleri tek seferde geri yükler.
     *
     * Silinmişler sekmesinde toplu silmenin karşılığı bu.
     */
    public function bulkRestore(BulkSliderRequest $request): RedirectResponse
    {
        $this->authorize('restore', new Slider());

        $geriYuklenen = $this->sliderService->restoreMany($request->ids());

        return $this->backToList($request, 'admin.sliders.index')->with(
            $geriYuklenen > 0 ? 'success' : 'error',
            $geriYuklenen > 0 ? "{$geriYuklenen} kayıt geri yüklendi." : 'Hiçbir kayıt geri yüklenemedi.',
        );
    }
}
