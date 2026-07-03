<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vertex_generations', static function (Blueprint $table): void {
            $table->foreignId('batch_id')->nullable()->after('id')->constrained('vertex_batches')->cascadeOnDelete();
            $table->unsignedInteger('queue_position')->nullable()->after('batch_id');
            $table->timestamp('started_at')->nullable()->after('generation_time_ms');
            $table->timestamp('finished_at')->nullable()->after('started_at');
            $table->decimal('cost_usd', 8, 6)->nullable()->after('finished_at');
            $table->unsignedInteger('token_count')->nullable()->after('cost_usd');

            $table->index(['batch_id', 'queue_position']);
        });
    }

    public function down(): void
    {
        Schema::table('vertex_generations', static function (Blueprint $table): void {
            $table->dropForeign(['batch_id']);
            $table->dropIndex(['batch_id', 'queue_position']);
            $table->dropColumn(['batch_id', 'queue_position', 'started_at', 'finished_at', 'cost_usd', 'token_count']);
        });
    }
};
