<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Admin only. The audit trail records every other user's activity.
 */
final class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->hasRole('admin');
    }
}
