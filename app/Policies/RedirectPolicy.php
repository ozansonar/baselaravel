<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Redirect;
use App\Models\User;

/**
 * Admin only. A redirect target is not constrained to the local host, so
 * whoever manages redirects can send site traffic to an external address.
 */
final class RedirectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Redirect $redirect): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Redirect $redirect): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Redirect $redirect): bool
    {
        return $user->hasRole('admin');
    }
}
