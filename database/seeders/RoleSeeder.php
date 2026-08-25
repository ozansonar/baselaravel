<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Roles come from the UserRole enum so the slugs live in one place.
        foreach (UserRole::cases() as $role) {
            Role::updateOrCreate(
                ['slug' => $role->value],
                [
                    'name'        => $role->label(),
                    'description' => $role->description(),
                ],
            );
        }
    }
}
