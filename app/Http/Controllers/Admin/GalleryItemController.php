<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\GalleryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkGalleryItemRequest;
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

        // Görünüm tercihi adreste taşınıyor: sayfa değiştirince, süzgeç
        // uygulayınca ve bağlantı paylaşılınca da korunuyor. Tarayıcı
        // hafızasına yazılsaydı listenin geri kalanı adresten, görünüm başka
        // yerden gelirdi.
        $viewMode = $request->input('view') === 'grid' ? 'grid' : 'table';

        return view('admin.gallery-items.index', [
            'items'        => $this->galleryService->paginate($perPage, $filters),
            'stats'        => $this->galleryService->getAdminStats(),
            'statusCounts' => $this->galleryService->statusCounts(),
            'types'        => GalleryType::cases(),
            'categories'   => GalleryCategory::active()->sorted()->get(),
            'perPage'      => $perPage,
            'viewMode'     => $viewMode,
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

    /**
     * Listede seçilen öğeleri tek seferde siler.
     *
     * Yüz fotoğraflık bir yüklemeyi tek tek silmek mümkün değil; toplu
     * yükleme varken toplu silmenin olmaması listeyi tek yönlü bırakıyordu.
     */
    public function bulkDestroy(BulkGalleryItemRequest $request): RedirectResponse
    {
        $this->authorize('delete', new GalleryItem());

        $silinen = $this->galleryService->deleteMany($request->ids());

        return $this->backToList($request)->with(
            $silinen > 0 ? 'success' : 'error',
            $silinen > 0 ? "{$silinen} galeri öğesi silindi." : 'Hiçbir öğe silinemedi.',
        );
    }

    /**
     * Çöpteki öğeleri tek seferde geri yükler.
     *
     * Silinmişler sekmesinde toplu silmenin karşılığı bu: orada "sil" demenin
     * anlamı yok, satırlar zaten silinmiş durumda.
     */
    public function bulkRestore(BulkGalleryItemRequest $request): RedirectResponse
    {
        $this->authorize('restore', new GalleryItem());

        $geriYuklenen = $this->galleryService->restoreMany($request->ids());

        return $this->backToList($request)->with(
            $geriYuklenen > 0 ? 'success' : 'error',
            $geriYuklenen > 0 ? "{$geriYuklenen} galeri öğesi geri yüklendi." : 'Hiçbir öğe geri yüklenemedi.',
        );
    }

    /**
     * Kullanıcı hangi süzgeç ve sayfadaysa oraya döndürür: toplu işlemden
     * sonra listenin başına düşmek, uzun listede yeri kaybettiriyor.
     */
    private function backToList(Request $request): RedirectResponse
    {
        $query = $request->only(['status', 'type', 'category', 'search', 'per_page', 'page']);

        return redirect()->route('admin.gallery-items.index', array_filter($query, static fn ($value): bool => $value !== null && $value !== ''));
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
