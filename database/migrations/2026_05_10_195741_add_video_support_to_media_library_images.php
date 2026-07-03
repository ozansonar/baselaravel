<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_library_images', function (Blueprint $table): void {
            // Video desteği — Reels için mp4 yükleme
            $table->boolean('is_video')->default(false)->after('media_type');
            $table->string('video_path', 500)->nullable()->after('image_path');
            $table->unsignedSmallInteger('video_duration_seconds')->nullable()->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('media_library_images', function (Blueprint $table): void {
            $table->dropColumn(['is_video', 'video_path', 'video_duration_seconds']);
        });
    }
};
