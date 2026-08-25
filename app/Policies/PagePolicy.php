<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Page;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::PagesView);
    }

    public function view(User $user, Page $page): bool
    {
        return $user->hasPermission(PermissionKey::PagesView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::PagesManage);
    }

    public function update(User $user, Page $page): bool
    {
        return $user->hasPermission(PermissionKey::PagesManage);
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->hasPermission(PermissionKey::PagesDelete);
    }

    public function restore(User $user, Page $page): bool
    {
        return $user->hasPermission(PermissionKey::PagesDelete);
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return $user->hasPermission(PermissionKey::PagesDelete);
    }
}
