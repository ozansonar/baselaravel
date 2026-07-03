<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generated_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_template_id')->nullable()->constrained('ai_image_prompts')->nullOnDelete();
            $table->text('final_prompt');
            $table->string('image_path', 255)->nullable();
            $table->string('provider', 30)->default('gemini');
            $table->string('model', 60)->nullable();
            $table->string('aspect_ratio', 10)->default('1:1');
            $table->string('status', 20)->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('generation_time_ms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generated_images');
    }
};
