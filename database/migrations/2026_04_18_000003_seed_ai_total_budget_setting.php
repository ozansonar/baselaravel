<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::updateOrCreate(
            ['key' => 'ai_total_budget_seconds'],
            ['value' => '240', 'group' => 'ai', 'type' => 'text'],
        );

        Setting::clearSettingsCache();
    }

    public function down(): void
    {
        Setting::where('key', 'ai_total_budget_seconds')->delete();
        Setting::clearSettingsCache();
    }
};
