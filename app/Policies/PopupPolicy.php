<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Popup;
use App\Models\User;

final class PopupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function view(User $user, Popup $popup): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function update(User $user, Popup $popup): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, Popup $popup): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Popup $popup): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Popup $popup): bool
    {
        return $user->hasRole('admin');
    }
}
