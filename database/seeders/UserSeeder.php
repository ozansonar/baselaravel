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
        // Admin user
        $admin = User::updateOrCreate(
            ['email' => 'ozansonar1@gmail.com'],
            [
                'first_name'        => 'Orhan',
                'last_name'         => 'Baba',
                'phone'             => '+905059424124',
                'password'          => 'Cft625-!q56?T',
                'email_verified_at' => now(),
                'is_active'         => true,
            ],
        );
        $admin->roles()->syncWithoutDetaching(
            Role::where('slug', 'admin')->pluck('id'),
        );

        // Editor user
        $editor = User::updateOrCreate(
            ['email' => 'editor@orhanbabaninciftligi.com'],
            [
                'first_name'        => 'Ayşe',
                'last_name'         => 'Editör',
                'phone'             => '05559876543',
                'password'          => 'password',
                'email_verified_at' => now(),
                'is_active'         => true,
            ],
        );
        $editor->roles()->syncWithoutDetaching(
            Role::where('slug', 'editor')->pluck('id'),
        );

        // Regular user
        $user = User::updateOrCreate(
            ['email' => 'kullanici@example.com'],
            [
                'first_name'        => 'Mehmet',
                'last_name'         => 'Kullanıcı',
                'phone'             => '05553456789',
                'password'          => 'password',
                'email_verified_at' => now(),
                'is_active'         => true,
            ],
        );
        $user->roles()->syncWithoutDetaching(
            Role::where('slug', 'user')->pluck('id'),
        );
    }
}
