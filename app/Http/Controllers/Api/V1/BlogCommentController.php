<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBlogCommentRequest;
use App\Http\Resources\Api\V1\BlogCommentResource;
use App\Http\Responses\ApiResponse;
use App\Models\BlogPost;
use App\Services\BlogCommentService;
use App\Services\BlogService;
use Illuminate\Http\JsonResponse;

/**
 * Yazı yorumları.
 *
 * Okuma ayrı bir uçta: yazı detayına gömülseydi kırk yorumlu bir yazının
 * detayı da kırk yorum taşırdı — yorumları hiç açmayan bir ekran için bile.
 * Detay yanıtı yalnız `comment_count` veriyor, liste kullanıcı aşağı
 * kaydırdığında isteniyor.
 */
final class BlogCommentController extends Controller
{
    public function __construct(
        private readonly BlogCommentService $comments,
        private readonly BlogService $posts,
    ) {}

    /**
     * GET /api/v1/blog/posts/{slug}/comments
     *
     * Yalnız onaylanmış yorumlar, yanıtlarıyla birlikte ağaç olarak.
     */
    public function index(string $slug): JsonResponse
    {
        $post = $this->posts->findBySlug($slug);

        if ($post === null) {
            return ApiResponse::error(__('api.blog.post_not_found'), status: 404);
        }

        return ApiResponse::success(
            BlogCommentResource::collection($this->comments->getApprovedComments($post)),
        );
    }

    /**
     * POST /api/v1/blog/comments
     *
     * Yorum onay bekleyerek kaydediliyor; yanıt bunu söylüyor. "Gönderildi"
     * deyip listede göstermemek istemciyi de kullanıcıyı da yanıltırdı.
     */
    public function store(StoreBlogCommentRequest $request): JsonResponse
    {
        // Yayında olmayan bir yazıya yorum yazılamaz. Doğrulamadaki `exists`
        // kuralı satırın varlığına bakıyor, yayında olup olmadığına değil —
        // taslak bir yazının kimliği tahmin edilerek yorum bırakılabilirdi.
        BlogPost::published()->findOrFail($request->validated('blog_post_id'));

        $this->comments->store($request->validated(), $request->ip());

        return ApiResponse::created(null, __('site.blog.comment_sent'));
    }
}
