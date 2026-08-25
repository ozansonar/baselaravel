<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Popup;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class PopupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::PopupsView);
    }

    public function view(User $user, Popup $popup): bool
    {
        return $user->hasPermission(PermissionKey::PopupsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::PopupsManage);
    }

    public function update(User $user, Popup $popup): bool
    {
        return $user->hasPermission(PermissionKey::PopupsManage);
    }

    public function delete(User $user, Popup $popup): bool
    {
        return $user->hasPermission(PermissionKey::PopupsDelete);
    }

    public function restore(User $user, Popup $popup): bool
    {
        return $user->hasPermission(PermissionKey::PopupsDelete);
    }
}
