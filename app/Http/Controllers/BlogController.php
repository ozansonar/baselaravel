<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\BlogPostFile;
use App\Services\BlogCategoryService;
use App\Services\BlogCommentService;
use App\Services\BlogPostFileService;
use App\Services\BlogService;
use App\Services\UploadService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\View\View;

final class BlogController extends Controller
{
    public function __construct(
        private readonly BlogService $blogService,
        private readonly BlogCategoryService $blogCategoryService,
        private readonly BlogCommentService $blogCommentService,
        private readonly BlogPostFileService $blogPostFiles,
    ) {}

    public function index(): View
    {
        return view('blog.index', [
            'posts'           => $this->blogService->paginatePublished(9),
            'categories'      => $this->blogCategoryService->allActive(),
            'activeCategory'  => null,
        ]);
    }

    public function category(string $categorySlug): View
    {
        $category = $this->blogCategoryService->findBySlug($categorySlug);

        if (!$category) {
            abort(404);
        }

        return view('blog.index', [
            'posts'          => $this->blogService->paginateByCategory($category->id, 9),
            'categories'     => $this->blogCategoryService->allActive(),
            'activeCategory' => $category,
        ]);
    }

    public function show(string $categorySlug, string $slug): View
    {
        $post = $this->blogService->findBySlug($slug);

        if (!$post) {
            abort(404);
        }

        if ($post->category->slug !== $categorySlug) {
            abort(404);
        }

        $this->blogService->incrementViews($post);

        $comments = $this->blogCommentService->getApprovedComments($post);

        return view('blog.show', [
            'post'            => $post,
            'categories'      => $this->blogCategoryService->allActive(),
            'relatedPosts'    => $this->blogService->getRelatedPosts($post, 4),
            'autoLinkedBody'  => $post->body ?? '',
            'comments'        => $comments,
            'commentCount'    => $comments->count(),
            // Ekler türlerine göre gruplanmış geliyor: on beş dosyayı tek sırada
            // dizmek okunmuyor, "5 Görsel · 3 PDF · 2 Tablo" okunuyor.
            'attachmentGroups' => $this->blogPostFiles->groupByKind($post->files),
        ]);
    }

    /**
     * Eki kullanıcının yüklediği adla indirir.
     *
     * Dosya public/uploads altında olduğu için doğrudan adresinden de açılabilir;
     * bu uç iki şey ekliyor: dosya "rapor-2026-a1b2c3d4e5.xlsx" değil kullanıcının
     * verdiği adla iniyor ve yayımlanmamış bir yazının eki adresi bilinse bile
     * servis edilmiyor.
     */
    public function downloadFile(BlogPostFile $file): BinaryFileResponse
    {
        $post = $file->post;

        if ($post === null || ! BlogPost::published()->whereKey($post->id)->exists()) {
            abort(404);
        }

        $path = UploadService::basePath($file->path);

        if (! is_file($path)) {
            abort(404);
        }

        return response()->download($path, $file->original_name);
    }
}
