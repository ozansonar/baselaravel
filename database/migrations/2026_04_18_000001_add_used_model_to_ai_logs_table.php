<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_logs', function (Blueprint $table) {
            $table->string('used_model', 100)->nullable()->after('token_count');
            $table->unsignedTinyInteger('attempt_count')->nullable()->after('used_model');

            $table->index('used_model');
        });
    }

    public function down(): void
    {
        Schema::table('ai_logs', function (Blueprint $table) {
            $table->dropIndex(['used_model']);
            $table->dropColumn(['used_model', 'attempt_count']);
        });
    }
};
