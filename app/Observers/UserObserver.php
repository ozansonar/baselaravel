<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AdminNotification;
use App\Models\User;
use App\Services\SessionRevoker;

final class UserObserver
{
    /**
     * Deactivating an account closes the sessions it already has.
     *
     * Without this the flag would only decide who may start a session, and the
     * ones already open would run until they expired. EnsureUserIsActive turns
     * such a session away on the next request; this makes sure there is no
     * session left to turn away.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged('is_active') && ! $user->is_active) {
            app(SessionRevoker::class)->revoke($user);
        }
    }

    /**
     * Same for a deleted account — soft deleted users cannot be resolved by
     * the auth guard any more, but their session rows would linger.
     */
    public function deleted(User $user): void
    {
        app(SessionRevoker::class)->revoke($user);
    }

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
