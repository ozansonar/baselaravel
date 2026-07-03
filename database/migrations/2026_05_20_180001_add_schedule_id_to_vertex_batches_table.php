<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vertex_batches', static function (Blueprint $table): void {
            $table->foreignId('schedule_id')
                ->nullable()
                ->after('special_day_id')
                ->constrained('vertex_schedules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vertex_batches', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('schedule_id');
        });
    }
};
