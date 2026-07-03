<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Görsel üretimi için TEXT üretiminden tamamen ayrı bir API key
     * ve aktif/pasif toggle ekler. Böylece:
     * - Görsel key silinirse text üretimi etkilenmez.
     * - Görsel üretimi pasife alınabilir (key silmeden).
     */
    public function up(): void
    {
        Setting::updateOrCreate(
            ['key' => 'ai_image_api_key'],
            ['value' => null, 'group' => 'ai', 'type' => 'text'],
        );

        Setting::updateOrCreate(
            ['key' => 'ai_image_enabled'],
            ['value' => '0', 'group' => 'ai', 'type' => 'boolean'],
        );
    }

    public function down(): void
    {
        Setting::whereIn('key', ['ai_image_api_key', 'ai_image_enabled'])->delete();
    }
};
