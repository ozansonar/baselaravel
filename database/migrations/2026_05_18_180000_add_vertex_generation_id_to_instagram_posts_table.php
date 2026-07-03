<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('vertex_generation_id')->nullable()->after('bulk_import_id');
            $table->index('vertex_generation_id');
        });
    }

    public function down(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->dropIndex(['vertex_generation_id']);
            $table->dropColumn('vertex_generation_id');
        });
    }
};
