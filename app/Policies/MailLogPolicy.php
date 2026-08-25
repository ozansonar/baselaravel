<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\MailLog;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class MailLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::MailLogsView);
    }

    public function view(User $user, MailLog $mailLog): bool
    {
        return $user->hasPermission(PermissionKey::MailLogsView);
    }

    public function resend(User $user, MailLog $mailLog): bool
    {
        return $user->hasPermission(PermissionKey::MailLogsResend);
    }
}
