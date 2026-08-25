<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRedirectRequest;
use App\Http\Requests\Admin\UpdateRedirectRequest;
use App\Models\Redirect;
use App\Services\RedirectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class RedirectController extends Controller
{
    public function __construct(
        private readonly RedirectService $redirectService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Redirect::class);

        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page')
            : 25;

        return view('admin.redirects.index', [
            'redirects' => $this->redirectService->paginate($perPage, $request->only(['search', 'status_code', 'status'])),
            'stats'     => $this->redirectService->getAdminStats(),
            'perPage'   => $perPage,
        ]);
    }

    public function store(StoreRedirectRequest $request): RedirectResponse
    {
        $this->authorize('create', Redirect::class);

        $this->redirectService->create($request->validated());

        return redirect()
            ->route('admin.redirects.index')
            ->with('success', 'Yönlendirme başarıyla oluşturuldu.');
    }

    public function update(UpdateRedirectRequest $request, Redirect $redirect): RedirectResponse
    {
        $this->authorize('update', $redirect);

        $this->redirectService->update($redirect, $request->validated());

        return redirect()
            ->route('admin.redirects.index')
            ->with('success', 'Yönlendirme başarıyla güncellendi.');
    }

    public function destroy(Redirect $redirect): RedirectResponse
    {
        $this->authorize('delete', $redirect);

        $this->redirectService->delete($redirect);

        return redirect()
            ->route('admin.redirects.index')
            ->with('success', 'Yönlendirme başarıyla silindi.');
    }

    public function restore(int $id): RedirectResponse
    {
        $this->authorize('restore', Redirect::withTrashed()->findOrFail($id));

        $this->redirectService->restore($id);

        return redirect()
            ->route('admin.redirects.index')
            ->with('success', 'Yönlendirme başarıyla geri yüklendi.');
    }

    public function toggleActive(Redirect $redirect): JsonResponse
    {
        $this->authorize('update', $redirect);

        $this->redirectService->toggleActive($redirect);

        return response()->json(['success' => true, 'is_active' => $redirect->fresh()->is_active]);
    }
}
