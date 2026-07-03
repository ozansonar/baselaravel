<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Kapak görseli: legacy/ → content/
        DB::table('blog_posts')
            ->where('image', 'LIKE', 'legacy/%')
            ->update(['image' => DB::raw("REPLACE(image, 'legacy/', 'content/')")]);

        // Body içindeki görseller: /uploads/legacy/ → /uploads/content/
        DB::table('blog_posts')
            ->where('body', 'LIKE', '%/uploads/legacy/%')
            ->update(['body' => DB::raw("REPLACE(body, '/uploads/legacy/', '/uploads/content/')")]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('blog_posts')
            ->where('image', 'LIKE', 'content/%')
            ->update(['image' => DB::raw("REPLACE(image, 'content/', 'legacy/')")]);

        DB::table('blog_posts')
            ->where('body', 'LIKE', '%/uploads/content/%')
            ->update(['body' => DB::raw("REPLACE(body, '/uploads/content/', '/uploads/legacy/')")]);
    }
};
