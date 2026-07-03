<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Slider;
use App\Models\User;

final class SliderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function view(User $user, Slider $slider): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function update(User $user, Slider $slider): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, Slider $slider): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Slider $slider): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Slider $slider): bool
    {
        return $user->hasRole('admin');
    }
}
