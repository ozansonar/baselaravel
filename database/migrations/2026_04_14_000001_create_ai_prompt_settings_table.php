<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompt_settings', function (Blueprint $table) {
            $table->id();
            $table->longText('system_instruction');
            $table->longText('user_prompt');
            $table->json('products');
            $table->string('category_slug', 100)->default('urunlerimiz');
            $table->unsignedSmallInteger('max_words')->default(600);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_settings');
    }
};
