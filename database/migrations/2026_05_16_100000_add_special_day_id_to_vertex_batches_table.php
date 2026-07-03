<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vertex_batches', function (Blueprint $table) {
            $table->foreignId('special_day_id')
                ->nullable()
                ->after('prompt_id')
                ->constrained('special_days')
                ->nullOnDelete();

            $table->string('media_type', 20)->nullable()->after('special_day_id');

            $table->index(['special_day_id', 'media_type']);
        });
    }

    public function down(): void
    {
        Schema::table('vertex_batches', function (Blueprint $table) {
            $table->dropForeign(['special_day_id']);
            $table->dropIndex(['special_day_id', 'media_type']);
            $table->dropColumn(['special_day_id', 'media_type']);
        });
    }
};
