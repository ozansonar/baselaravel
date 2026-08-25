<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UploadedFile;
use App\Models\User;

final class UploadedFilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function view(User $user, UploadedFile $file): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function update(User $user, UploadedFile $file): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, UploadedFile $file): bool
    {
        return $user->hasRole('admin');
    }
}
