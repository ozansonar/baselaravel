<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

final class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Campaign $campaign): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Campaign $campaign): bool
    {
        return $user->hasRole('admin');
    }
}
