<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('blog_categories')->insertOrIgnore([
            'name'       => 'Ürünlerimiz',
            'slug'       => Str::slug('Ürünlerimiz'),
            'icon'       => 'bi-box-seam',
            'is_active'  => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed gemini_api_key setting
        DB::table('settings')->insertOrIgnore([
            'key'   => 'gemini_api_key',
            'value' => null,
            'group' => 'ai',
            'type'  => 'text',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('blog_categories')->where('slug', 'urunlerimiz')->delete();
        DB::table('settings')->where('key', 'gemini_api_key')->delete();
    }
};
