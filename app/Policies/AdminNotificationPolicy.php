<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\AdminNotification;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class AdminNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::NotificationsView);
    }

    public function update(User $user, AdminNotification $notification): bool
    {
        return $user->hasPermission(PermissionKey::NotificationsManage);
    }

    public function delete(User $user, AdminNotification $notification): bool
    {
        return $user->hasPermission(PermissionKey::NotificationsDelete);
    }
}
