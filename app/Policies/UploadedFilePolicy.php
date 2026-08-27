<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\UploadedFile;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), so what a role can
 * do is changed from the roles screen, not by editing this file.
 */
final class UploadedFilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::FilesView);
    }

    public function view(User $user, UploadedFile $file): bool
    {
        return $user->hasPermission(PermissionKey::FilesView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::FilesManage);
    }

    public function update(User $user, UploadedFile $file): bool
    {
        return $user->hasPermission(PermissionKey::FilesManage);
    }

    public function delete(User $user, UploadedFile $file): bool
    {
        return $user->hasPermission(PermissionKey::FilesDelete);
    }

    /**
     * Kaydı olmayan bir dosyayı silme yetkisi.
     *
     * Editörün dosya seçicisi diski listeliyor ve orada uploaded_files
     * karşılığı bulunmayan dosyalar da var (editörden yüklenenler tabloya
     * yazılmıyor). Böyle bir dosya için delete()'e verilecek bir model yok;
     * izin aynı, yalnızca imza modelsiz.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::FilesDelete);
    }
}
