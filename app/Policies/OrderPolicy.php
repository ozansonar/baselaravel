<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor', 'moderator']);
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->hasAnyRole(['admin', 'editor', 'moderator'])) {
            return true;
        }

        return $user->id === $order->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Order $order): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return $user->hasRole('admin');
    }
}
