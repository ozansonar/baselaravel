<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\AiFaqService;
use App\Services\AiImageService;
use App\Services\CategoryService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly CategoryService $categoryService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $perPage = in_array((int) $request->input('per_page'), [12, 24, 48, 96], true) ? (int) $request->input('per_page') : 24;

        return view('admin.products.index', [
            'products' => $this->productService->paginate($perPage, $request->only([
                'status', 'category_id', 'is_featured', 'search', 'min_price', 'max_price', 'stock',
            ])),
            'categories'   => $this->categoryService->allActive(),
            'stats'        => $this->productService->getAdminStats(),
            'statusCounts' => $this->productService->getStatusCounts(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('admin.products.create', [
            'categories' => $this->categoryService->allActive(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $this->productService->create($request->validated());

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Ürün başarıyla oluşturuldu.');
    }

    public function show(Product $product): View
    {
        $this->authorize('view', $product);

        $product = $this->productService->findByIdForAdminDetail($product->id);
        $reviewStats = app(\App\Services\ProductReviewService::class)->getProductStats($product->id);
        $productFaqs = \App\Models\Faq::forProduct($product->id)->active()->sorted()->get();

        return view('admin.products.show', [
            'product'     => $product,
            'reviewStats' => $reviewStats,
            'productFaqs' => $productFaqs,
        ]);
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        $product = $this->productService->findByIdForAdmin($product->id);

        return view('admin.products.edit', [
            'product'    => $product,
            'categories' => $this->categoryService->allActive(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->productService->update($product, $request->validated());

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Ürün başarıyla güncellendi.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $this->productService->delete($product);

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Ürün başarıyla silindi.');
    }

    public function restore(int $id): RedirectResponse
    {
        $product = Product::withTrashed()->findOrFail($id);
        $this->authorize('restore', $product);

        $this->productService->restore($id);

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Ürün başarıyla geri yüklendi.');
    }

    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,webp|max:2048',
        ]);

        $image = $this->productService->addImage($product, $request->file('file'));

        return response()->json([
            'success' => true,
            'id'      => $image->id,
            'url'     => upload_url($image->image, 'thumb'),
            'name'    => $image->alt_text ?? $product->name,
        ]);
    }

    public function reorderImages(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $request->validate([
            'image_ids'   => 'required|array',
            'image_ids.*' => 'integer|exists:product_images,id',
        ]);

        $this->productService->reorderImages($product, $request->input('image_ids'));

        return response()->json(['success' => true, 'message' => 'Görsel sıralaması güncellendi.']);
    }

    public function deleteImage(int $imageId): JsonResponse
    {
        $image = \App\Models\ProductImage::findOrFail($imageId);
        $this->authorize('update', $image->product);

        $this->productService->deleteImage($imageId);

        return response()->json(['success' => true, 'message' => 'Görsel silindi.']);
    }

    public function enhanceImage(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $request->validate([
            'image_path' => ['required', 'string', 'max:500'],
            'style'      => ['required', 'string', 'in:studio,natural,social,clean,breakfast,harvest,wooden,basket,family,default'],
        ]);

        $imagePath = $request->input('image_path');

        // Path traversal koruması: sadece bu ürüne ait görsellere izin ver
        $product->loadMissing('images');
        $allowedPaths = $product->images->pluck('image')->toArray();
        if ($product->image) {
            $allowedPaths[] = $product->image;
        }

        if (! in_array($imagePath, $allowedPaths, true)) {
            return response()->json(['success' => false, 'message' => 'Bu görsel bu ürüne ait değil.'], 403);
        }

        $result = app(AiImageService::class)->enhance($product, $imagePath, $request->input('style'));

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function generateFaq(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $request->validate([
            'count' => ['required', 'integer', 'min:3', 'max:10'],
        ]);

        $result = app(AiFaqService::class)->generate($product->id, (int) $request->input('count'));

        $faqs = [];
        foreach ($result['faqs'] as $faq) {
            $faqs[] = [
                'id'       => $faq->id,
                'question' => $faq->question,
                'answer'   => $faq->answer,
            ];
        }

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'faqs'    => $faqs,
        ], $result['success'] ? 200 : 422);
    }
}
