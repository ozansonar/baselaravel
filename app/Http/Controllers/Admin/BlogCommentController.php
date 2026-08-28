<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ReturnsToList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkBlogCommentRequest;
use App\Models\BlogComment;
use App\Services\BlogCommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BlogCommentController extends Controller
{
    use ReturnsToList;

    public function __construct(
        private readonly BlogCommentService $commentService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BlogComment::class);

        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only($this->commentService->filterKeys());

        return view('admin.blog-comments.index', [
            'comments'     => $this->commentService->paginate($perPage, $filters),
            'stats'        => $this->commentService->getAdminStats(),
            'statusCounts' => $this->commentService->statusCounts(),
            'pendingCount' => $this->commentService->pendingCount(),
            'perPage'      => $perPage,
            // Süzgeç listesi yalnız yorumu olan yazıları gösteriyor: yorumu
            // olmayan yüzlerce yazı arasında seçim yapmak süzgeci
            // kullanılmaz hâle getiriyordu.
            'posts'        => $this->commentService->commentedPosts(),
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

    /**
     * Seçilen yorumları tek seferde onaylar.
     *
     * Zaten onaylı olanlar sayıya girmiyor: hiçbiri değişmemişken
     * "5 yorum onaylandı" demek olan biteni yanlış anlatırdı.
     */
    public function bulkApprove(BulkBlogCommentRequest $request): RedirectResponse
    {
        $this->authorize('approve', new BlogComment());

        $onaylanan = $this->commentService->approveMany($request->ids());

        return $this->backToList($request, 'admin.blog-comments.index')->with(
            $onaylanan > 0 ? 'success' : 'info',
            $onaylanan > 0 ? "{$onaylanan} yorum onaylandı." : 'Seçilen yorumlar zaten onaylıydı.',
        );
    }

    /**
     * Seçilen yorumları tek seferde siler.
     */
    public function bulkDestroy(BulkBlogCommentRequest $request): RedirectResponse
    {
        $this->authorize('delete', new BlogComment());

        $silinen = $this->commentService->deleteMany($request->ids());

        return $this->backToList($request, 'admin.blog-comments.index')->with(
            $silinen > 0 ? 'success' : 'error',
            $silinen > 0 ? "{$silinen} yorum silindi." : 'Hiçbir yorum silinemedi.',
        );
    }

    /**
     * Çöpteki yorumları tek seferde geri yükler.
     */
    public function bulkRestore(BulkBlogCommentRequest $request): RedirectResponse
    {
        $this->authorize('restore', new BlogComment());

        $geriYuklenen = $this->commentService->restoreMany($request->ids());

        return $this->backToList($request, 'admin.blog-comments.index')->with(
            $geriYuklenen > 0 ? 'success' : 'error',
            $geriYuklenen > 0 ? "{$geriYuklenen} yorum geri yüklendi." : 'Hiçbir yorum geri yüklenemedi.',
        );
    }
}
