<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_days', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->index();
            $table->string('name', 150);
            $table->string('slug', 180)->index();
            $table->enum('category', ['ulusal', 'dini', 'ozel', 'sezonsal', 'genel'])
                ->default('genel')
                ->index();
            $table->text('theme')->nullable();
            $table->enum('source', ['fixed', 'rule', 'ai', 'manual'])
                ->default('manual')
                ->index();
            $table->boolean('is_movable')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Aynı tarih + ad kombinasyonu birden fazla olabilir (Ramazan Bayramı 1/2/3 gün)
            // ama tarih + slug benzersiz
            $table->unique(['date', 'slug']);

            $table->foreign('verified_by')
                ->references('id')->on('users')
                ->nullOnDelete();
        });

        Schema::create('special_day_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('special_day_id')
                ->constrained('special_days')
                ->cascadeOnDelete();
            $table->string('image_path', 500);
            $table->enum('media_type', ['feed', 'reels', 'story'])
                ->default('feed')
                ->index();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('alt_text', 250)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['special_day_id', 'media_type', 'usage_count'], 'sd_img_least_used_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_day_images');
        Schema::dropIfExists('special_days');
    }
};
