<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;

final class UserObserver
{
    public function deleting(User $user): void
    {
        if ($user->isForceDeleting()) {
            $user->addresses()->forceDelete();
            return;
        }

        $user->addresses()->each(fn ($address) => $address->delete());
    }

    public function restoring(User $user): void
    {
        $user->addresses()->withTrashed()->restore();
    }
}
