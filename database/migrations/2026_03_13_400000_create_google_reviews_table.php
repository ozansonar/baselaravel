<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('author_name');
            $table->string('author_photo_url')->nullable();
            $table->string('author_profile_url')->nullable();
            $table->text('text')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->string('language', 10)->default('tr');
            $table->string('relative_time')->nullable();
            $table->timestamp('review_time')->nullable();
            $table->string('google_review_id')->unique()->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_visible');
            $table->index('sort_order');
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_reviews');
    }
};
