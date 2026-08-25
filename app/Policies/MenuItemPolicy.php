<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\MenuItem;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class MenuItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::MenusView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::MenusManage);
    }

    public function update(User $user, MenuItem $menuItem): bool
    {
        return $user->hasPermission(PermissionKey::MenusManage);
    }

    public function reorder(User $user): bool
    {
        return $user->hasPermission(PermissionKey::MenusManage);
    }

    public function delete(User $user, MenuItem $menuItem): bool
    {
        return $user->hasPermission(PermissionKey::MenusDelete);
    }

    public function restore(User $user, MenuItem $menuItem): bool
    {
        return $user->hasPermission(PermissionKey::MenusDelete);
    }
}
