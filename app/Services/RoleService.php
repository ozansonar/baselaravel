<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PermissionGroup;
use App\Enums\PermissionKey;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

final class RoleService
{
    /**
     * Roles defined by the UserRole enum. They may be renamed but not deleted,
     * because the seeder recreates them and parts of the app expect them.
     */
    public function isSystemRole(Role $role): bool
    {
        return UserRole::tryFrom($role->slug) !== null;
    }

    /**
     * The admin role always holds every permission; letting it be edited is the
     * quickest way for an administrator to lock themselves out.
     */
    public function isLocked(Role $role): bool
    {
        return $role->slug === UserRole::Admin->value;
    }

    /**
     * @return Collection<int, Role>
     */
    /**
     * Rol listesinin sorgusu — ekran ve dışa aktarma aynı sırayı görsün.
     *
     * @return Builder<Role>
     */
    public function listQuery(): Builder
    {
        return Role::query()
            ->with(['permissions:id,key'])
            ->withCount(['users', 'permissions'])
            ->orderBy('id');
    }

    public function allWithCounts(): Collection
    {
        return $this->listQuery()->get();
    }

    /**
     * @return Collection<int, Permission>
     */
    public function allPermissions(): Collection
    {
        return Permission::query()->orderBy('sort_order')->get();
    }

    /**
     * Permissions grouped for the matrix, in enum order.
     *
     * @return array<string, array{group: PermissionGroup, permissions: Collection<int, Permission>}>
     */
    public function permissionMatrix(): array
    {
        $matrix = [];

        foreach ($this->allPermissions()->groupBy(fn (Permission $p): string => $p->group->value) as $group => $permissions) {
            $matrix[$group] = [
                'group'       => PermissionGroup::from($group),
                'permissions' => $permissions,
            ];
        }

        return $matrix;
    }

    /**
     * @return array{roles: int, system_roles: int, permissions: int, groups: int, assigned_users: int}
     */
    public function getAdminStats(): array
    {
        $roles = Role::withCount('users')->get();

        return [
            'roles'          => $roles->count(),
            'system_roles'   => $roles->filter(fn (Role $r): bool => $this->isSystemRole($r))->count(),
            'permissions'    => Permission::count(),
            'groups'         => count(PermissionGroup::cases()),
            'assigned_users' => (int) $roles->sum('users_count'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Role
    {
        return Role::create([
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Role $role, array $data): Role
    {
        $payload = [
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ];

        // A system role's slug is referenced by the seeder and by UserRole, so
        // only custom roles may be renamed at the slug level.
        if (! $this->isSystemRole($role) && isset($data['slug'])) {
            $payload['slug'] = $data['slug'];
        }

        $role->update($payload);

        return $role;
    }

    public function delete(Role $role): void
    {
        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();
    }

    /**
     * Replace the whole matrix in one transaction.
     *
     * @param array<string, array<int, string>> $assignments role slug => permission keys
     */
    public function syncMatrix(array $assignments): void
    {
        $permissionIds = Permission::pluck('id', 'key');

        // Neyin değiştiği denetim izine yazılıyor: izin matrisi panelin en
        // keskin ekranı, bir rolün neyi ne zaman kazandığı sonradan
        // sorulacak ilk şey. Pivot tablosunun modeli olmadığı için gözlemci
        // bunu göremiyor, kayıt buradan düşüyor.
        $changes = [];

        DB::transaction(function () use ($assignments, $permissionIds, &$changes): void {
            foreach (Role::with('permissions')->get() as $role) {
                $before = $role->permissions->pluck('key')->sort()->values()->all();

                if ($this->isLocked($role)) {
                    $role->permissions()->sync($permissionIds->values());

                    continue;
                }

                $keys = $assignments[$role->slug] ?? [];

                $ids = collect($keys)
                    ->filter(static fn (string $key): bool => PermissionKey::tryFrom($key) !== null)
                    ->map(static fn (string $key) => $permissionIds[$key] ?? null)
                    ->filter()
                    ->values();

                $role->permissions()->sync($ids);

                $after = $role->permissions()->pluck('key')->sort()->values()->all();

                $added = array_values(array_diff($after, $before));
                $removed = array_values(array_diff($before, $after));

                if ($added === [] && $removed === []) {
                    continue;
                }

                $changes[$role->slug] = array_filter([
                    'eklenen'   => $added,
                    'kaldirilan' => $removed,
                ]);
            }
        });

        // Hiçbir şey değişmediyse kayıt da yok: ekranı açıp kaydete basmak
        // denetim izini doldurmamalı.
        if ($changes !== []) {
            AuditLogger::custom('Rol izinleri güncellendi', $changes);
        }
    }

    /**
     * @param array<int, int> $roleIds
     */
    public function syncUserRoles(User $user, array $roleIds): void
    {
        $before = $user->roles()->pluck('slug')->sort()->values()->all();

        $user->roles()->sync($roleIds);

        $after = $user->roles()->pluck('slug')->sort()->values()->all();

        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        if ($added === [] && $removed === []) {
            return;
        }

        AuditLogger::custom('Kullanıcı rolleri değiştirildi', array_filter([
            'kullanici'  => '#' . $user->getKey() . ' ' . $user->email,
            'eklenen'    => $added,
            'kaldirilan' => $removed,
        ]));
    }

    /**
     * @return Collection<int, Role>
     */
    public function all(): Collection
    {
        return Role::orderBy('name')->get();
    }

    public function findById(int $id): Role
    {
        return Role::with(['users', 'permissions'])->findOrFail($id);
    }

    /**
     * Assign a single role to a user.
     */
    public function assignRole(User $user, string $slug): void
    {
        $role = Role::where('slug', $slug)->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    /**
     * Remove a role from a user.
     */
    public function removeRole(User $user, string $slug): void
    {
        $role = Role::where('slug', $slug)->firstOrFail();
        $user->roles()->detach($role->id);
    }
}
