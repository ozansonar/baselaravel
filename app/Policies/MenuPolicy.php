<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Menu;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class MenuPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::MenusView);
    }

    public function update(User $user, Menu $menu): bool
    {
        return $user->hasPermission(PermissionKey::MenusManage);
    }

    /**
     * Copying a menu into another language creates a menu, so it needs the same
     * permission as managing one.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::MenusManage);
    }
}
