<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Yönetici',   'slug' => 'admin',     'description' => 'Tam yetkili sistem yöneticisi'],
            ['name' => 'Editör',     'slug' => 'editor',    'description' => 'İçerik yönetimi yetkisi'],
            ['name' => 'Moderatör',  'slug' => 'moderator', 'description' => 'Mesaj ve yorum yönetimi yetkisi'],
            ['name' => 'Kullanıcı',  'slug' => 'user',      'description' => 'Kayıtlı site kullanıcısı'],
            ['name' => 'İzleyici',   'slug' => 'viewer',    'description' => 'Sadece görüntüleme yetkisi'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
