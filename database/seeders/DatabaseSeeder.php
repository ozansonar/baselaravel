<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            SliderSeeder::class,
            SettingSeeder::class,
            PageSeeder::class,
            FaqSeeder::class,
            BlogSeeder::class,
            MenuSeeder::class,
        ]);
    }
}
