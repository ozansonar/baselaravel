<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchService
{
    /**
     * Search products by keyword.
     *
     * @return LengthAwarePaginator<Product>
     */
    public function search(string $query, int $perPage = 12): LengthAwarePaginator
    {
        $query = trim($query);

        return Product::active()
            ->with('category')
            ->withCount(['approvedReviews as reviews_count'])
            ->withAvg('approvedReviews as reviews_avg', 'rating')
            ->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($query): void {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('short_description', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%")
                  ->orWhereHas('category', function (\Illuminate\Database\Eloquent\Builder $cq) use ($query): void {
                      $cq->where('name', 'like', "%{$query}%");
                  });
            })
            ->sorted()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Search blog posts by keyword.
     *
     * @return LengthAwarePaginator<BlogPost>
     */
    public function searchBlog(string $query, int $perPage = 6): LengthAwarePaginator
    {
        $query = trim($query);

        return BlogPost::published()
            ->with(['category', 'author'])
            ->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($query): void {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('excerpt', 'like', "%{$query}%")
                  ->orWhere('body', 'like', "%{$query}%")
                  ->orWhereHas('category', function (\Illuminate\Database\Eloquent\Builder $cq) use ($query): void {
                      $cq->where('name', 'like', "%{$query}%");
                  });
            })
            ->recent()
            ->paginate($perPage, ['*'], 'blog_page')
            ->withQueryString();
    }

    /**
     * Autocomplete suggestions (lightweight - returns limited fields).
     *
     * @return array<int, array{id: int, name: string, slug: string, price: float, image: string|null}>
     */
    public function suggest(string $query, int $limit = 5): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        return Product::active()
            ->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($query): void {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%");
            })
            ->sorted()
            ->limit($limit)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->currentPrice(),
                'image' => $product->image
                    ? UploadService::url($product->image, 'thumb')
                    : UploadService::placeholder('thumb'),
            ])
            ->all();
    }
}
