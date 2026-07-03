<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facebook Page cross-post desteği:
 * - publish_to_facebook: Form'da "Facebook'a da paylaş" toggle
 * - fb_post_id: Facebook tarafındaki post ID
 * - fb_permalink: Facebook post URL'si
 * - fb_published_at: Facebook'ta yayın zamanı (Instagram'dan farklı olabilir)
 * - fb_error_message: Sadece Facebook'a özel hata
 *
 * Instagram ve Facebook bağımsız çalışır — biri başarısız olursa diğeri devam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->boolean('publish_to_facebook')->default(false)->after('status');
            $table->string('fb_post_id', 64)->nullable()->after('permalink');
            $table->string('fb_permalink', 512)->nullable()->after('fb_post_id');
            $table->timestamp('fb_published_at')->nullable()->after('published_at');
            $table->text('fb_error_message')->nullable()->after('error_message');

            $table->index('fb_post_id');
            $table->index('publish_to_facebook');
        });
    }

    public function down(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->dropIndex(['fb_post_id']);
            $table->dropIndex(['publish_to_facebook']);
            $table->dropColumn([
                'publish_to_facebook',
                'fb_post_id',
                'fb_permalink',
                'fb_published_at',
                'fb_error_message',
            ]);
        });
    }
};
