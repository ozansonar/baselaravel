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
            $table->unsignedInteger('usage_count')->default(0)->after('ig_hashtags');
            $table->index(['aspect_ratio', 'usage_count', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('vertex_generations', function (Blueprint $table) {
            $table->dropIndex(['aspect_ratio', 'usage_count', 'status']);
            $table->dropColumn('usage_count');
        });
    }
};
