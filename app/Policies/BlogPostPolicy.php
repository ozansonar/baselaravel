<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\BlogPost;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class BlogPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::BlogPostsView);
    }

    public function view(User $user, BlogPost $post): bool
    {
        return $user->hasPermission(PermissionKey::BlogPostsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::BlogPostsManage);
    }

    public function update(User $user, BlogPost $post): bool
    {
        return $user->hasPermission(PermissionKey::BlogPostsManage);
    }

    public function delete(User $user, BlogPost $post): bool
    {
        return $user->hasPermission(PermissionKey::BlogPostsDelete);
    }

    public function restore(User $user, BlogPost $post): bool
    {
        return $user->hasPermission(PermissionKey::BlogPostsDelete);
    }

    public function forceDelete(User $user, BlogPost $post): bool
    {
        return $user->hasPermission(PermissionKey::BlogPostsDelete);
    }
}
