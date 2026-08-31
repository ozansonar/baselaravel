<?php

declare(strict_types=1);

use App\Enums\PermissionKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kuyruk ekranının izinleri.
 *
 * İzinlerin tek kaynağı `PermissionKey` enum'u ve `PermissionSeeder` onu
 * satırlara çeviriyor — ama seeder yalnızca kurulumda çalışıyor. Deploy
 * `git pull` + `migrate` ile yapıldığı için mevcut bir kurulumda yeni enum
 * case'i satır karşılığı bulamaz ve **yönetici bile ekranı göremezdi.**
 *
 * Bu yüzden yeni izinler migration ile ekleniyor. Yönetici rolünün izinleri
 * kilitli olduğu için (bkz. RoleService::isLocked) yeni izin ona doğrudan
 * veriliyor; diğer roller matristen alır.
 */
return new class extends Migration
{
    /**
     * @var list<PermissionKey>
     */
    private const KEYS = [
        PermissionKey::QueueView,
        PermissionKey::QueueManage,
    ];

    public function up(): void
    {
        $now = now();
        $sort = (int) DB::table('permissions')->max('sort_order');

        foreach (self::KEYS as $key) {
            $exists = DB::table('permissions')->where('key', $key->value)->exists();

            if ($exists) {
                continue;
            }

            DB::table('permissions')->insert([
                'key'        => $key->value,
                'name'       => $key->label(),
                'group'      => $key->group()->value,
                'sort_order' => ++$sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($adminRoleId === null) {
            return;
        }

        foreach (self::KEYS as $key) {
            $permissionId = DB::table('permissions')->where('key', $key->value)->value('id');

            if ($permissionId === null) {
                continue;
            }

            $alreadyGranted = DB::table('permission_role')
                ->where('permission_id', $permissionId)
                ->where('role_id', $adminRoleId)
                ->exists();

            if ($alreadyGranted) {
                continue;
            }

            DB::table('permission_role')->insert([
                'permission_id' => $permissionId,
                'role_id'       => $adminRoleId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('key', array_map(static fn (PermissionKey $key): string => $key->value, self::KEYS))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        // Bağlantı önce: permission_role kısıtlı silme ile bağlı.
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
