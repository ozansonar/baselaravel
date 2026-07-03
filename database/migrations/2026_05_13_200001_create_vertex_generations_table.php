<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vertex_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_id')->nullable()->constrained('vertex_prompts')->nullOnDelete();

            // Snapshot — şablon sonradan değişse bile geçmiş bozulmasın.
            $table->text('prompt_used');
            $table->string('reference_image_path', 255)->nullable();
            $table->string('output_image_path', 255)->nullable();

            // Generation config snapshot
            $table->string('model', 80);
            $table->string('aspect_ratio', 10)->default('1:1');
            $table->string('image_size', 10)->default('1K');
            $table->string('mime_type', 30)->default('image/png');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('generation_time_ms')->nullable();

            $table->longText('request_payload')->nullable();
            $table->longText('response_payload')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vertex_generations');
    }
};
