<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class AddressService
{
    /**
     * @return Collection<int, Address>
     */
    public function allByUser(User $user): Collection
    {
        return $user->addresses()->orderByDesc('is_default')->latest()->get();
    }

    public function findById(int $id): Address
    {
        return Address::findOrFail($id);
    }

    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data): Address {
            // If this is set as default, unset previous default
            if (!empty($data['is_default'])) {
                $user->addresses()->update(['is_default' => false]);
            }

            // If first address, make it default
            if (!$user->addresses()->exists()) {
                $data['is_default'] = true;
            }

            $data['user_id'] = $user->id;

            return Address::create($data);
        });
    }

    public function update(Address $address, array $data): Address
    {
        return DB::transaction(function () use ($address, $data): Address {
            if (!empty($data['is_default'])) {
                Address::where('user_id', $address->user_id)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            $address->update($data);

            return $address->refresh();
        });
    }

    public function delete(Address $address): void
    {
        DB::transaction(function () use ($address): void {
            $wasDefault = $address->is_default;
            $userId = $address->user_id;

            $address->delete();

            // Assign default to another address if deleted was default
            if ($wasDefault) {
                Address::where('user_id', $userId)
                    ->latest()
                    ->first()
                    ?->update(['is_default' => true]);
            }
        });
    }
}
