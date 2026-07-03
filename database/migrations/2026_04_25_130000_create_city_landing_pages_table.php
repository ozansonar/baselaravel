<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_landing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('city_name', 100);
            $table->string('city_slug', 120)->unique();
            $table->string('region', 60)->nullable();
            $table->unsignedTinyInteger('tier')->default(1);
            $table->string('title');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 200)->nullable();
            $table->string('hero_heading');
            $table->text('hero_description')->nullable();
            $table->longText('content')->nullable();
            $table->string('shipping_note', 255)->nullable();
            $table->string('delivery_time', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('tier');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_landing_pages');
    }
};
