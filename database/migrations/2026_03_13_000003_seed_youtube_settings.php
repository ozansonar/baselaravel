<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, array{key: string, value: string|null, group: string, type: string}>
     */
    private array $settings = [
        ['key' => 'youtube_api_key', 'value' => null, 'group' => 'youtube', 'type' => 'password'],
        ['key' => 'youtube_channel_id', 'value' => null, 'group' => 'youtube', 'type' => 'text'],
    ];

    public function up(): void
    {
        foreach ($this->settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'group' => $setting['group'],
                    'type'  => $setting['type'],
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', array_column($this->settings, 'key'))
            ->delete();
    }
};
