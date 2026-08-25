<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Configurable so a deployment can seed its own password instead of the
        // one shipped in the repository. See config/seeding.php.
        $password = (string) config('seeding.password');

        // Admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name'        => 'Admin',
                'last_name'         => 'User',
                'phone'             => null,
                'password'          => $password,
                'email_verified_at' => now(),
                'is_active'         => true,
            ],
        );
        $admin->roles()->syncWithoutDetaching(
            Role::where('slug', 'admin')->pluck('id'),
        );

        // Editor user
        $editor = User::updateOrCreate(
            ['email' => 'editor@example.com'],
            [
                'first_name'        => 'Editor',
                'last_name'         => 'User',
                'phone'             => null,
                'password'          => $password,
                'email_verified_at' => now(),
                'is_active'         => true,
            ],
        );
        $editor->roles()->syncWithoutDetaching(
            Role::where('slug', 'editor')->pluck('id'),
        );

        // Regular user
        $user = User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'first_name'        => 'Regular',
                'last_name'         => 'User',
                'phone'             => null,
                'password'          => $password,
                'email_verified_at' => now(),
                'is_active'         => true,
            ],
        );
        $user->roles()->syncWithoutDetaching(
            Role::where('slug', 'user')->pluck('id'),
        );
    }
}
