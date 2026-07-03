<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cron'dan üretilen blog yazılarının otomatik IG paylaşımı için izleme alanı.
 * - blog_post_id null = manuel oluşturulmuş post (eski davranış)
 * - blog_post_id dolu = blog'dan otomatik üretilmiş; idempotency için kullanılır
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table): void {
            $table->foreignId('blog_post_id')
                ->nullable()
                ->after('created_by')
                ->constrained('blog_posts')
                ->nullOnDelete()
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table): void {
            $table->dropForeign(['blog_post_id']);
            $table->dropColumn('blog_post_id');
        });
    }
};
