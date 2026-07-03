<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Faq;
use App\Models\User;

final class FaqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function view(User $user, Faq $faq): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function update(User $user, Faq $faq): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, Faq $faq): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Faq $faq): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Faq $faq): bool
    {
        return $user->hasRole('admin');
    }
}
