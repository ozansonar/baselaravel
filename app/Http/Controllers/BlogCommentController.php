<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBlogCommentRequest;
use App\Models\BlogPost;
use App\Services\BlogCommentService;
use Illuminate\Http\JsonResponse;

final class BlogCommentController extends Controller
{
    public function __construct(
        private readonly BlogCommentService $commentService,
    ) {}

    public function store(StoreBlogCommentRequest $request): JsonResponse
    {
        BlogPost::published()->findOrFail($request->validated('blog_post_id'));

        $this->commentService->store($request->validated(), $request->ip());

        return response()->json([
            'success' => true,
            'message' => __('site.blog.comment_sent'),
        ]);
    }
}
