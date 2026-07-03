<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key' => 'guest_checkout_enabled',      'value' => '1', 'group' => 'shipping', 'type' => 'boolean'],
            ['key' => 'order_whatsapp_notification', 'value' => '1', 'group' => 'shipping', 'type' => 'boolean'],
            ['key' => 'admin_notification_email',    'value' => null, 'group' => 'contact',  'type' => 'text'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }

    public function down(): void
    {
        Setting::whereIn('key', [
            'guest_checkout_enabled',
            'order_whatsapp_notification',
            'admin_notification_email',
        ])->delete();
    }
};
