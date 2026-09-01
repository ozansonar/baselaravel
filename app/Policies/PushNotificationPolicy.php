<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\PushNotification;
use App\Models\User;

final class PushNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::PushNotificationsView);
    }

    public function view(User $user, PushNotification $notification): bool
    {
        return $user->hasPermission(PermissionKey::PushNotificationsView);
    }

    /**
     * Duyuru yazmak ile göndermek aynı işlem: taslak durumu yok, kaydedilen
     * her duyuru sıraya giriyor. Bu yüzden oluşturma yetkisi gönderme
     * yetkisiyle aynı — cihaza ulaşan bildirim geri alınamıyor.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::PushNotificationsSend);
    }

    /**
     * Sıradaki bir gönderimi durdurmak, göndermeye karar verenin işi.
     */
    public function cancel(User $user, PushNotification $notification): bool
    {
        return $user->hasPermission(PermissionKey::PushNotificationsSend);
    }

    public function delete(User $user, PushNotification $notification): bool
    {
        return $user->hasPermission(PermissionKey::PushNotificationsDelete);
    }

    public function restore(User $user, PushNotification $notification): bool
    {
        return $user->hasPermission(PermissionKey::PushNotificationsDelete);
    }
}
