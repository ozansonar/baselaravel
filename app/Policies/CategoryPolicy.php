<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

final class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function view(User $user, Category $category): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return $user->hasRole('admin');
    }
}
