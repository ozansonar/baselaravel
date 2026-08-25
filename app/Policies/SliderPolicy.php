<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Slider;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class SliderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::SlidersView);
    }

    public function view(User $user, Slider $slider): bool
    {
        return $user->hasPermission(PermissionKey::SlidersView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::SlidersManage);
    }

    public function update(User $user, Slider $slider): bool
    {
        return $user->hasPermission(PermissionKey::SlidersManage);
    }

    public function delete(User $user, Slider $slider): bool
    {
        return $user->hasPermission(PermissionKey::SlidersDelete);
    }

    public function restore(User $user, Slider $slider): bool
    {
        return $user->hasPermission(PermissionKey::SlidersDelete);
    }
}
