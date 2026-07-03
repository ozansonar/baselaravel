<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogComment;
use App\Services\BlogCommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BlogCommentController extends Controller
{
    public function __construct(
        private readonly BlogCommentService $commentService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BlogComment::class);

        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only(['status', 'search', 'post_id']);

        return view('admin.blog-comments.index', [
            'comments'     => $this->commentService->paginate($perPage, $filters),
            'stats'        => $this->commentService->getAdminStats(),
            'statusCounts' => $this->commentService->statusCounts(),
            'pendingCount' => $this->commentService->pendingCount(),
            'perPage'      => $perPage,
        ]);
    }

    public function show(BlogComment $blogComment): View
    {
        $this->authorize('view', $blogComment);

        $blogComment->load(['post', 'parent', 'replies']);

        return view('admin.blog-comments.show', [
            'comment' => $blogComment,
        ]);
    }

    public function approve(BlogComment $blogComment): RedirectResponse
    {
        $this->authorize('approve', $blogComment);

        $this->commentService->approve($blogComment);

        return redirect()->route('admin.blog-comments.index')->with('success', 'Yorum onaylandı.');
    }

    public function reject(BlogComment $blogComment): RedirectResponse
    {
        $this->authorize('approve', $blogComment);

        $this->commentService->reject($blogComment);

        return redirect()->route('admin.blog-comments.index')->with('success', 'Yorum reddedildi.');
    }

    public function destroy(BlogComment $blogComment): RedirectResponse
    {
        $this->authorize('delete', $blogComment);

        $this->commentService->delete($blogComment);

        return redirect()->route('admin.blog-comments.index')->with('success', 'Yorum silindi.');
    }

    public function restore(BlogComment $blogComment): RedirectResponse
    {
        $this->authorize('delete', $blogComment);

        $this->commentService->restore($blogComment);

        return redirect()->route('admin.blog-comments.index')->with('success', 'Yorum geri yüklendi.');
    }
}
