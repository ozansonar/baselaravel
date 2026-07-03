<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

final class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function view(User $user, Page $page): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function update(User $user, Page $page): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Page $page): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return $user->hasRole('admin');
    }
}
