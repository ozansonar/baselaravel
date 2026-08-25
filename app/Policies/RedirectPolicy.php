<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Redirect;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class RedirectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::RedirectsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::RedirectsManage);
    }

    public function update(User $user, Redirect $redirect): bool
    {
        return $user->hasPermission(PermissionKey::RedirectsManage);
    }

    public function delete(User $user, Redirect $redirect): bool
    {
        return $user->hasPermission(PermissionKey::RedirectsDelete);
    }

    public function restore(User $user, Redirect $redirect): bool
    {
        return $user->hasPermission(PermissionKey::RedirectsDelete);
    }
}
