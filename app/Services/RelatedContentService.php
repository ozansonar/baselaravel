<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\ProductTopic;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class RelatedContentService
{
    private const int CACHE_TTL = 3600;

    private const array TURKISH_STOP_WORDS = [
        'bir', 'bu', 'da', 'de', 'den', 'ile', 'için', 'gibi', 'kadar',
        'daha', 'çok', 'en', 'hem', 'ama', 'veya', 'ya', 'ki', 'ne',
        'nasıl', 'neden', 'hangi', 'her', 'tüm', 'olan', 'olarak',
        'sonra', 'önce', 'arasında', 'üzerinde', 'altında', 'yanında',
        've', 'ise', 'biz', 'siz', 'var', 'yok', 'değil',
        'hakkında', 'ayrıca', 'ancak', 'fakat', 'böyle', 'şu',
        'diğer', 'bazı', 'sadece', 'bile', 'artık', 'zaten', 'yine',
    ];

    /**
     * @return Collection<int, BlogPost>
     */
    public function getRelatedPosts(BlogPost $post, int $limit = 4): Collection
    {
        $cacheKey = "related_posts.{$post->id}.{$limit}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($post, $limit): Collection {
            $keywords = $this->extractKeywords($post->title);

            if (empty($keywords)) {
                return BlogPost::with(['category', 'author'])
                    ->published()
                    ->where('id', '!=', $post->id)
                    ->recent()
                    ->limit($limit)
                    ->get();
            }

            [$relevanceExpr, $bindings] = $this->buildRelevanceScore($keywords, $post->blog_category_id);

            return BlogPost::with(['category', 'author'])
                ->published()
                ->where('id', '!=', $post->id)
                ->selectRaw("blog_posts.*, ({$relevanceExpr}) as relevance_score", $bindings)
                ->orderByDesc('relevance_score')
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * @return Collection<int, Product>
     */
    public function getRelatedProducts(BlogPost $post, int $limit = 3): Collection
    {
        $cacheKey = "related_products.{$post->id}.{$limit}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($post, $limit): Collection {
            $topicLinkedProductIds = ProductTopic::query()
                ->whereNotNull('blog_post_id')
                ->where('blog_post_id', $post->id)
                ->pluck('product_id');

            $keywords = $this->extractKeywords($post->title);

            $query = Product::active()
                ->select('products.*')
                ->with('category:id,name,slug');

            if ($topicLinkedProductIds->isNotEmpty() && !empty($keywords)) {
                $query->where(function ($q) use ($topicLinkedProductIds, $keywords): void {
                    $q->whereIn('products.id', $topicLinkedProductIds);
                    foreach ($keywords as $keyword) {
                        $q->orWhere('products.name', 'like', "%{$keyword}%");
                    }
                });

                $placeholders = implode(',', array_fill(0, $topicLinkedProductIds->count(), '?'));
                $query->orderByRaw(
                    "CASE WHEN products.id IN ({$placeholders}) THEN 0 ELSE 1 END",
                    $topicLinkedProductIds->all(),
                );
            } elseif ($topicLinkedProductIds->isNotEmpty()) {
                $query->whereIn('products.id', $topicLinkedProductIds);
            } elseif (!empty($keywords)) {
                $query->where(function ($q) use ($keywords): void {
                    foreach ($keywords as $keyword) {
                        $q->orWhere('products.name', 'like', "%{$keyword}%");
                    }
                });
            } else {
                return new Collection();
            }

            return $query->limit($limit)->get();
        });
    }

    /**
     * @return Collection<int, BlogPost>
     */
    public function getRelatedBlogPostsForProduct(Product $product, int $limit = 6): Collection
    {
        $cacheKey = "related_blog_posts_product.{$product->id}.{$limit}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($product, $limit): Collection {
            $topicLinkedIds = ProductTopic::query()
                ->where('product_id', $product->id)
                ->whereNotNull('blog_post_id')
                ->pluck('blog_post_id');

            return BlogPost::query()
                ->select('id', 'title', 'slug', 'blog_category_id', 'image', 'excerpt', 'published_at')
                ->published()
                ->where(function ($q) use ($topicLinkedIds, $product): void {
                    $q->whereIn('id', $topicLinkedIds)
                      ->orWhere('title', 'like', '%' . $product->name . '%');
                })
                ->with('category:id,slug,name')
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * @return list<string>
     */
    public function extractKeywords(string $title): array
    {
        $title = mb_strtolower($title, 'UTF-8');
        $title = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title) ?? $title;
        $words = preg_split('/\s+/', trim($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $words,
            fn (string $word): bool => mb_strlen($word, 'UTF-8') >= 3
                && !in_array($word, self::TURKISH_STOP_WORDS, true),
        ));
    }

    public function clearCacheForPost(int $postId): void
    {
        foreach ([3, 4, 6] as $limit) {
            Cache::forget("related_posts.{$postId}.{$limit}");
            Cache::forget("related_products.{$postId}.{$limit}");
        }
    }

    public function clearCacheForProduct(int $productId): void
    {
        foreach ([3, 4, 6] as $limit) {
            Cache::forget("related_blog_posts_product.{$productId}.{$limit}");
        }
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildRelevanceScore(array $keywords, ?int $categoryId): array
    {
        $cases    = [];
        $bindings = [];

        if ($categoryId !== null) {
            $cases[]    = 'CASE WHEN blog_posts.blog_category_id = ? THEN 10 ELSE 0 END';
            $bindings[] = $categoryId;
        }

        foreach ($keywords as $keyword) {
            $cases[]    = 'CASE WHEN blog_posts.title LIKE ? THEN 3 ELSE 0 END';
            $bindings[] = "%{$keyword}%";
            $cases[]    = 'CASE WHEN blog_posts.excerpt LIKE ? THEN 1 ELSE 0 END';
            $bindings[] = "%{$keyword}%";
        }

        if (empty($cases)) {
            return ['0', []];
        }

        return [implode(' + ', $cases), $bindings];
    }
}
