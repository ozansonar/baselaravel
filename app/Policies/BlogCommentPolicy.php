<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BlogComment;
use App\Models\User;

final class BlogCommentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function view(User $user, BlogComment $comment): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function approve(User $user, BlogComment $comment): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, BlogComment $comment): bool
    {
        return $user->hasRole('admin');
    }
}
