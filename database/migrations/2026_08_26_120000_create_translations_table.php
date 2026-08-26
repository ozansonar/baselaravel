<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel-editable interface texts.
 *
 * The lang/{locale}/*.php files stay the shipped defaults; this table holds
 * only what an admin has actually changed. Two reasons it works this way
 * rather than rewriting the files:
 *
 *  - A deploy is a git pull. Writing edits into lang/ means every deploy
 *    silently throws them away.
 *  - Keeping the file as the default makes "reset to default" possible, the
 *    same way mail templates already work.
 *
 * Reads cost nothing extra: the overrides for a group are cached as one array
 * and merged over the file when Laravel loads that group — once per request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table): void {
            $table->id();
            $table->string('locale', 5);
            // The translation file the key lives in, e.g. "site".
            $table->string('group', 60);
            // Dot path inside that file, e.g. "nav.home".
            $table->string('key', 191);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['locale', 'group', 'key']);
            // The loader's only query: every override of one group in one language.
            $table->index(['locale', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
