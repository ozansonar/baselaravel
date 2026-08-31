<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Signs a user out of every session they are currently holding.
 *
 * Deactivating an account used to take effect only at the next login, so the
 * sessions already open kept working — up to the session lifetime, and far
 * longer with a "remember me" cookie. Two things close that window and both
 * are needed:
 *
 *   - EnsureUserIsActive rejects the session on the way in, which is the only
 *     defence that works whatever the session driver is;
 *   - this class removes the sessions at the source, so the next request
 *     arrives as a guest even if the middleware were taken out. It also drops
 *     the remember token, otherwise the cookie would silently re-authenticate
 *     the visitor and the middleware would have to log them out on every
 *     single request.
 *
 * Sessions can only be found by user when they live in the database. With any
 * other driver this half is a no-op and the middleware carries the whole job,
 * which is why the check exists in both places rather than only here.
 */
final class SessionRevoker
{
    public function revoke(User $user): void
    {
        $this->deleteSessions([$user->getKey()]);

        // A force delete has already taken the row away and left exists at
        // false; saving now would insert the user straight back.
        if (! $user->exists) {
            return;
        }

        // saveQuietly: this runs from inside UserObserver::updated(), and a
        // regular save would re-enter the observer that called it.
        $user->forceFill(['remember_token' => null])->saveQuietly();
    }

    /**
     * Same thing for a list of ids, used by the bulk actions.
     *
     * Bulk delete goes through the query builder and never wakes a model
     * event, so the observer cannot help there.
     *
     * @param list<int> $userIds
     */
    public function revokeMany(array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $this->deleteSessions($userIds);

        // withTrashed: the rows this is called for have usually just been soft
        // deleted, and the default scope would skip every one of them.
        User::withTrashed()
            ->whereIn('id', $userIds)
            ->update(['remember_token' => null]);
    }

    /**
     * @param list<int> $userIds
     */
    private function deleteSessions(array $userIds): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->whereIn('user_id', $userIds)
            ->delete();
    }
}
