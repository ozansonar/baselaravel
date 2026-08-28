<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGalleryBulkImageRequest;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Services\GalleryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Galeriye toplu fotoğraf yükleme.
 *
 * Tekli form bir etkinliğin yüz fotoğrafı için kullanılamıyordu: yüz kez başlık,
 * kategori, durum doldurmak gerekiyordu. Burada ortak alanlar bir kez seçiliyor,
 * dosyalar bırakılıyor ve her biri kendi isteğiyle kaydediliyor; başlıklar dosya
 * adından türeyip alttaki ızgarada düzeltilebiliyor.
 *
 * Kayıt yüklemeyle birlikte doğuyor, "kaydet"i beklemiyor — bekletilseydi
 * tarayıcı kapandığında yüz yükleme çöpe giderdi. Izgaradaki "Hepsini Kaydet"
 * yalnızca düzeltilen başlıkları yazıyor.
 *
 * Video bu ekranda yok: galeride video yüklenen bir dosya değil, YouTube/Vimeo
 * adresi. Video eklemek tekli formdan yapılıyor.
 */
final class GalleryBulkUploadController extends Controller
{
    public function __construct(
        private readonly GalleryService $galleryService,
    ) {}

    public function create(): View
    {
        $this->authorize('create', GalleryItem::class);

        return view('admin.gallery-items.bulk', [
            'categories'    => GalleryCategory::active()->sorted()->get(),
            'formLanguages' => $this->galleryService->formLanguages(),
            'maxBytes'      => StoreGalleryBulkImageRequest::maxBytes(),
        ]);
    }

    /**
     * Bırakılan tek görseli kaydeder ve ızgara satırını çizecek veriyi döner.
     */
    public function store(StoreGalleryBulkImageRequest $request): JsonResponse
    {
        $this->authorize('create', GalleryItem::class);

        $item = $this->galleryService->createFromUpload($request->file('image'), [
            'locale'              => (string) $request->input('locale'),
            'gallery_category_id' => $request->filled('gallery_category_id')
                ? (int) $request->input('gallery_category_id')
                : null,
            'is_active'  => $request->boolean('is_active'),
            'sort_order' => (int) $request->input('sort_order'),
        ]);

        return response()->json([
            'id'        => $item->id,
            'title'     => $item->title,
            'thumb_url' => upload_url($item->image, 'md'),
            'edit_url'  => route('admin.gallery-items.edit', $item),
        ]);
    }

    /**
     * Izgarada düzeltilen başlıkları tek istekte yazar.
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorize('create', GalleryItem::class);

        $validated = $request->validate([
            'titles'   => ['required', 'array', 'max:500'],
            'titles.*' => ['required', 'string', 'max:255'],
        ], [
            'titles.*.required' => 'Başlık boş bırakılamaz.',
            'titles.*.max'      => 'Başlık en fazla 255 karakter olabilir.',
        ]);

        // Anahtarlar istemciden geliyor; sayıya çevrilmeden sorguya girmesin.
        $titles = [];

        foreach ($validated['titles'] as $id => $title) {
            if (is_numeric($id)) {
                $titles[(int) $id] = trim((string) $title);
            }
        }

        return response()->json([
            'updated' => $this->galleryService->renameMany($titles),
        ]);
    }

    /**
     * Yanlış bırakılan dosyayı ızgaradan kaldırır.
     *
     * Kayıt yüklemeyle birlikte doğduğu için "kaldır" gerçekten silmek demek;
     * galeri listesindeki silme ile aynı yetki ve aynı yumuşak silme.
     */
    public function destroy(GalleryItem $galleryItem): JsonResponse
    {
        $this->authorize('delete', $galleryItem);

        $this->galleryService->delete($galleryItem);

        return response()->json(['removed' => true]);
    }
}
