<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

final class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor', 'moderator']);
    }

    public function view(User $user, ContactMessage $contactMessage): bool
    {
        return $user->hasAnyRole(['admin', 'editor', 'moderator']);
    }

    public function reply(User $user, ContactMessage $contactMessage): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, ContactMessage $contactMessage): bool
    {
        return $user->hasRole('admin');
    }
}
