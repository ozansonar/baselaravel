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
            ['key' => 'site_name',        'value' => 'Orhan Babanın Çiftliği',                          'group' => 'general', 'type' => 'text'],
            ['key' => 'site_title',       'value' => 'Orhan Babanın Çiftliği',                          'group' => 'general', 'type' => 'text'],
            ['key' => 'site_description', 'value' => 'Özlem duyduğunuz doğal organik köy ürünlerini sizlere sunuyoruz.', 'group' => 'general', 'type' => 'textarea'],
            ['key' => 'site_keywords',    'value' => 'orhanbabaninciftligi.com,orhan babanın çiftliği,orhan sonar,çorum köy yumurtası,büyük palabıyık köyü,çorum palabıyık,çorum süt,süt ve süt ürünleri,çökelek,köy ürünleri,orhan gazi sonar,tereyag', 'group' => 'general', 'type' => 'text'],
            ['key' => 'site_logo',        'value' => null, 'group' => 'general',    'type' => 'image'],
            ['key' => 'site_favicon',     'value' => null, 'group' => 'general',    'type' => 'image'],
            ['key' => 'footer_text',      'value' => '© 2026 Orhan Babanın Çiftliği. Tüm hakları saklıdır.', 'group' => 'general', 'type' => 'text'],

            // Contact
            ['key' => 'contact_email',          'value' => 'iletisim@orhanbabaninciftligi.com',  'group' => 'contact', 'type' => 'text'],
            ['key' => 'contact_phone',          'value' => '+905059424124',                       'group' => 'contact', 'type' => 'text'],
            ['key' => 'contact_phone_2',        'value' => null,                                  'group' => 'contact', 'type' => 'text'],
            ['key' => 'contact_address',        'value' => 'Büyük Palabıyık Köyü Çorum/Merkez', 'group' => 'contact', 'type' => 'textarea'],
            ['key' => 'contact_map_embed',      'value' => null,                                  'group' => 'contact', 'type' => 'textarea'],
            ['key' => 'working_hours_weekday',  'value' => '08:00 - 18:00',                      'group' => 'contact', 'type' => 'text'],
            ['key' => 'working_hours_saturday', 'value' => '09:00 - 16:00',                      'group' => 'contact', 'type' => 'text'],
            ['key' => 'working_hours_sunday',   'value' => 'Kapalı',                             'group' => 'contact', 'type' => 'text'],

            // Social media
            ['key' => 'social_facebook',  'value' => 'https://www.facebook.com/orhanbabaninciftligi',     'group' => 'social', 'type' => 'text'],
            ['key' => 'social_instagram', 'value' => 'https://www.instagram.com/orhanbabaninciftligi',    'group' => 'social', 'type' => 'text'],
            ['key' => 'social_twitter',   'value' => null,                                                 'group' => 'social', 'type' => 'text'],
            ['key' => 'social_youtube',   'value' => 'https://www.youtube.com/@orhanbabaninciftligicorum', 'group' => 'social', 'type' => 'text'],
            ['key' => 'social_whatsapp',  'value' => '+905059424124',                                      'group' => 'social', 'type' => 'text'],
            ['key' => 'social_tiktok',    'value' => null,                                                 'group' => 'social', 'type' => 'text'],

            // Shipping & E-commerce
            ['key' => 'shipping_free_limit', 'value' => '500',   'group' => 'shipping', 'type' => 'number'],
            ['key' => 'shipping_fee',        'value' => '39.90', 'group' => 'shipping', 'type' => 'number'],
            ['key' => 'min_order_amount',    'value' => '100',   'group' => 'shipping', 'type' => 'number'],
            ['key' => 'currency',            'value' => 'TRY',   'group' => 'shipping', 'type' => 'text'],
            ['key' => 'cod_enabled',         'value' => '1',     'group' => 'shipping', 'type' => 'boolean'],

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

            // Checkout & Notifications
            ['key' => 'guest_checkout_enabled',      'value' => '1', 'group' => 'shipping', 'type' => 'boolean'],
            ['key' => 'order_whatsapp_notification', 'value' => '1', 'group' => 'shipping', 'type' => 'boolean'],
            ['key' => 'admin_notification_email',    'value' => 'iletisim@orhanbabaninciftligi.com', 'group' => 'contact', 'type' => 'text'],

            // Appearance
            ['key' => 'registration_enabled', 'value' => '1', 'group' => 'appearance', 'type' => 'boolean'],
            ['key' => 'maintenance_mode',     'value' => '0', 'group' => 'appearance', 'type' => 'boolean'],

            // AI Content
            ['key' => 'gemini_api_key',     'value' => null,                                                                               'group' => 'ai', 'type' => 'text'],
            ['key' => 'ai_primary_model',   'value' => 'gemini-2.5-flash',                                                                 'group' => 'ai', 'type' => 'text'],
            ['key' => 'ai_fallback_models', 'value' => 'gemini-2.0-flash,gemini-2.5-flash-lite,gemini-2.0-flash-lite,gemini-1.5-flash',    'group' => 'ai', 'type' => 'text'],
            ['key' => 'ai_max_attempts',    'value' => '5',                                                                                'group' => 'ai', 'type' => 'text'],
            ['key' => 'ai_timeout_seconds', 'value' => '90',                                                                               'group' => 'ai', 'type' => 'text'],
            ['key' => 'ai_initial_backoff', 'value' => '4',                                                                                'group' => 'ai', 'type' => 'text'],
            ['key' => 'ai_total_budget_seconds', 'value' => '240',                                                                         'group' => 'ai', 'type' => 'text'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }
}
