<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Turns the content tables multilingual.
 *
 * Each row belongs to exactly one language (locale) and the translations of the
 * same piece of content share a lang_group_id. That also means every
 * language-specific column — including the image — is naturally per language,
 * which matters when the artwork carries text.
 *
 * Slugs become unique per language rather than globally, so Turkish and English
 * may both use "contact".
 */
return new class extends Migration
{
    /**
     * Tables that get translated, and whether they carry a slug.
     *
     * @var array<string, bool>
     */
    private const TABLES = [
        'pages'              => true,
        'blog_posts'         => true,
        'blog_categories'    => true,
        'gallery_categories' => true,
        'gallery_items'      => false,
        'faqs'               => false,
        'sliders'            => false,
        'popups'             => false,
    ];

    public function up(): void
    {
        $defaultLocale = $this->defaultLocale();

        foreach (self::TABLES as $table => $hasSlug) {
            Schema::table($table, function (Blueprint $table) use ($defaultLocale): void {
                $table->string('locale', 5)->default($defaultLocale)->after('id')->index();
                $table->uuid('lang_group_id')->nullable()->after('locale')->index();
            });

            // Existing rows are the default-language version of their own group.
            $this->backfill($table, $defaultLocale);

            if ($hasSlug) {
                Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                    $blueprint->dropUnique($table . '_slug_unique');
                    $blueprint->unique(['locale', 'slug'], $table . '_locale_slug_unique');
                });
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->unique(['locale', 'lang_group_id'], $table . '_locale_group_unique');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $hasSlug) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropUnique($table . '_locale_group_unique');
            });

            if ($hasSlug) {
                Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                    $blueprint->dropUnique($table . '_locale_slug_unique');
                });

                // Only one row per group survives as a unique slug, so drop the
                // extra translations before restoring the global constraint.
                $this->keepDefaultLocaleRowsOnly($table);

                Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                    $blueprint->unique('slug', $table . '_slug_unique');
                });
            }

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

    private function keepDefaultLocaleRowsOnly(string $table): void
    {
        $defaultLocale = $this->defaultLocale();

        DB::table($table)->where('locale', '!=', $defaultLocale)->delete();
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
