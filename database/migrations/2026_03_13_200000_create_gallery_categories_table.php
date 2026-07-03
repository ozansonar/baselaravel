<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('gallery_items', function (Blueprint $table): void {
            $table->foreignId('gallery_category_id')
                ->nullable()
                ->after('category')
                ->constrained('gallery_categories')
                ->nullOnDelete();

            $table->index('gallery_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_items', function (Blueprint $table): void {
            $table->dropForeign(['gallery_category_id']);
            $table->dropColumn('gallery_category_id');
        });

        Schema::dropIfExists('gallery_categories');
    }
};
