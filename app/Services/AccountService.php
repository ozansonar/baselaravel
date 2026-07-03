<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class AccountService
{
    public function __construct(
        private readonly UploadService $uploadService,
    ) {}
    /**
     * Update user profile.
     *
     * @param array{first_name: string, last_name: string, email: string, phone?: string|null, password?: string|null} $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $updateData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? $user->phone,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = $data['password'];
        }

        $user->update($updateData);

        $user->refresh();

        return $user;
    }

    /**
     * Handle avatar upload: upload new, delete old, update user.
     */
    public function handleAvatarUpload(User $user, UploadedFile $file, string $name): void
    {
        $oldAvatar = $user->avatar;

        $avatarPath = $this->uploadService->uploadImage(
            $file,
            'avatars',
            $name,
            ['thumb', 'sm'],
        );

        if ($oldAvatar) {
            $this->uploadService->deleteImage($oldAvatar);
        }

        $user->update(['avatar' => $avatarPath]);
    }

    /**
     * Remove user avatar.
     */
    public function removeAvatar(User $user): void
    {
        if ($user->avatar) {
            $this->uploadService->deleteImage($user->avatar);
        }

        $user->update(['avatar' => null]);
    }

    /**
     * Get dashboard data for user.
     *
     * @return array{recentOrders: \Illuminate\Database\Eloquent\Collection, orderCounts: array<string, int>, defaultAddress: \App\Models\Address|null}
     */
    public function getDashboardData(User $user): array
    {
        $recentOrders = $user->orders()
            ->with('items')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $statusCounts = $user->orders()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as shipped,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered
            ", [
                \App\Enums\OrderStatus::Pending->value,
                \App\Enums\OrderStatus::Shipped->value,
                \App\Enums\OrderStatus::Delivered->value,
            ])->first();

        return [
            'recentOrders' => $recentOrders,
            'orderCounts' => [
                'total'     => (int) $statusCounts->total,
                'pending'   => (int) $statusCounts->pending,
                'shipped'   => (int) $statusCounts->shipped,
                'delivered' => (int) $statusCounts->delivered,
            ],
            'defaultAddress' => $user->defaultAddress(),
        ];
    }

    /**
     * Get paginated orders for user.
     *
     * @return LengthAwarePaginator<\App\Models\Order>
     */
    public function getOrders(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return $user->orders()
            ->with('items.product')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get single order for user.
     */
    public function getOrder(User $user, int $orderId): ?\App\Models\Order
    {
        return $user->orders()
            ->with('items.product')
            ->find($orderId);
    }

    /**
     * Get all addresses for user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Address>
     */
    public function getAddresses(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Create a new address.
     *
     * @param array{title: string, full_name: string, phone: string, city: string, district: string, zip_code?: string|null, address_line: string, is_default?: bool} $data
     */
    public function createAddress(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data): Address {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            // If user has no addresses, make the first one default
            if ($user->addresses()->doesntExist()) {
                $isDefault = true;
            }

            return $user->addresses()->create([
                'title' => $data['title'],
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'city' => $data['city'],
                'district' => $data['district'],
                'zip_code' => $data['zip_code'] ?? null,
                'address_line' => $data['address_line'],
                'is_default' => $isDefault,
            ]);
        });
    }

    /**
     * Update an existing address.
     *
     * @param array{title: string, full_name: string, phone: string, city: string, district: string, zip_code?: string|null, address_line: string, is_default?: bool} $data
     */
    public function updateAddress(User $user, Address $address, array $data): Address
    {
        return DB::transaction(function () use ($user, $address, $data): Address {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault && !$address->is_default) {
                $user->addresses()->update(['is_default' => false]);
            }

            $address->update([
                'title' => $data['title'],
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'city' => $data['city'],
                'district' => $data['district'],
                'zip_code' => $data['zip_code'] ?? null,
                'address_line' => $data['address_line'],
                'is_default' => $isDefault,
            ]);

            $address->refresh();

            return $address;
        });
    }

    /**
     * Delete an address.
     */
    public function deleteAddress(User $user, Address $address): void
    {
        DB::transaction(function () use ($user, $address): void {
            $wasDefault = $address->is_default;

            $address->delete();

            // If deleted address was default, assign default to another
            if ($wasDefault) {
                $nextAddress = $user->addresses()->orderByDesc('created_at')->first();
                $nextAddress?->update(['is_default' => true]);
            }
        });
    }

    /**
     * Set an address as default.
     */
    public function setDefaultAddress(User $user, Address $address): void
    {
        DB::transaction(function () use ($user, $address): void {
            $user->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });
    }
}
