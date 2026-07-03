<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::updateOrCreate(
            ['key' => 'ai_image_model'],
            ['value' => 'gemini-2.5-flash-image', 'group' => 'ai', 'type' => 'text'],
        );

        Setting::updateOrCreate(
            ['key' => 'ai_image_fallback_models'],
            ['value' => 'gemini-3.1-flash-image-preview,gemini-3-pro-image-preview,gemini-2.0-flash-preview-image-generation,gemini-2.5-flash-image-preview,gemini-2.0-flash-exp', 'group' => 'ai', 'type' => 'text'],
        );
    }

    public function down(): void
    {
        Setting::where('key', 'ai_image_fallback_models')->delete();
    }
};
