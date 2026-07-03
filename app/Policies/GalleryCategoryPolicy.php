<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GalleryCategory;
use App\Models\User;

final class GalleryCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function view(User $user, GalleryCategory $galleryCategory): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function update(User $user, GalleryCategory $galleryCategory): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, GalleryCategory $galleryCategory): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, GalleryCategory $galleryCategory): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, GalleryCategory $galleryCategory): bool
    {
        return $user->hasRole('admin');
    }
}
