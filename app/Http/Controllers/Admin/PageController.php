<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePageRequest;
use App\Http\Requests\UpdatePageRequest;
use App\Models\Page;
use App\Services\PageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PageController extends Controller
{
    public function __construct(
        private readonly PageService $pageService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Page::class);

        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only(['status', 'search']);

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

        return view('admin.pages.create');
    }

    public function store(StorePageRequest $request): RedirectResponse
    {
        $this->authorize('create', Page::class);

        $this->pageService->create($request->validated());

        return redirect()
            ->route('admin.pages.index')
            ->with('success', 'Sayfa başarıyla oluşturuldu.');
    }

    public function edit(Page $page): View
    {
        $this->authorize('update', $page);

        return view('admin.pages.edit', [
            'page' => $page,
        ]);
    }

    public function update(UpdatePageRequest $request, Page $page): RedirectResponse
    {
        $this->authorize('update', $page);

        $data = $request->validated();

        $teamPhotoFiles = [];
        if (isset($data['sections']['team'])) {
            foreach (array_keys($data['sections']['team']) as $index) {
                $photoFile = $request->file("sections.team.{$index}.photo_file");
                if ($photoFile) {
                    $teamPhotoFiles[$index] = $photoFile;
                }
            }
        }

        $this->pageService->update($page, $data, $teamPhotoFiles);

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
}
