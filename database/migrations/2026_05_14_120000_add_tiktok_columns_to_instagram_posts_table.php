<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * instagram_posts tablosuna TikTok cross-post kolonları ekle.
     *
     * Tasarım kararı: Yeni tablo açılmıyor; mevcut FB cross-post pattern'i
     * (publish_to_facebook + fb_* alanları) ile birebir aynı.
     *
     * Detay: docs/tiktok.md Bölüm 3.
     */
    public function up(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table): void {
            $table->boolean('publish_to_tiktok')->default(false)->after('fb_error_message');
            $table->string('tt_post_id', 255)->nullable()->after('publish_to_tiktok');
            $table->string('tt_permalink', 500)->nullable()->after('tt_post_id');
            $table->timestamp('tt_published_at')->nullable()->after('tt_permalink');
            $table->text('tt_error_message')->nullable()->after('tt_published_at');
            $table->unsignedTinyInteger('tt_retry_count')->default(0)->after('tt_error_message');
            $table->string('tt_inbox_id', 255)->nullable()->after('tt_retry_count');

            $table->index('publish_to_tiktok');
            $table->index('tt_post_id');
        });
    }

    public function down(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table): void {
            $table->dropIndex(['publish_to_tiktok']);
            $table->dropIndex(['tt_post_id']);
            $table->dropColumn([
                'publish_to_tiktok',
                'tt_post_id',
                'tt_permalink',
                'tt_published_at',
                'tt_error_message',
                'tt_retry_count',
                'tt_inbox_id',
            ]);
        });
    }
};
