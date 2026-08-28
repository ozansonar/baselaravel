<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ProvidesContentFileForm;
use App\Http\Controllers\Admin\Concerns\ReturnsToList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkPageRequest;
use App\Http\Requests\Admin\StoreTranslatedPageRequest;
use App\Models\Page;
use App\Services\PageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PageController extends Controller
{
    use ReturnsToList;

    use ProvidesContentFileForm;

    public function __construct(
        private readonly PageService $pageService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Page::class);

        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only($this->pageService->filterKeys());

        return view('admin.pages.index', [
            'pages'        => $this->pageService->paginate($perPage, $filters),
            'stats'        => $this->pageService->getAdminStats(),
            'statusCounts' => $this->pageService->statusCounts(),
            'perPage'      => $perPage,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Page::class);

        return view('admin.pages.create', [
            'formLanguages' => $this->pageService->formLanguages(),
        ] + $this->contentFileFormData());
    }

    public function store(StoreTranslatedPageRequest $request): RedirectResponse
    {
        $this->authorize('create', Page::class);

        $this->pageService->createTranslated($request->validated('translations'));

        return redirect()
            ->route('admin.pages.index')
            ->with('success', 'Sayfa başarıyla oluşturuldu.');
    }

    public function edit(Page $page): View
    {
        $this->authorize('update', $page);

        return view('admin.pages.edit', [
            'page'          => $page,
            'formLanguages' => $this->pageService->formLanguages(),
        ] + $this->contentFileFormData());
    }

    public function update(StoreTranslatedPageRequest $request, Page $page): RedirectResponse
    {
        $this->authorize('update', $page);

        $this->pageService->updateTranslated($page, $request->validated('translations'));

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('success', 'Sayfa başarıyla güncellendi.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $this->authorize('delete', $page);

        $this->pageService->delete($page);

        return redirect()
            ->route('admin.pages.index')
            ->with('success', 'Sayfa başarıyla silindi.');
    }

    public function restore(int $id): RedirectResponse
    {
        $page = Page::withTrashed()->findOrFail($id);
        $this->authorize('restore', $page);

        $this->pageService->restore($id);

        return redirect()
            ->route('admin.pages.index')
            ->with('success', 'Sayfa başarıyla geri yüklendi.');
    }

    /**
     * Listede seçilen sayfaleri tek seferde siler.
     *
     * Liste ekranındaki seçim kutuları buraya bağlı; önceden yalnız arayüz
     * vardı, "Sil" kutuları temizliyor ama sunucuya istek gitmiyordu.
     */
    public function bulkDestroy(BulkPageRequest $request): RedirectResponse
    {
        $this->authorize('delete', new Page());

        $silinen = $this->pageService->deleteMany($request->ids());

        return $this->backToList($request, 'admin.pages.index')->with(
            $silinen > 0 ? 'success' : 'error',
            $silinen > 0 ? "{$silinen} kayıt silindi." : 'Hiçbir kayıt silinemedi.',
        );
    }

    /**
     * Çöpteki sayfaleri tek seferde geri yükler.
     *
     * Silinmişler sekmesinde toplu silmenin karşılığı bu.
     */
    public function bulkRestore(BulkPageRequest $request): RedirectResponse
    {
        $this->authorize('restore', new Page());

        $geriYuklenen = $this->pageService->restoreMany($request->ids());

        return $this->backToList($request, 'admin.pages.index')->with(
            $geriYuklenen > 0 ? 'success' : 'error',
            $geriYuklenen > 0 ? "{$geriYuklenen} kayıt geri yüklendi." : 'Hiçbir kayıt geri yüklenemedi.',
        );
    }
}
