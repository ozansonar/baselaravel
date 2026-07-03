<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ProductImage;
use App\Models\ProductNutrition;
use App\Models\ProductShippingItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ProductService
{
    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    public function paginate(int $perPage = 15, ?array $filters = null): LengthAwarePaginator
    {
        $query = Product::with('category')->withCount('images');

        if ($filters) {
            $this->applyFilters($query, $filters);
        }

        return $query->latest()->paginate($perPage);
    }

    public function paginateAll(int $perPage = 12): LengthAwarePaginator
    {
        return Product::active()
            ->sorted()
            ->with(['category', 'images'])
            ->withCount(['approvedReviews as reviews_count'])
            ->withAvg('approvedReviews as reviews_avg', 'rating')
            ->paginate($perPage);
    }

    public function paginateByCategory(string $categorySlug, int $perPage = 12): LengthAwarePaginator
    {
        return Product::active()
            ->sorted()
            ->whereHas('category', fn ($q) => $q->where('slug', $categorySlug))
            ->with(['category', 'images'])
            ->withCount(['approvedReviews as reviews_count'])
            ->withAvg('approvedReviews as reviews_avg', 'rating')
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, Product>
     */
    public function featured(int $limit = 8): Collection
    {
        return Cache::remember("products.featured.{$limit}", 1800, fn () =>
            Product::active()
                ->featured()
                ->sorted()
                ->with(['category', 'images'])
                ->withCount(['approvedReviews as reviews_count'])
                ->withAvg('approvedReviews as reviews_avg', 'rating')
                ->limit($limit)
                ->get(),
        );
    }

    public function findBySlug(string $slug): Product
    {
        return Product::active()
            ->where('slug', $slug)
            ->with(['category', 'images', 'nutritions', 'shippingItems', 'features', 'approvedReviews'])
            ->firstOrFail();
    }

    public function findByIdForAdmin(int $id): Product
    {
        return Product::with(['category', 'images', 'nutritions', 'shippingItems', 'features'])->findOrFail($id);
    }

    public function findByIdForAdminDetail(int $id): Product
    {
        return Product::withCount(['reviews', 'approvedReviews'])
            ->with(['category', 'images', 'nutritions', 'shippingItems', 'features', 'reviews' => fn ($q) => $q->latest()->limit(10)])
            ->findOrFail($id);
    }

    public function findById(int $id): Product
    {
        return Product::active()
            ->with(['category', 'images', 'nutritions', 'shippingItems', 'features'])
            ->findOrFail($id);
    }

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $images = $data['images'] ?? [];
            unset($data['images']);

            $nutritions = $data['nutritions'] ?? [];
            unset($data['nutritions']);

            $shippingItems = $data['shipping_items'] ?? [];
            unset($data['shipping_items']);

            $features = $data['features'] ?? [];
            unset($data['features']);

            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                $data['image'] = $this->uploadService->uploadImage($data['image'], 'products', $data['name']);
            }

            $product = Product::create($data);

            $this->syncImages($product, $images);
            $this->syncNutritions($product, $nutritions);
            $this->syncShippingItems($product, $shippingItems);
            $this->syncFeatures($product, $features);
            $this->clearCache();

            return $product->load(['category', 'images', 'nutritions', 'shippingItems', 'features']);
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $images = $data['images'] ?? null;
            unset($data['images']);

            $nutritions = $data['nutritions'] ?? null;
            unset($data['nutritions']);

            $shippingItems = $data['shipping_items'] ?? null;
            unset($data['shipping_items']);

            $features = $data['features'] ?? null;
            unset($data['features']);

            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                $data['image'] = $this->uploadService->replaceImage(
                    $data['image'],
                    'products',
                    $data['name'] ?? $product->name,
                    $product->image,
                );
            }

            $product->update($data);

            if ($images !== null) {
                $this->syncImages($product, $images);
            }

            if ($nutritions !== null) {
                $this->syncNutritions($product, $nutritions);
            }

            if ($shippingItems !== null) {
                $this->syncShippingItems($product, $shippingItems);
            }

            if ($features !== null) {
                $this->syncFeatures($product, $features);
            }

            $this->clearCache();

            return $product->refresh()->load(['category', 'images', 'nutritions', 'shippingItems', 'features']);
        });
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product->delete();
            $this->clearCache();
        });
    }

    public function restore(int $id): Product
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();
        $this->clearCache();

        return $product;
    }

    /**
     * @param array<UploadedFile> $images
     */
    private function syncImages(Product $product, array $images): void
    {
        foreach ($images as $index => $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $path = $this->uploadService->uploadImage($file, 'products/gallery', $product->name . "-{$index}");

            ProductImage::create([
                'product_id' => $product->id,
                'image'      => $path,
                'sort_order'  => $index,
            ]);
        }
    }

    /**
     * @param array<int, array{name: string, per_value?: string, daily_value?: string}> $nutritions
     */
    private function syncNutritions(Product $product, array $nutritions): void
    {
        // Soft delete existing nutritions
        $product->nutritions()->delete();

        foreach ($nutritions as $index => $item) {
            if (empty($item['name'])) {
                continue;
            }

            ProductNutrition::create([
                'product_id'  => $product->id,
                'name'        => $item['name'],
                'per_value'   => $item['per_value'] ?? null,
                'daily_value' => $item['daily_value'] ?? null,
                'sort_order'  => $index,
            ]);
        }
    }

    /**
     * @param array<int, array{type: string, content: string}> $items
     */
    private function syncShippingItems(Product $product, array $items): void
    {
        $product->shippingItems()->delete();

        foreach ($items as $index => $item) {
            if (empty($item['content'])) {
                continue;
            }

            ProductShippingItem::create([
                'product_id' => $product->id,
                'type'       => $item['type'],
                'content'    => $item['content'],
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param array<int, array{icon: string, text: string}> $features
     */
    private function syncFeatures(Product $product, array $features): void
    {
        $product->features()->delete();

        foreach ($features as $index => $item) {
            if (empty($item['text'])) {
                continue;
            }

            ProductFeature::create([
                'product_id' => $product->id,
                'icon'       => $item['icon'] ?? 'fa-solid fa-check',
                'text'       => $item['text'],
                'sort_order' => $index,
            ]);
        }
    }

    public function addImage(Product $product, UploadedFile $file): ProductImage
    {
        $path = $this->uploadService->uploadImage($file, 'products/gallery', $product->name);

        return DB::transaction(function () use ($product, $path): ProductImage {
            $maxOrder = ProductImage::where('product_id', $product->id)
                ->lockForUpdate()
                ->max('sort_order');

            $sortOrder = ($maxOrder !== null ? (int) $maxOrder : 0) + 1;

            return ProductImage::create([
                'product_id' => $product->id,
                'image'      => $path,
                'sort_order' => $sortOrder,
            ]);
        });
    }

    /**
     * @param array<int> $imageIds
     */
    public function reorderImages(Product $product, array $imageIds): void
    {
        if (empty($imageIds)) {
            return;
        }

        $cases = [];
        $bindings = [];

        foreach ($imageIds as $index => $imageId) {
            $cases[] = "WHEN id = ? THEN ?";
            $bindings[] = (int) $imageId;
            $bindings[] = $index;
        }

        $bindings[] = $product->id;
        $ids = implode(',', array_map('intval', $imageIds));

        DB::statement(
            "UPDATE product_images SET sort_order = CASE " . implode(' ', $cases) . " END WHERE id IN ({$ids}) AND product_id = ?",
            $bindings,
        );
    }

    public function deleteImage(int $imageId): void
    {
        $image = ProductImage::findOrFail($imageId);
        $this->uploadService->deleteImage($image->image);
        $image->delete();
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<Product> $query
     */
    private function applyFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['is_featured'])) {
            $query->where('is_featured', $filters['is_featured']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters): void {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('sku', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (isset($filters['stock'])) {
            match ($filters['stock']) {
                'instock'    => $query->where('stock', '>', 10),
                'lowstock'   => $query->where('stock', '>', 0)->where('stock', '<=', 10),
                'outofstock' => $query->where('stock', '<=', 0),
                default      => null,
            };
        }
    }

    /**
     * @return Collection<int, Product>
     */
    public function related(Product $product, int $limit = 4): Collection
    {
        $related = Product::active()
            ->sorted()
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->with('images')
            ->withCount(['approvedReviews as reviews_count'])
            ->withAvg('approvedReviews as reviews_avg', 'rating')
            ->limit($limit)
            ->get();

        // Same category products share the already-loaded category relation
        if ($product->relationLoaded('category')) {
            $related->each(fn (Product $p) => $p->setRelation('category', $product->category));
        }

        if ($related->count() < $limit) {
            $excludeIds = $related->pluck('id')->push($product->id)->toArray();
            $extra = Product::active()
                ->sorted()
                ->whereNotIn('id', $excludeIds)
                ->with(['category', 'images'])
                ->withCount(['approvedReviews as reviews_count'])
                ->withAvg('approvedReviews as reviews_avg', 'rating')
                ->limit($limit - $related->count())
                ->get();

            $related = $related->concat($extra);
        }

        return $related;
    }

    /**
     * @return array{total: int, active: int, low_stock: int, out_of_stock: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('products.admin_stats', 300, function (): array {
            $counts = Product::withTrashed()->selectRaw("
                SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN deleted_at IS NULL AND status = ? THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN deleted_at IS NULL AND stock > 0 AND stock <= 10 THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN deleted_at IS NULL AND stock <= 0 THEN 1 ELSE 0 END) as out_of_stock
            ", [ProductStatus::Active->value])->first();

            return [
                'total'        => (int) $counts->total,
                'active'       => (int) $counts->active,
                'low_stock'    => (int) $counts->low_stock,
                'out_of_stock' => (int) $counts->out_of_stock,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function getStatusCounts(): array
    {
        $counts = Product::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'all'      => array_sum($counts),
            'active'   => $counts[ProductStatus::Active->value] ?? 0,
            'draft'    => $counts[ProductStatus::Draft->value] ?? 0,
            'inactive' => $counts[ProductStatus::Inactive->value] ?? 0,
        ];
    }

    private function clearCache(): void
    {
        Cache::forget('products.featured.4');
        Cache::forget('products.featured.6');
        Cache::forget('products.featured.8');
        Cache::forget('products.featured.12');
        Cache::forget('products.admin_stats');
        Cache::forget('categories.active');
        // Modül 7 — yeni/güncellenen ürün sitemap'e anında yansısın.
        Cache::forget('sitemap.urls');
        Cache::forget('sitemap_page.groups');
    }
}
