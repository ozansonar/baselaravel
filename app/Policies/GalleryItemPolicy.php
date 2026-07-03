<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GalleryItem;
use App\Models\User;

final class GalleryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function view(User $user, GalleryItem $galleryItem): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function update(User $user, GalleryItem $galleryItem): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, GalleryItem $galleryItem): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, GalleryItem $galleryItem): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, GalleryItem $galleryItem): bool
    {
        return $user->hasRole('admin');
    }
}
