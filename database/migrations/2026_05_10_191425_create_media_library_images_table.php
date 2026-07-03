<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library_images', function (Blueprint $table): void {
            $table->id();
            $table->string('image_path', 500);
            $table->enum('media_type', ['feed', 'reels', 'story'])->default('feed');
            $table->json('tags')->nullable();
            $table->string('theme', 60)->nullable()->index();
            $table->string('mood', 30)->nullable();
            $table->string('season', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('ai_generated_tags')->default(false);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('alt_text', 250)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Bulk import sırasında least-used + tip filtresi için optimum index
            $table->index(['media_type', 'usage_count'], 'mli_least_used_idx');
            $table->index(['theme', 'media_type'], 'mli_theme_type_idx');
            $table->index(['season', 'media_type'], 'mli_season_type_idx');
            $table->index(['mood', 'media_type'], 'mli_mood_type_idx');

            $table->foreign('created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library_images');
    }
};
