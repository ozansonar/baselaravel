<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_videos', function (Blueprint $table) {
            $table->id();
            $table->string('video_id', 20)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('duration', 30)->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_visible');
            $table->index('sort_order');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_videos');
    }
};
