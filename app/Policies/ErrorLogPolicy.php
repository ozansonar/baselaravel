<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\ErrorLog;
use App\Models\User;

/**
 * Hata kayıtları yalnız yöneticiye açık.
 *
 * Kayıt yığın izi taşıyor: dosya yolları, sınıf adları, sorgu parçaları ve
 * bazen istek verisi. Editör ve moderatörün bu bilgiye ihtiyacı yok, görmesi
 * de doğru değil.
 *
 * Görmek ile dokunmak ayrı: "çözüldü" işareti ve silme, listeyi devralan
 * kişinin gördüğü tabloyu değiştiriyor.
 */
final class ErrorLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::ErrorLogsView);
    }

    public function view(User $user, ErrorLog $errorLog): bool
    {
        return $user->hasPermission(PermissionKey::ErrorLogsView);
    }

    public function update(User $user, ErrorLog $errorLog): bool
    {
        return $user->hasPermission(PermissionKey::ErrorLogsManage);
    }

    public function delete(User $user, ErrorLog $errorLog): bool
    {
        return $user->hasPermission(PermissionKey::ErrorLogsManage);
    }

    /**
     * Toplu temizlik — elde tek bir kayıt yokken sorulan izin.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::ErrorLogsManage);
    }
}
