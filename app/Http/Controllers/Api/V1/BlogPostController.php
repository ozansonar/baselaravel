<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BlogPostDetailResource;
use App\Http\Resources\Api\V1\BlogPostResource;
use App\Http\Responses\ApiResponse;
use App\Services\BlogCategoryService;
use App\Services\BlogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Yayındaki blog yazıları.
 *
 * Sorgular servis katmanından geliyor ve kategori ile yazar ilişkilerini
 * baştan yüklüyor: yirmi yazılık bir sayfa aksi hâlde kırk sorgu daha atardı
 * (N+1). Aynı sorgular ön yüzde de kullanılıyor, yani iki taraf aynı yazıları
 * aynı sırayla görüyor.
 */
final class BlogPostController extends Controller
{
    use ResolvesPagination;

    public function __construct(
        private readonly BlogService $posts,
        private readonly BlogCategoryService $categories,
    ) {}

    /**
     * GET /api/v1/blog/posts
     * GET /api/v1/blog/posts?category=haberler&per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request);
        $categorySlug = $request->query('category');

        if (! is_string($categorySlug) || $categorySlug === '') {
            return ApiResponse::paginated(
                $this->posts->paginatePublished($perPage),
                BlogPostResource::class,
            );
        }

        $category = $this->categories->findBySlug($categorySlug);

        // Olmayan bir kategori boş liste değil 404 dönüyor: istemci yazdığı
        // slug'ın yanlış olduğunu "bu kategoride yazı yok" sanmamalı.
        if ($category === null) {
            return ApiResponse::error(__('api.blog.category_not_found'), status: 404);
        }

        return ApiResponse::paginated(
            $this->posts->paginateByCategory($category->id, $perPage),
            BlogPostResource::class,
        );
    }

    /**
     * GET /api/v1/blog/posts/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $post = $this->posts->findBySlug($slug);

        if ($post === null) {
            return ApiResponse::error(__('api.blog.post_not_found'), status: 404);
        }

        // Okunma sayacı ön yüzdekiyle aynı yerden artıyor: uygulamadan okunan
        // yazı da okunmuş sayılmalı, yoksa panelde görünen sayı zamanla
        // gerçeğin yarısı olur.
        $this->posts->incrementViews($post);

        return ApiResponse::success(BlogPostDetailResource::make($post));
    }
}
