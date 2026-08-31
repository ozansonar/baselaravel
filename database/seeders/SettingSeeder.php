<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'site_name',        'value' => config('app.name'),                                 'group' => 'general', 'type' => 'text'],
            ['key' => 'site_title',       'value' => config('app.name'),                                 'group' => 'general', 'type' => 'text'],
            ['key' => 'site_description', 'value' => 'Yeniden kullanılabilir Laravel başlangıç altyapısı.', 'group' => 'general', 'type' => 'textarea'],
            ['key' => 'site_keywords',    'value' => 'laravel,base,starter',                             'group' => 'general', 'type' => 'text'],
            ['key' => 'site_logo',        'value' => null, 'group' => 'general',    'type' => 'image'],
            ['key' => 'site_favicon',     'value' => null, 'group' => 'general',    'type' => 'image'],
            ['key' => 'footer_text',      'value' => '© ' . date('Y') . ' ' . config('app.name') . '. Tüm hakları saklıdır.', 'group' => 'general', 'type' => 'text'],
            ['key' => 'footer_credit',    'value' => null, 'group' => 'general',    'type' => 'text'],

            // Contact
            ['key' => 'contact_email',          'value' => 'info@example.com',        'group' => 'contact', 'type' => 'text'],
            ['key' => 'contact_phone',          'value' => null,                      'group' => 'contact', 'type' => 'text'],
            ['key' => 'contact_phone_2',        'value' => null,                      'group' => 'contact', 'type' => 'text'],
            ['key' => 'contact_address',        'value' => null,                      'group' => 'contact', 'type' => 'textarea'],
            ['key' => 'contact_map_embed',      'value' => null,                      'group' => 'contact', 'type' => 'textarea'],
            ['key' => 'working_hours_weekday',  'value' => '09:00 - 18:00',           'group' => 'contact', 'type' => 'text'],
            ['key' => 'working_hours_saturday', 'value' => '10:00 - 16:00',           'group' => 'contact', 'type' => 'text'],
            ['key' => 'working_hours_sunday',   'value' => 'Kapalı',                  'group' => 'contact', 'type' => 'text'],

            // Social media
            ['key' => 'social_facebook',  'value' => null, 'group' => 'social', 'type' => 'text'],
            ['key' => 'social_instagram', 'value' => null, 'group' => 'social', 'type' => 'text'],
            ['key' => 'social_twitter',   'value' => null, 'group' => 'social', 'type' => 'text'],
            ['key' => 'social_youtube',   'value' => null, 'group' => 'social', 'type' => 'text'],
            ['key' => 'social_whatsapp',  'value' => null, 'group' => 'social', 'type' => 'text'],
            ['key' => 'social_tiktok',    'value' => null, 'group' => 'social', 'type' => 'text'],

            // SEO & Meta
            ['key' => 'seo_home_title',        'value' => null, 'group' => 'seo', 'type' => 'text'],
            ['key' => 'seo_home_description',  'value' => null, 'group' => 'seo', 'type' => 'textarea'],
            ['key' => 'google_analytics_id',   'value' => null, 'group' => 'seo', 'type' => 'text'],
            ['key' => 'google_tag_manager_id', 'value' => null, 'group' => 'seo', 'type' => 'text'],
            ['key' => 'facebook_pixel_id',     'value' => null, 'group' => 'seo', 'type' => 'text'],
            ['key' => 'custom_head_code',      'value' => null, 'group' => 'seo', 'type' => 'textarea'],
            ['key' => 'og_title',              'value' => null, 'group' => 'seo', 'type' => 'text'],
            ['key' => 'og_description',        'value' => null, 'group' => 'seo', 'type' => 'textarea'],
            ['key' => 'og_image',              'value' => null, 'group' => 'seo', 'type' => 'image'],

            // Notifications
            ['key' => 'admin_notification_email', 'value' => 'info@example.com', 'group' => 'contact', 'type' => 'text'],

            // Appearance
            ['key' => 'registration_enabled', 'value' => '1', 'group' => 'appearance', 'type' => 'boolean'],
            ['key' => 'two_factor_required_admins', 'value' => '0', 'group' => 'appearance', 'type' => 'boolean'],

            // Uygulama (PWA) — telefona kurulabilirlik.
            ['key' => 'pwa_enabled',          'value' => '1',       'group' => 'appearance', 'type' => 'boolean'],
            ['key' => 'pwa_short_name',       'value' => null,      'group' => 'appearance', 'type' => 'text'],
            ['key' => 'pwa_theme_color',      'value' => '#4f46e5', 'group' => 'appearance', 'type' => 'text'],
            ['key' => 'pwa_background_color', 'value' => '#ffffff', 'group' => 'appearance', 'type' => 'text'],
            ['key' => 'pwa_icon',             'value' => null,      'group' => 'appearance', 'type' => 'image'],
            ['key' => 'maintenance_mode',     'value' => '0', 'group' => 'appearance', 'type' => 'boolean'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }
}
