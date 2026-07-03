<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vertex_schedules', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('prompt_id')->constrained('vertex_prompts')->cascadeOnDelete();
            $table->json('variables')->nullable();
            $table->unsignedSmallInteger('count')->default(1);
            $table->string('aspect_ratio_override', 10)->nullable();
            $table->string('frequency', 20)->default('daily');
            $table->json('days_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->string('time', 5)->default('09:00');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedInteger('total_runs')->default(0);
            $table->unsignedInteger('total_images_generated')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vertex_schedules');
    }
};
