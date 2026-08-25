<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\ContactMessage;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::MessagesView);
    }

    public function view(User $user, ContactMessage $contactMessage): bool
    {
        return $user->hasPermission(PermissionKey::MessagesView);
    }

    public function reply(User $user, ContactMessage $contactMessage): bool
    {
        return $user->hasPermission(PermissionKey::MessagesReply);
    }

    public function delete(User $user, ContactMessage $contactMessage): bool
    {
        return $user->hasPermission(PermissionKey::MessagesDelete);
    }

    public function restore(User $user, ContactMessage $contactMessage): bool
    {
        return $user->hasPermission(PermissionKey::MessagesDelete);
    }
}
