<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Turns navigation multilingual.
 *
 * The rest of the content tables already work this way, and navigation is the
 * most visible text on the site — an English visitor was still reading
 * "Anasayfa · Hakkımızda · İletişim".
 *
 * A menu belongs to one language and carries its own item tree, so a language
 * may legitimately have a different navigation (fewer pages, a different
 * order). Items are linked across languages by lang_group_id so the panel can
 * tell which item is the translation of which.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaultLocale = $this->defaultLocale();

        foreach (['menus', 'menu_items'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($defaultLocale): void {
                $blueprint->string('locale', 5)->default($defaultLocale)->after('id')->index();
                $blueprint->uuid('lang_group_id')->nullable()->after('locale')->index();
            });

            $this->backfill($table, $defaultLocale);

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->unique(['locale', 'lang_group_id'], $table . '_locale_group_unique');
            });
        }

        // A location is looked up per language on every page render.
        Schema::table('menus', function (Blueprint $blueprint): void {
            $blueprint->index(['locale', 'location'], 'menus_locale_location_index');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $blueprint): void {
            $blueprint->dropIndex('menus_locale_location_index');
        });

        // Only the default language's navigation survives; the translations
        // were rows of their own.
        $defaultLocale = $this->defaultLocale();
        DB::table('menu_items')->where('locale', '!=', $defaultLocale)->delete();
        DB::table('menus')->where('locale', '!=', $defaultLocale)->delete();

        foreach (['menus', 'menu_items'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropUnique($table . '_locale_group_unique');
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropIndex(['locale']);
                $blueprint->dropIndex(['lang_group_id']);
                $blueprint->dropColumn(['locale', 'lang_group_id']);
            });
        }
    }

    private function backfill(string $table, string $defaultLocale): void
    {
        DB::table($table)->whereNull('lang_group_id')->orderBy('id')->each(
            static function (object $row) use ($table, $defaultLocale): void {
                DB::table($table)->where('id', $row->id)->update([
                    'locale'        => $defaultLocale,
                    'lang_group_id' => (string) Str::uuid(),
                ]);
            },
        );
    }

    private function defaultLocale(): string
    {
        if (! Schema::hasTable('languages')) {
            return (string) config('app.locale', 'tr');
        }

        $code = DB::table('languages')->where('is_default', true)->value('code');

        return is_string($code) ? $code : (string) config('app.locale', 'tr');
    }
};
