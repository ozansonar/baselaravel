<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RedirectStatus;
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

    private const PER_PAGE = [10, 25, 50, 100];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Redirect::class);

        $perPage = in_array((int) $request->input('per_page'), self::PER_PAGE, true)
            ? (int) $request->input('per_page')
            : 25;

        $sort = $request->string('sort')->toString();
        $sort = array_key_exists($sort, RedirectService::SORT_OPTIONS) ? $sort : '';

        $filters = [
            'search'      => (string) $request->string('search')->trim()->value(),
            'status_code' => (string) $request->string('status_code')->value(),
            'status'      => (string) $request->string('status')->value(),
            'usage'       => (string) $request->string('usage')->value(),
            'trashed'     => $request->boolean('trashed') ? '1' : '',
            'from'        => (string) $request->string('from')->value(),
            'to'          => (string) $request->string('to')->value(),
            'sort'        => $sort,
        ];

        return view('admin.redirects.index', [
            'redirects' => $this->redirectService->paginate($perPage, $filters),
            'stats'     => $this->redirectService->getAdminStats(),
            'filters'   => $filters,
            // "0" da bir seçim olabilir; boş dizeyle karıştırılmasın diye
            // süzgeç açık mı sorusu tek yerde cevaplanıyor.
            'filtered'  => collect($filters)
                ->except('sort')
                ->filter(fn (string $value): bool => $value !== '')
                ->isNotEmpty(),
            'statuses'    => RedirectStatus::cases(),
            'sortOptions' => RedirectService::SORT_OPTIONS,
            'perPage'     => $perPage,
            'perPageList' => self::PER_PAGE,
        ]);
    }

    /**
     * Ekleme ve düzenleme kendi sayfasında: pencerede açılan form, hata
     * mesajlarını ve durum koduna göre değişen alanları dar bir alana
     * sıkıştırıyor, sayfa yenilendiğinde de kayboluyordu.
     */
    public function create(): View
    {
        $this->authorize('create', Redirect::class);

        return view('admin.redirects.create', [
            'statuses' => RedirectStatus::cases(),
        ]);
    }

    public function edit(Redirect $redirect): View
    {
        $this->authorize('update', $redirect);

        return view('admin.redirects.edit', [
            'redirect' => $redirect,
            'statuses' => RedirectStatus::cases(),
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
