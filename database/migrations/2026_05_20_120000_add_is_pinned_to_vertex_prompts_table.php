<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vertex_prompts', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->after('is_active');
            $table->index(['is_pinned', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('vertex_prompts', function (Blueprint $table) {
            $table->dropIndex(['is_pinned', 'is_active', 'sort_order']);
            $table->dropColumn('is_pinned');
        });
    }
};
