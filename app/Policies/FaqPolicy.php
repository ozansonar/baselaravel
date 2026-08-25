<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Faq;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class FaqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::FaqsView);
    }

    public function view(User $user, Faq $faq): bool
    {
        return $user->hasPermission(PermissionKey::FaqsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::FaqsManage);
    }

    public function update(User $user, Faq $faq): bool
    {
        return $user->hasPermission(PermissionKey::FaqsManage);
    }

    public function delete(User $user, Faq $faq): bool
    {
        return $user->hasPermission(PermissionKey::FaqsDelete);
    }

    public function restore(User $user, Faq $faq): bool
    {
        return $user->hasPermission(PermissionKey::FaqsDelete);
    }
}
