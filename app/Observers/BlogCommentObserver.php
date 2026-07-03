<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BlogComment;

final class BlogCommentObserver
{
    public function deleting(BlogComment $comment): void
    {
        if ($comment->isForceDeleting()) {
            $comment->replies()->forceDelete();
            return;
        }

        $comment->replies()->each(fn (BlogComment $reply) => $reply->delete());
    }

    public function restoring(BlogComment $comment): void
    {
        $comment->replies()->withTrashed()->restore();
    }
}
