<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Subscriber;
use App\Models\User;

final class SubscriberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::SubscribersView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::SubscribersManage);
    }

    public function update(User $user, Subscriber $subscriber): bool
    {
        return $user->hasPermission(PermissionKey::SubscribersManage);
    }

    public function delete(User $user, Subscriber $subscriber): bool
    {
        return $user->hasPermission(PermissionKey::SubscribersManage);
    }

    /**
     * Abone listelerini (tedarikçiler, pazarlamacılar…) yönetme yetkisi.
     *
     * Liste bir abone kaydı değil, o yüzden model isteyen delete/update ile
     * sorulamıyor; yetki yine aboneleri yönetme yetkisi — listeyi düzenleyen
     * kişi zaten aboneleri de düzenliyor, ayrı bir izin çifti izin listesini
     * gereksiz kalabalık yapardı.
     */
    public function manageLists(User $user): bool
    {
        return $user->hasPermission(PermissionKey::SubscribersManage);
    }
}
