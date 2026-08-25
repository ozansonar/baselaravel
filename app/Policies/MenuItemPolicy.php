<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

final class MenuItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function update(User $user, MenuItem $menuItem): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function reorder(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, MenuItem $menuItem): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, MenuItem $menuItem): bool
    {
        return $user->hasRole('admin');
    }
}
