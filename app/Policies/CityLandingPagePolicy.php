<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CityLandingPage;
use App\Models\User;

final class CityLandingPagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function view(User $user, CityLandingPage $page): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function update(User $user, CityLandingPage $page): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, CityLandingPage $page): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, CityLandingPage $page): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, CityLandingPage $page): bool
    {
        return $user->hasRole('admin');
    }
}
