<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\CustomRoute;
use App\Models\User;

/**
 * İzinler veritabanından (rol → izin) geliyor; bir rolün ne yapabileceği bu
 * dosya değiştirilerek değil roller ekranından değişiyor.
 */
final class CustomRoutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::CustomRoutesView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::CustomRoutesManage);
    }

    public function update(User $user, CustomRoute $route): bool
    {
        return $user->hasPermission(PermissionKey::CustomRoutesManage);
    }

    public function delete(User $user, CustomRoute $route): bool
    {
        return $user->hasPermission(PermissionKey::CustomRoutesDelete);
    }

    public function restore(User $user, CustomRoute $route): bool
    {
        return $user->hasPermission(PermissionKey::CustomRoutesDelete);
    }
}
