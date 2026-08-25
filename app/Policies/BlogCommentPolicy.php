<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\BlogComment;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class BlogCommentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::CommentsView);
    }

    public function view(User $user, BlogComment $comment): bool
    {
        return $user->hasPermission(PermissionKey::CommentsView);
    }

    public function approve(User $user, BlogComment $comment): bool
    {
        return $user->hasPermission(PermissionKey::CommentsModerate);
    }

    public function delete(User $user, BlogComment $comment): bool
    {
        return $user->hasPermission(PermissionKey::CommentsDelete);
    }

    public function restore(User $user, BlogComment $comment): bool
    {
        return $user->hasPermission(PermissionKey::CommentsDelete);
    }
}
