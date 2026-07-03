<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reels / Story desteği için medya tipi ve video alanı.
 *
 * media_type:
 *   - image (varsayılan, mevcut feed post + carousel)
 *   - reels (video, max 90 sn)
 *   - story (görsel veya video, 24 saat sonra Meta tarafında silinir)
 *
 * video_path: Reels veya Story video paylaşımı için. public/uploads/ altında relative path.
 * video_duration_seconds: video uzunluğu (UI gösterimi + validation).
 *
 * NOT: image_path tek görsel/carousel için zorunlu kalmaya devam eder
 * (Story görsel paylaşımında da kullanılır). Reels'te image_path NULL olabilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->string('media_type', 16)->default('image')->after('hashtags');
            $table->string('video_path', 512)->nullable()->after('image_path');
            $table->unsignedSmallInteger('video_duration_seconds')->nullable()->after('video_path');

            // image_path zorunluluğunu kaldır — Reels'te video_path yeterli
            $table->string('image_path', 512)->nullable()->change();

            $table->index('media_type');
        });
    }

    public function down(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->dropIndex(['media_type']);
            // image_path zorunluluğunu geri getir (önce NULL'lar varsa migration başarısız olur — ihtimal düşük)
            $table->string('image_path', 512)->nullable(false)->change();
            $table->dropColumn(['media_type', 'video_path', 'video_duration_seconds']);
        });
    }
};
