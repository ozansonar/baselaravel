<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shot_list_items', function (Blueprint $table): void {
            $table->id();
            $table->string('topic', 250);
            $table->enum('media_type', ['feed', 'reels', 'story'])->default('feed')->index();
            $table->string('theme', 60)->nullable()->index();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'planned', 'done', 'cancelled'])
                ->default('pending')
                ->index();
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->timestamp('done_at')->nullable();
            $table->unsignedBigInteger('done_by')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority'], 'shot_status_priority_idx');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('done_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shot_list_items');
    }
};
