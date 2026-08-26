<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Translation;
use App\Models\User;

/**
 * Interface texts appear on every page, so editing them sits with system
 * settings rather than with content editing.
 */
final class TranslationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::TranslationsView);
    }

    public function update(User $user, ?Translation $translation = null): bool
    {
        return $user->hasPermission(PermissionKey::TranslationsManage);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::TranslationsManage);
    }

    public function delete(User $user, Translation $translation): bool
    {
        return $user->hasPermission(PermissionKey::TranslationsManage);
    }
}
