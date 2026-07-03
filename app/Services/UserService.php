<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

final class UserService
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly UploadService $uploadService,
    ) {}

    // ── Admin Stats ──

    /**
     * @return array{total: int, active: int, new_this_month: int, inactive: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin_user_stats', 300, function (): array {
            $startOfMonth = Carbon::now()->startOfMonth();

            $counts = User::withTrashed()->selectRaw("
                SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN deleted_at IS NULL AND is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN deleted_at IS NULL AND created_at >= ? THEN 1 ELSE 0 END) as new_this_month,
                SUM(CASE WHEN deleted_at IS NULL AND is_active = 0 THEN 1 ELSE 0 END) as inactive
            ", [$startOfMonth])->first();

            return [
                'total'          => (int) $counts->total,
                'active'         => (int) $counts->active,
                'new_this_month' => (int) $counts->new_this_month,
                'inactive'       => (int) $counts->inactive,
            ];
        });
    }

    /**
     * @return array{all: int, active: int, inactive: int, trashed: int}
     */
    public function getStatusCounts(): array
    {
        $counts = User::withTrashed()
            ->selectRaw('sum(case when deleted_at is null then 1 else 0 end) as total')
            ->selectRaw('sum(case when deleted_at is null and is_active = 1 then 1 else 0 end) as active')
            ->selectRaw('sum(case when deleted_at is null and is_active = 0 then 1 else 0 end) as inactive')
            ->selectRaw('sum(case when deleted_at is not null then 1 else 0 end) as trashed')
            ->first();

        return [
            'all'      => (int) $counts->total,
            'active'   => (int) $counts->active,
            'inactive' => (int) $counts->inactive,
            'trashed'  => (int) $counts->trashed,
        ];
    }

    // ── Admin Paginate ──

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        $query = User::with('roles')->latest();

        if (isset($filters['status'])) {
            match ($filters['status']) {
                'active'  => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                'trashed' => $query->onlyTrashed(),
                default   => null,
            };
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters): void {
                $q->where('slug', $filters['role']);
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    // ── CRUD ──

    /**
     * @param array<string, mixed> $data
     * @param \Illuminate\Http\UploadedFile|null $avatar
     * @param array<int>|null $roles
     */
    public function create(array $data, $avatar = null, ?array $roles = null): User
    {
        if ($avatar) {
            $data['avatar'] = $this->uploadService->uploadImage(
                $avatar,
                'users',
                $data['first_name'] . '-' . $data['last_name'],
            );
        }

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        if ($roles) {
            $this->roleService->syncUserRoles($user, $roles);
        }

        Cache::forget('admin_user_stats');

        return $user;
    }

    /**
     * @param array<string, mixed> $data
     * @param \Illuminate\Http\UploadedFile|null $avatar
     * @param array<int>|null $roles
     */
    public function update(User $user, array $data, $avatar = null, ?array $roles = null, ?string $password = null, bool $removeAvatar = false): User
    {
        if ($removeAvatar && !$avatar && $user->avatar) {
            $this->uploadService->deleteImage($user->avatar);
            $data['avatar'] = null;
        } elseif ($avatar) {
            $data['avatar'] = $this->uploadService->replaceImage(
                $avatar,
                'users',
                ($data['first_name'] ?? $user->first_name) . '-' . ($data['last_name'] ?? $user->last_name),
                $user->avatar,
            );
        }

        if ($password) {
            $data['password'] = Hash::make($password);
        }

        $user->update($data);

        if ($roles !== null) {
            $this->roleService->syncUserRoles($user, $roles);
        }

        Cache::forget('admin_user_stats');

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
        Cache::forget('admin_user_stats');
    }

    public function restore(User $user): void
    {
        $user->restore();
        Cache::forget('admin_user_stats');
    }
}
