<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Language;
use App\Models\User;

/**
 * Languages shape the whole site, so managing them sits with system settings
 * rather than with content editing.
 */
final class LanguagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::LanguagesView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::LanguagesManage);
    }

    public function update(User $user, Language $language): bool
    {
        return $user->hasPermission(PermissionKey::LanguagesManage);
    }

    public function delete(User $user, Language $language): bool
    {
        return $user->hasPermission(PermissionKey::LanguagesManage);
    }
}
