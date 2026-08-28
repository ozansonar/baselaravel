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

        // NOT: DemoMediaSeeder (slider + galeri) varsayılan seed'e dahil değil.
        // Görselleri kendisi üretip public/uploads altına yazıyor; canlıda
        // istenmeyen içerik doğurmaması için elle çağrılıyor:
        //   php artisan db:seed --class=DemoMediaSeeder
    }
}
