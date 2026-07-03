<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $mailSettings = [
            ['key' => 'mail_mailer',       'value' => 'smtp',  'group' => 'mail', 'type' => 'text'],
            ['key' => 'mail_host',         'value' => null,     'group' => 'mail', 'type' => 'text'],
            ['key' => 'mail_port',         'value' => '587',    'group' => 'mail', 'type' => 'text'],
            ['key' => 'mail_username',     'value' => null,     'group' => 'mail', 'type' => 'text'],
            ['key' => 'mail_password',     'value' => null,     'group' => 'mail', 'type' => 'password'],
            ['key' => 'mail_encryption',   'value' => 'tls',    'group' => 'mail', 'type' => 'text'],
            ['key' => 'mail_from_address',  'value' => null,       'group' => 'mail', 'type' => 'text'],
            ['key' => 'mail_from_name',     'value' => null,       'group' => 'mail', 'type' => 'text'],
            ['key' => 'mail_logo',          'value' => null,       'group' => 'mail', 'type' => 'image'],
            ['key' => 'mail_mode',          'value' => 'normal',   'group' => 'mail', 'type' => 'text'],
            ['key' => 'mail_test_addresses','value' => null,       'group' => 'mail', 'type' => 'text'],
        ];

        $now = now();

        foreach ($mailSettings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'mail')
            ->whereIn('key', [
                'mail_mailer', 'mail_host', 'mail_port', 'mail_username',
                'mail_password', 'mail_encryption', 'mail_from_address', 'mail_from_name',
                'mail_logo', 'mail_mode', 'mail_test_addresses',
            ])
            ->delete();
    }
};
