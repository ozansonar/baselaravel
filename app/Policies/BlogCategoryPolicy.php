<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BlogCategory;
use App\Models\User;

final class BlogCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function view(User $user, BlogCategory $category): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function update(User $user, BlogCategory $category): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, BlogCategory $category): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, BlogCategory $category): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, BlogCategory $category): bool
    {
        return $user->hasRole('admin');
    }
}
