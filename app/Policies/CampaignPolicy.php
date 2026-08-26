<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Campaign;
use App\Models\User;

final class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::CampaignsView);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(PermissionKey::CampaignsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::CampaignsManage);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(PermissionKey::CampaignsManage);
    }

    /**
     * Starting a send reaches every address on the list and cannot be undone,
     * so it is a separate ability from editing the draft.
     */
    public function send(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(PermissionKey::CampaignsSend);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(PermissionKey::CampaignsDelete);
    }

    public function restore(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(PermissionKey::CampaignsDelete);
    }
}
