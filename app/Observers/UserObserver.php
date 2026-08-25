<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AdminNotification;
use App\Models\User;

final class UserObserver
{
    /**
     * Cascade is handled here rather than by foreign keys.
     *
     * role_user and admin_notifications are declared restrictOnDelete, so both
     * have to be cleared before the database will remove a user for good. A
     * soft delete leaves them in place, which is what lets a restore put the
     * user back exactly as they were.
     *
     * blog_posts.user_id is nullOnDelete on purpose: removing an author must
     * not remove their content.
     */
    public function deleting(User $user): void
    {
        if (! $user->isForceDeleting()) {
            return;
        }

        $user->roles()->detach();

        $user->adminNotifications()
            ->withTrashed()
            ->each(fn (AdminNotification $notification) => $notification->forceDelete());
    }
}
