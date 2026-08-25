<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A slider translation may be saved before its own artwork exists.
 *
 * Every other content table already allows a null image; sliders did not, which
 * blocked adding a language until artwork for it was ready. A translation
 * without its own file inherits the default language's image, so the slider
 * still renders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sliders', function (Blueprint $table): void {
            $table->string('image')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sliders', function (Blueprint $table): void {
            $table->string('image')->nullable(false)->change();
        });
    }
};
