<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\BlogCategory;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class BlogCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::BlogCategoriesView);
    }

    public function view(User $user, BlogCategory $category): bool
    {
        return $user->hasPermission(PermissionKey::BlogCategoriesView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::BlogCategoriesManage);
    }

    public function update(User $user, BlogCategory $category): bool
    {
        return $user->hasPermission(PermissionKey::BlogCategoriesManage);
    }

    public function delete(User $user, BlogCategory $category): bool
    {
        return $user->hasPermission(PermissionKey::BlogCategoriesDelete);
    }

    public function restore(User $user, BlogCategory $category): bool
    {
        return $user->hasPermission(PermissionKey::BlogCategoriesDelete);
    }
}
