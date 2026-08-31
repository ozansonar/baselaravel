<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTranslatedGalleryCategoryRequest;
use App\Models\GalleryCategory;
use App\Services\GalleryCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class GalleryCategoryController extends Controller
{
    public function __construct(
        private readonly GalleryCategoryService $galleryCategoryService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GalleryCategory::class);

        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only($this->galleryCategoryService->filterKeys());

        return view('admin.gallery-categories.index', [
            'categories'   => $this->galleryCategoryService->paginate($perPage, $filters),
            'stats'        => $this->galleryCategoryService->getAdminStats(),
            'statusCounts' => $this->galleryCategoryService->statusCounts(),
            'perPage'      => $perPage,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GalleryCategory::class);

        return view('admin.gallery-categories.create', ['formLanguages' => $this->galleryCategoryService->formLanguages()]);
    }

    public function store(StoreTranslatedGalleryCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', GalleryCategory::class);

        $this->galleryCategoryService->createTranslated($request->validated('translations'));

        return redirect()
            ->route('admin.gallery-categories.index')
            ->with('success', 'Galeri kategorisi başarıyla oluşturuldu.');
    }

    public function edit(GalleryCategory $galleryCategory): View
    {
        $this->authorize('update', $galleryCategory);

        return view('admin.gallery-categories.edit', [
            'category'      => $galleryCategory,
            'formLanguages' => $this->galleryCategoryService->formLanguages(),
        ]);
    }

    public function update(StoreTranslatedGalleryCategoryRequest $request, GalleryCategory $galleryCategory): RedirectResponse
    {
        $this->authorize('update', $galleryCategory);

        $this->galleryCategoryService->updateTranslated($galleryCategory, $request->validated('translations'));

        return redirect()
            ->route('admin.gallery-categories.index')
            ->with('success', 'Galeri kategorisi başarıyla güncellendi.');
    }

    public function destroy(GalleryCategory $galleryCategory): RedirectResponse
    {
        $this->authorize('delete', $galleryCategory);

        $this->galleryCategoryService->delete($galleryCategory);

        return redirect()
            ->route('admin.gallery-categories.index')
            ->with('success', 'Galeri kategorisi başarıyla silindi.');
    }

    public function restore(int $id): RedirectResponse
    {
        $category = GalleryCategory::withTrashed()->findOrFail($id);
        $this->authorize('restore', $category);

        $this->galleryCategoryService->restore($id);

        return redirect()
            ->route('admin.gallery-categories.index')
            ->with('success', 'Galeri kategorisi başarıyla geri yüklendi.');
    }
}
