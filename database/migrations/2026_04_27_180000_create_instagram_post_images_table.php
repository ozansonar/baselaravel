<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carousel post desteği için ek görseller tablosu.
 * Tek görsel post: instagram_posts.image_path doluymalı, bu tabloda kayıt olmayabilir
 * Carousel post: instagram_posts.image_path = ana görsel + bu tabloda 1-9 ek görsel
 *
 * Instagram Carousel limit: max 10 görsel toplam (1 ana + 9 ek).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_post_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instagram_post_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->string('ig_child_media_id', 64)->nullable(); // Carousel publish sırasında oluşan child container ID
            $table->timestamps();
            $table->softDeletes();

            $table->index(['instagram_post_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_post_images');
    }
};
