<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Languages the site is published in.
 *
 * Managed from the panel rather than a config file, so adding a language never
 * needs a deploy. Exactly one row carries is_default; that invariant is kept by
 * LanguageService, which is the only place allowed to move the flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 5)->unique();          // tr, en, de, fr, it
            $table->string('name', 60);                   // Türkçe
            $table->string('native_name', 60)->nullable(); // Türkçe (kendi dilinde)
            $table->string('flag', 8)->nullable();        // 🇹🇷
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
