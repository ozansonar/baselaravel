<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Blog kapak görseli kaynağı tercihi.
 *
 *   media_library         (default) → MediaLibrary'den seç, sıfır AI maliyeti
 *   media_library_then_ai → kütüphane boşsa AI fallback
 *   ai                    → eski AI akışı (Imagen/Gemini, ~$0.04/blog)
 *
 * BlogCoverImageService::generateForBlog() bu değere göre dallanır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::updateOrCreate(
            ['key' => 'blog_cover_source'],
            [
                'value' => 'media_library',
                'group' => 'ai',
                'type'  => 'text',
            ],
        );

        Setting::clearSettingsCache();
    }

    public function down(): void
    {
        Setting::where('key', 'blog_cover_source')->delete();
        Setting::clearSettingsCache();
    }
};
