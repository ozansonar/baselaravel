<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_new')->default(false)->after('is_featured');
            $table->boolean('allow_reviews')->default(true)->after('is_new');
            $table->boolean('show_related')->default(true)->after('allow_reviews');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_new', 'allow_reviews', 'show_related']);
        });
    }
};
