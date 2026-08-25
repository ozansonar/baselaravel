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
            LanguageSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            UserSeeder::class,
            SettingSeeder::class,
            PageSeeder::class,
            FaqSeeder::class,
            BlogSeeder::class,
            MenuSeeder::class,
        ]);

        // NOT: SliderSeeder demo görselleri gerçek dosya gerektirdiğinden
        // varsayılan seed'e dahil değildir. Slider'lar admin panelinden
        // eklenir; istenirse: php artisan db:seed --class=SliderSeeder
    }
}
