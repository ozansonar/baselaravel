<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\GalleryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTranslatedGalleryItemRequest;
use App\Http\Requests\StoreGalleryItemRequest;
use App\Http\Requests\UpdateGalleryItemRequest;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Services\GalleryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class GalleryItemController extends Controller
{
    public function __construct(
        private readonly GalleryService $galleryService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GalleryItem::class);

        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only($this->galleryService->filterKeys());

        return view('admin.gallery-items.index', [
            'items'        => $this->galleryService->paginate($perPage, $filters),
            'stats'        => $this->galleryService->getAdminStats(),
            'statusCounts' => $this->galleryService->statusCounts(),
            'types'        => GalleryType::cases(),
            'categories'   => GalleryCategory::active()->sorted()->get(),
            'perPage'      => $perPage,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GalleryItem::class);

        return view('admin.gallery-items.create', [
            'types'         => GalleryType::cases(),
            // Every language's categories, filtered per tab in the form.
            'categories'    => GalleryCategory::active()->sorted()->get(),
            'formLanguages' => $this->galleryService->formLanguages(),
        ]);
    }

    public function store(StoreTranslatedGalleryItemRequest $request): RedirectResponse
    {
        $this->authorize('create', GalleryItem::class);

        $this->galleryService->createTranslated($request->validated('translations'));

        return redirect()
            ->route('admin.gallery-items.index')
            ->with('success', 'Galeri öğesi başarıyla oluşturuldu.');
    }

    public function edit(GalleryItem $galleryItem): View
    {
        $this->authorize('update', $galleryItem);

        return view('admin.gallery-items.edit', [
            'item'          => $galleryItem->load('galleryCategory'),
            'types'         => GalleryType::cases(),
            'categories'    => GalleryCategory::active()->sorted()->get(),
            'formLanguages' => $this->galleryService->formLanguages(),
        ]);
    }

    public function update(StoreTranslatedGalleryItemRequest $request, GalleryItem $galleryItem): RedirectResponse
    {
        $this->authorize('update', $galleryItem);

        $this->galleryService->updateTranslated($galleryItem, $request->validated('translations'));

        return redirect()
            ->route('admin.gallery-items.index')
            ->with('success', 'Galeri öğesi başarıyla güncellendi.');
    }

    public function destroy(GalleryItem $galleryItem): RedirectResponse
    {
        $this->authorize('delete', $galleryItem);

        $this->galleryService->delete($galleryItem);

        return redirect()
            ->route('admin.gallery-items.index')
            ->with('success', 'Galeri öğesi başarıyla silindi.');
    }

    public function restore(int $id): RedirectResponse
    {
        $item = GalleryItem::withTrashed()->findOrFail($id);
        $this->authorize('restore', $item);

        $this->galleryService->restore($id);

        return redirect()
            ->route('admin.gallery-items.index')
            ->with('success', 'Galeri öğesi başarıyla geri yüklendi.');
    }
}
