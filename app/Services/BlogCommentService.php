<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommentStatus;
use App\Models\BlogComment;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class BlogCommentService
{
    // ── Frontend ──

    /**
     * @return Collection<int, BlogComment>
     */
    public function getApprovedComments(BlogPost $post): Collection
    {
        return $post->approvedComments()
            ->with('approvedReplies')
            ->get();
    }

    public function store(array $data, ?string $ipAddress = null): BlogComment
    {
        $data['status'] = CommentStatus::Pending->value;

        if ($ipAddress !== null) {
            $data['ip_address'] = $ipAddress;
        }

        return DB::transaction(fn (): BlogComment => BlogComment::create($data));
    }

    // ── Admin ──

    /**
     * @param array<string, mixed> $filters
     */
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = BlogComment::with(['post'])->withTrashed()->recent();

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'trashed') {
                $query->onlyTrashed();
            } else {
                $status = CommentStatus::tryFrom($filters['status']);
                if ($status !== null) {
                    $query->whereNull('deleted_at')->where('status', $status);
                }
            }
        } else {
            $query->whereNull('deleted_at');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['post_id'])) {
            $query->where('blog_post_id', $filters['post_id']);
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): BlogComment
    {
        return BlogComment::with(['post', 'parent', 'replies'])->findOrFail($id);
    }

    public function pendingCount(): int
    {
        return BlogComment::pending()->count();
    }

    /**
     * @return array<string, int>
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.blog_comments.stats', 300, function (): array {
            $counts = BlogComment::withTrashed()
                ->selectRaw('SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as total')
                ->selectRaw('SUM(CASE WHEN deleted_at IS NULL AND status = ? THEN 1 ELSE 0 END) as approved', [CommentStatus::Approved->value])
                ->selectRaw('SUM(CASE WHEN deleted_at IS NULL AND status = ? THEN 1 ELSE 0 END) as pending', [CommentStatus::Pending->value])
                ->selectRaw('SUM(CASE WHEN deleted_at IS NULL AND status = ? THEN 1 ELSE 0 END) as rejected', [CommentStatus::Rejected->value])
                ->first();

            return [
                'total'    => (int) $counts->total,
                'approved' => (int) $counts->approved,
                'pending'  => (int) $counts->pending,
                'rejected' => (int) $counts->rejected,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = BlogComment::withTrashed()
            ->selectRaw('sum(case when deleted_at is null and status = ? then 1 else 0 end) as approved', [CommentStatus::Approved->value])
            ->selectRaw('sum(case when deleted_at is null and status = ? then 1 else 0 end) as pending', [CommentStatus::Pending->value])
            ->selectRaw('sum(case when deleted_at is null and status = ? then 1 else 0 end) as rejected', [CommentStatus::Rejected->value])
            ->selectRaw('sum(case when deleted_at is not null then 1 else 0 end) as trashed')
            ->first();

        return [
            'approved' => (int) $counts->approved,
            'pending'  => (int) $counts->pending,
            'rejected' => (int) $counts->rejected,
            'trashed'  => (int) $counts->trashed,
        ];
    }

    public function approve(BlogComment $comment): void
    {
        $comment->update(['status' => CommentStatus::Approved]);
    }

    public function reject(BlogComment $comment): void
    {
        $comment->update(['status' => CommentStatus::Rejected]);
    }

    public function delete(BlogComment $comment): void
    {
        DB::transaction(fn () => $comment->delete());
    }

    public function restore(BlogComment $comment): void
    {
        DB::transaction(fn () => $comment->restore());
    }
}
