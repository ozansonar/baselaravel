<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Role;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::RolesView);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission(PermissionKey::RolesView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::RolesManage);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission(PermissionKey::RolesManage);
    }

    /**
     * Saving the permission matrix is about roles as a whole, not one role, so
     * it needs an ability that takes no model instance.
     */
    public function managePermissions(User $user): bool
    {
        return $user->hasPermission(PermissionKey::RolesManage);
    }

    /**
     * Class-level counterpart of delete(), for markup that is rendered once
     * rather than per role (the confirmation modal).
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::RolesDelete);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission(PermissionKey::RolesDelete);
    }
}
