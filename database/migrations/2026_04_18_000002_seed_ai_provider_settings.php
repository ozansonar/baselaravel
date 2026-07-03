<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key' => 'ai_primary_model',   'value' => 'gemini-2.5-flash',                                                             'group' => 'ai', 'type' => 'text'],
            ['key' => 'ai_fallback_models', 'value' => 'gemini-2.0-flash,gemini-2.5-flash-lite,gemini-2.0-flash-lite,gemini-1.5-flash', 'group' => 'ai', 'type' => 'text'],
            ['key' => 'ai_max_attempts',    'value' => '5',                                                                            'group' => 'ai', 'type' => 'text'],
            ['key' => 'ai_timeout_seconds', 'value' => '90',                                                                           'group' => 'ai', 'type' => 'text'],
            ['key' => 'ai_initial_backoff', 'value' => '4',                                                                            'group' => 'ai', 'type' => 'text'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }

        Setting::clearSettingsCache();
    }

    public function down(): void
    {
        Setting::whereIn('key', [
            'ai_primary_model',
            'ai_fallback_models',
            'ai_max_attempts',
            'ai_timeout_seconds',
            'ai_initial_backoff',
        ])->delete();

        Setting::clearSettingsCache();
    }
};
