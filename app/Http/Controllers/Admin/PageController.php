<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTranslatedPageRequest;
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
        ]);
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
        ]);
    }

    public function update(StoreTranslatedPageRequest $request, Page $page): RedirectResponse
    {
        $this->authorize('update', $page);

        $translations = $request->validated('translations');

        // Uploads never survive validation, so team photos are pulled straight
        // off the request and merged back into their own language block.
        foreach ($translations as $locale => $fields) {
            foreach (array_keys($fields['sections']['team'] ?? []) as $index) {
                $photo = $request->file("translations.{$locale}.sections.team.{$index}.photo_file");

                if ($photo !== null) {
                    $translations[$locale]['sections']['team'][$index]['photo_file'] = $photo;
                }
            }
        }

        $this->pageService->updateTranslated($page, $translations);

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
