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
    public function allWithCounts(): Collection
    {
        return Role::query()
            ->with(['permissions:id,key'])
            ->withCount(['users', 'permissions'])
            ->orderBy('id')
            ->get();
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

        DB::transaction(function () use ($assignments, $permissionIds): void {
            foreach (Role::all() as $role) {
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
            }
        });
    }

    /**
     * @param array<int, int> $roleIds
     */
    public function syncUserRoles(User $user, array $roleIds): void
    {
        $user->roles()->sync($roleIds);
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
