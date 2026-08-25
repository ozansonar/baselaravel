<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AdminNotification;
use App\Models\User;

/**
 * The notification centre is shared by everyone who can reach the panel.
 */
final class AdminNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor', 'moderator']);
    }

    public function update(User $user, AdminNotification $notification): bool
    {
        return $user->hasAnyRole(['admin', 'editor', 'moderator']);
    }

    public function delete(User $user, AdminNotification $notification): bool
    {
        return $user->hasRole('admin');
    }
}
