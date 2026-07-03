<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMediaAssetRequest;
use App\Http\Requests\Admin\UploadMediaAssetRequest;
use App\Models\MediaAsset;
use App\Services\MediaLibraryPromptService;
use App\Services\MediaLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Görsel Kütüphanesi yönetim ekranları.
 *
 *   GET  /admin/media-library              → ürün grid (index)
 *   GET  /admin/media-library/{slug}       → ürün galeri (show)
 *   POST /admin/media-library/upload       → Dropzone tek dosya upload
 *   PATCH /admin/media-library/{asset}     → metadata düzenle
 *   DELETE /admin/media-library/{asset}    → soft delete
 *
 * Slug çözümlemesi:
 *   - "genel" → product_name IS NULL
 *   - diğer   → AiPromptSetting.products listesinde slug eşleşeni bul
 */
final class MediaLibraryController extends Controller
{
    public function __construct(
        private readonly MediaLibraryService $library,
        private readonly MediaLibraryPromptService $prompts,
    ) {}

    public function index(): View
    {
        $stats    = $this->library->statsByProduct();
        $products = $this->library->availableProducts();

        // Her ürün için ilk 4 thumbnail (grid kartında preview)
        $previews = [];
        foreach (array_merge($products, [null]) as $product) {
            $query = MediaAsset::query()->active();
            $query = $product === null
                ? $query->general()
                : $query->forProduct($product);

            $previews[$product ?? '__general__'] = $query
                ->latest()
                ->limit(4)
                ->get(['id', 'image_path']);
        }

        return view('admin.media-library.index', [
            'stats'    => $stats,
            'products' => $products,
            'previews' => $previews,
            'totals'   => [
                'images'    => array_sum($stats),
                'products'  => count(array_filter(
                    array_keys($stats),
                    static fn (string $k): bool => $k !== 'Genel',
                )),
            ],
        ]);
    }

    public function show(Request $request, string $productSlug): View
    {
        $resolved = $this->resolveSlug($productSlug);
        $productName = $resolved['product_name'];
        $label = $resolved['label'];

        $perPage = in_array((int) $request->query('per_page'), [12, 24, 48, 96], true)
            ? (int) $request->query('per_page')
            : 24;

        $query = MediaAsset::query()->with('creator')->latest('id');
        $query = $productName === null
            ? $query->general()
            : $query->forProduct($productName);

        $assets = $query->paginate($perPage)->withQueryString();

        return view('admin.media-library.show', [
            'productSlug'       => $productSlug,
            'productName'       => $productName,            // null = "Genel"
            'productLabel'      => $label,                  // "Günlük Taze Süt" / "Genel"
            'availableProducts' => $this->library->availableProducts(),
            'promptDataStandard' => $this->prompts->getForProduct(
                $productName,
                MediaLibraryPromptService::MODE_STANDARD,
            ),
            'promptDataBranded' => $this->prompts->getForProduct(
                $productName,
                MediaLibraryPromptService::MODE_BRANDED_OVERLAY,
            ),
            'promptDataCtaStory' => $this->prompts->getForProduct(
                $productName,
                MediaLibraryPromptService::MODE_CTA_STORY,
            ),
            'promptDataOzelGun' => $this->prompts->getForProduct(
                $productName,
                MediaLibraryPromptService::MODE_OZEL_GUN_HIKAYE,
            ),
            'assets'            => $assets,
            'perPage'           => $perPage,
            'totalActive'       => MediaAsset::query()
                ->active()
                ->when($productName === null,
                    static fn ($q) => $q->general(),
                    static fn ($q) => $q->forProduct($productName),
                )
                ->count(),
        ]);
    }

    /**
     * Dropzone.js her dosya için ayrı POST atar — tek dosya işlenir,
     * JSON döner. Frontend bu response ile preview/progress günceller.
     */
    public function upload(UploadMediaAssetRequest $request): JsonResponse
    {
        // "Genel" string'i frontend dropdown'dan gelir, null'a çevir.
        $productInput = $request->input('product_name');
        $productName = ($productInput === null || $productInput === '' || $productInput === 'Genel')
            ? null
            : (string) $productInput;

        $asset = $this->library->uploadAsset(
            file:        $request->file('image'),
            productName: $productName,
            title:       $request->filled('title') ? (string) $request->input('title') : null,
            userId:      $request->user()?->id,
        );

        return response()->json([
            'success'       => true,
            'asset_id'      => $asset->id,
            'image_url'     => upload_url($asset->image_path),
            'thumbnail_url' => upload_url($asset->image_path, 'thumb'),
            'product_name'  => $asset->product_name ?? 'Genel',
        ]);
    }

    public function update(UpdateMediaAssetRequest $request, MediaAsset $asset): RedirectResponse
    {
        $payload = $request->validated();

        // "Genel" string normalize
        if (array_key_exists('product_name', $payload)
            && ($payload['product_name'] === '' || $payload['product_name'] === 'Genel')) {
            $payload['product_name'] = null;
        }

        $asset->update($payload);

        return back()->with('success', 'Görsel güncellendi.');
    }

    public function destroy(MediaAsset $asset): RedirectResponse
    {
        $this->library->deleteAsset($asset);

        return back()->with('success', 'Görsel silindi.');
    }

    /**
     * URL slug'ından ürün adına çözümleme.
     *
     * @return array{product_name: ?string, label: string}
     *
     * @throws NotFoundHttpException Bilinmeyen slug.
     */
    private function resolveSlug(string $slug): array
    {
        if ($slug === 'genel') {
            return ['product_name' => null, 'label' => 'Genel'];
        }

        foreach ($this->library->availableProducts() as $product) {
            if (Str::slug($product) === $slug) {
                return ['product_name' => $product, 'label' => $product];
            }
        }

        throw new NotFoundHttpException("Görsel kütüphanesinde '{$slug}' adında bir ürün bulunamadı.");
    }
}
