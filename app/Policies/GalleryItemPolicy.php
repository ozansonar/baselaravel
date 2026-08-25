<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\GalleryItem;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class GalleryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::GalleryView);
    }

    public function view(User $user, GalleryItem $item): bool
    {
        return $user->hasPermission(PermissionKey::GalleryView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::GalleryManage);
    }

    public function update(User $user, GalleryItem $item): bool
    {
        return $user->hasPermission(PermissionKey::GalleryManage);
    }

    public function delete(User $user, GalleryItem $item): bool
    {
        return $user->hasPermission(PermissionKey::GalleryDelete);
    }

    public function restore(User $user, GalleryItem $item): bool
    {
        return $user->hasPermission(PermissionKey::GalleryDelete);
    }
}
