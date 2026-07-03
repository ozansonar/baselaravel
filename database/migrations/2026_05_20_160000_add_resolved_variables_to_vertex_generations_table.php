<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vertex_generations', function (Blueprint $table) {
            $table->json('resolved_variables')->nullable()->after('prompt_used');
        });
    }

    public function down(): void
    {
        Schema::table('vertex_generations', function (Blueprint $table) {
            $table->dropColumn('resolved_variables');
        });
    }
};
