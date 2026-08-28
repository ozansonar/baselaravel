<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTranslatedPopupRequest;
use App\Http\Requests\StorePopupRequest;
use App\Http\Requests\UpdatePopupRequest;
use App\Models\Popup;
use App\Services\PopupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PopupController extends Controller
{
    public function __construct(
        private readonly PopupService $popupService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Popup::class);

        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only($this->popupService->filterKeys());

        return view('admin.popups.index', [
            'popups'       => $this->popupService->paginate($perPage, $filters),
            'stats'        => $this->popupService->getAdminStats(),
            'statusCounts' => $this->popupService->statusCounts(),
            'perPage'      => $perPage,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Popup::class);

        return view('admin.popups.create', ['formLanguages' => $this->popupService->formLanguages()]);
    }

    public function store(StoreTranslatedPopupRequest $request): RedirectResponse
    {
        $this->authorize('create', Popup::class);

        $this->popupService->createTranslated($request->validated('translations'));

        return redirect()
            ->route('admin.popups.index')
            ->with('success', 'Popup başarıyla oluşturuldu.');
    }

    public function edit(Popup $popup): View
    {
        $this->authorize('update', $popup);

        return view('admin.popups.edit', [
            'popup'         => $popup,
            'formLanguages' => $this->popupService->formLanguages(),
        ]);
    }

    public function update(StoreTranslatedPopupRequest $request, Popup $popup): RedirectResponse
    {
        $this->authorize('update', $popup);

        $this->popupService->updateTranslated($popup, $request->validated('translations'));

        return redirect()
            ->route('admin.popups.index')
            ->with('success', 'Popup başarıyla güncellendi.');
    }

    public function destroy(Popup $popup): RedirectResponse
    {
        $this->authorize('delete', $popup);

        $this->popupService->delete($popup);

        return redirect()
            ->route('admin.popups.index')
            ->with('success', 'Popup başarıyla silindi.');
    }

    public function restore(int $id): RedirectResponse
    {
        $popup = Popup::withTrashed()->findOrFail($id);
        $this->authorize('restore', $popup);

        $this->popupService->restore($id);

        return redirect()
            ->route('admin.popups.index')
            ->with('success', 'Popup başarıyla geri yüklendi.');
    }
}
