<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class RoleService
{
    /**
     * @return Collection<int, Role>
     */
    public function all(): Collection
    {
        return Role::orderBy('name')->get();
    }

    public function findById(int $id): Role
    {
        return Role::with('users')->findOrFail($id);
    }

    /**
     * Sync roles for a user.
     *
     * @param array<int> $roleIds
     */
    public function syncUserRoles(User $user, array $roleIds): void
    {
        $user->roles()->sync($roleIds);
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
