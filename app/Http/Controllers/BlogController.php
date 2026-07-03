<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BlogCategoryService;
use App\Services\BlogCommentService;
use App\Services\BlogService;
use Illuminate\View\View;

final class BlogController extends Controller
{
    public function __construct(
        private readonly BlogService $blogService,
        private readonly BlogCategoryService $blogCategoryService,
        private readonly BlogCommentService $blogCommentService,
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
        ]);
    }
}
