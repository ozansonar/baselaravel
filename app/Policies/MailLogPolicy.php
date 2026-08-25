<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MailLog;
use App\Models\User;

/**
 * Admin only. Mail log bodies contain the rendered contents of outgoing mail,
 * including password reset links.
 */
final class MailLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, MailLog $mailLog): bool
    {
        return $user->hasRole('admin');
    }

    public function resend(User $user, MailLog $mailLog): bool
    {
        return $user->hasRole('admin');
    }
}
