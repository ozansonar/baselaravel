<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $enumMap = [
            'genel'       => ['name' => 'Genel',       'slug' => 'genel',       'sort_order' => 1],
            'etkinlikler' => ['name' => 'Etkinlikler', 'slug' => 'etkinlikler', 'sort_order' => 2],
            'ekip'        => ['name' => 'Ekip',         'slug' => 'ekip',         'sort_order' => 3],
            'mekan'       => ['name' => 'Mekan',        'slug' => 'mekan',        'sort_order' => 4],
        ];

        $now = now();

        foreach ($enumMap as $enumValue => $data) {
            $exists = DB::table('gallery_categories')->where('slug', $data['slug'])->exists();

            if (! $exists) {
                $categoryId = DB::table('gallery_categories')->insertGetId([
                    'name'       => $data['name'],
                    'slug'       => $data['slug'],
                    'is_active'  => true,
                    'sort_order' => $data['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $categoryId = DB::table('gallery_categories')->where('slug', $data['slug'])->value('id');
            }

            DB::table('gallery_items')
                ->where('category', $enumValue)
                ->whereNull('gallery_category_id')
                ->update(['gallery_category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        $enumMap = [
            'genel'       => 'Genel',
            'etkinlikler' => 'Etkinlikler',
            'ekip'        => 'Ekip',
            'mekan'       => 'Mekan',
        ];

        foreach ($enumMap as $enumValue => $name) {
            $categoryId = DB::table('gallery_categories')->where('slug', $enumValue)->value('id');

            if ($categoryId) {
                DB::table('gallery_items')
                    ->where('gallery_category_id', $categoryId)
                    ->update([
                        'category'             => $enumValue,
                        'gallery_category_id'  => null,
                    ]);
            }
        }
    }
};
