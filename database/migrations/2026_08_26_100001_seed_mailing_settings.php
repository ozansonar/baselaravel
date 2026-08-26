<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sending limits live in settings so they can be tuned without a deploy.
 *
 * The hourly limit is the one that matters: mail hosts throttle or blacklist an
 * account that empties a list in one burst, so the dispatcher spreads a
 * campaign across the hour instead.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{key: string, value: string, type: string}>
     */
    private const SETTINGS = [
        ['key' => 'mail_hourly_limit',    'value' => '100', 'type' => 'text'],
        ['key' => 'mail_batch_max',       'value' => '0',   'type' => 'text'],
        ['key' => 'mail_max_attempts',    'value' => '3',   'type' => 'text'],
        ['key' => 'newsletter_enabled',   'value' => '1',   'type' => 'boolean'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::SETTINGS as $setting) {
            if (DB::table('settings')->where('key', $setting['key'])->exists()) {
                continue;
            }

            DB::table('settings')->insert($setting + [
                'group'      => 'mail',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', array_column(self::SETTINGS, 'key'))
            ->delete();
    }
};
