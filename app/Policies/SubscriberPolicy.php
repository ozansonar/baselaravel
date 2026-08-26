<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Subscriber;
use App\Models\User;

final class SubscriberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::SubscribersView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::SubscribersManage);
    }

    public function update(User $user, Subscriber $subscriber): bool
    {
        return $user->hasPermission(PermissionKey::SubscribersManage);
    }

    public function delete(User $user, Subscriber $subscriber): bool
    {
        return $user->hasPermission(PermissionKey::SubscribersManage);
    }
}
