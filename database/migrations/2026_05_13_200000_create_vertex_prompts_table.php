<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vertex_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->text('prompt');

            // Reference image (image-to-image) — public URL fileUri'a verilir.
            $table->string('reference_image_path', 255)->nullable();
            $table->string('reference_image_mime', 60)->nullable();

            // Generation config (request body için)
            $table->string('model', 80)->default('gemini-3.1-flash-image-preview');
            $table->string('aspect_ratio', 10)->default('1:1');
            $table->string('image_size', 10)->default('1K');
            $table->string('mime_type', 30)->default('image/png');
            $table->decimal('temperature', 3, 2)->default(1.00);
            $table->unsignedInteger('max_output_tokens')->default(32768);
            $table->decimal('top_p', 3, 2)->default(0.95);
            $table->string('thinking_level', 20)->default('MINIMAL');
            $table->string('person_generation', 30)->default('ALLOW_ALL');
            $table->boolean('safety_off')->default(true);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vertex_prompts');
    }
};
