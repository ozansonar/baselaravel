<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vertex_prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vertex_prompt_id')->constrained('vertex_prompts')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('data');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->index(['vertex_prompt_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vertex_prompt_versions');
    }
};
