<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

final class MenuPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function update(User $user, Menu $menu): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }
}
