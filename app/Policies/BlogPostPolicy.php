<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BlogPost;
use App\Models\User;

final class BlogPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function view(User $user, BlogPost $post): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function update(User $user, BlogPost $post): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, BlogPost $post): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, BlogPost $post): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, BlogPost $post): bool
    {
        return $user->hasRole('admin');
    }
}
