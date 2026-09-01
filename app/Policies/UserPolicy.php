<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\User;

/**
 * Permissions come from the database (role -> permission), with two rules that
 * stay in code because they are about identity rather than configuration:
 * everyone may *see* their own record, and nobody may delete themselves.
 *
 * Kendi kaydını **düzenlemek** bilerek bu listede yok. Bir zamanlar vardı ve
 * yetki yükseltme kapısıydı: kullanıcı ekranının formu `roles[]` kabul ediyor,
 * rotada izin denetimi yok, dolayısıyla panele girebilen herkes kendi kaydına
 * tek bir PUT atıp admin rolünü kendine verebiliyordu. Kendi bilgilerini
 * düzenlemenin yeri ProfileController — o form rol alanı taşımıyor.
 *
 * @see \App\Http\Controllers\Admin\ProfileController
 */
final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::UsersView);
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id
            || $user->hasPermission(PermissionKey::UsersView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::UsersManage);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermission(PermissionKey::UsersManage);
    }

    public function delete(User $user, User $model): bool
    {
        // Deleting your own account from the panel would lock you out mid-session.
        if ($user->id === $model->id) {
            return false;
        }

        return $user->hasPermission(PermissionKey::UsersDelete);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasPermission(PermissionKey::UsersDelete);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasPermission(PermissionKey::UsersDelete);
    }
}
