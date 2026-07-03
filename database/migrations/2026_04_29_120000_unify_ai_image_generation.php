<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unify all AI image generation behind a single base service.
 *
 * Schema changes:
 *   - ai_generated_images: add source/width/height/cost_usd/attempt_count/
 *     blog_post_id/instagram_post_id/request_payload/response_payload
 *
 * Settings cleanup:
 *   - Remove obsolete `ai_image_primary_model` (was used by legacy
 *     InstagramImageAiService — now everything reads `ai_image_model`)
 *   - Sanitize `ai_image_fallback_models` value: drop dead models that
 *     return 404 from the v1beta API (gemini-2.0-flash-exp,
 *     gemini-2.0-flash-preview-image-generation)
 */
return new class extends Migration
{
    /** @var list<string> Models that no longer exist on v1beta — must be stripped from fallback list */
    private const array DEAD_MODELS = [
        'gemini-2.0-flash-exp',
        'gemini-2.0-flash-preview-image-generation',
        'imagen-3.0-generate-001',
        'imagen-3.0-generate-002',
    ];

    private const string CLEAN_FALLBACK_DEFAULT = 'gemini-3.1-flash-image-preview,gemini-3-pro-image-preview,gemini-2.5-flash-image-preview';

    public function up(): void
    {
        Schema::table('ai_generated_images', function (Blueprint $table): void {
            $table->string('source', 40)->nullable()->after('aspect_ratio')->index();
            $table->unsignedInteger('width')->nullable()->after('source');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->decimal('cost_usd', 10, 6)->nullable()->after('generation_time_ms');
            $table->unsignedTinyInteger('attempt_count')->default(0)->after('cost_usd');
            $table->unsignedBigInteger('blog_post_id')->nullable()->after('attempt_count')->index();
            $table->unsignedBigInteger('instagram_post_id')->nullable()->after('blog_post_id')->index();
            $table->longText('request_payload')->nullable()->after('instagram_post_id');
            $table->longText('response_payload')->nullable()->after('request_payload');
        });

        DB::transaction(function (): void {
            // Drop legacy duplicate setting key
            Setting::where('key', 'ai_image_primary_model')->delete();

            // Sanitize stored fallback list — keep user customizations but strip dead models
            $fallbackSetting = Setting::where('key', 'ai_image_fallback_models')->first();
            if ($fallbackSetting !== null) {
                $sanitized = $this->stripDeadModels((string) $fallbackSetting->value);
                $fallbackSetting->update([
                    'value' => $sanitized !== '' ? $sanitized : self::CLEAN_FALLBACK_DEFAULT,
                    'group' => 'ai',
                    'type'  => 'text',
                ]);
            } else {
                Setting::create([
                    'key'   => 'ai_image_fallback_models',
                    'value' => self::CLEAN_FALLBACK_DEFAULT,
                    'group' => 'ai',
                    'type'  => 'text',
                ]);
            }

            // If primary model is dead too — replace with safe default
            $primarySetting = Setting::where('key', 'ai_image_model')->first();
            if ($primarySetting !== null && in_array(trim((string) $primarySetting->value), self::DEAD_MODELS, true)) {
                $primarySetting->update(['value' => 'gemini-2.5-flash-image']);
            }
        });

        Setting::clearSettingsCache();
    }

    public function down(): void
    {
        Schema::table('ai_generated_images', function (Blueprint $table): void {
            $table->dropIndex(['source']);
            $table->dropIndex(['blog_post_id']);
            $table->dropIndex(['instagram_post_id']);
            $table->dropColumn([
                'source',
                'width',
                'height',
                'cost_usd',
                'attempt_count',
                'blog_post_id',
                'instagram_post_id',
                'request_payload',
                'response_payload',
            ]);
        });

        // Settings rollback intentionally minimal — re-creating obsolete primary key is not desired
        Setting::clearSettingsCache();
    }

    private function stripDeadModels(string $csv): string
    {
        $tokens = array_filter(array_map('trim', explode(',', $csv)));
        $kept = array_values(array_filter(
            $tokens,
            static fn (string $model): bool => $model !== '' && ! in_array($model, self::DEAD_MODELS, true),
        ));

        return implode(',', $kept);
    }
};
