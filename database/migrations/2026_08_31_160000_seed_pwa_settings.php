<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Uygulama (PWA) ayarları.
 *
 * Varsayılan açık: kurulabilirlik ziyaretçiye bir şey dayatmıyor — tarayıcı
 * yalnız "ana ekrana ekle" seçeneğini sunuyor, kimse istemezse hiçbir şey
 * değişmiyor. Kapatmak isteyen panelden kapatıyor.
 *
 * Renkler burada duruyor çünkü kurulan uygulamanın açılış ekranı ve başlık
 * çubuğu bunları kullanıyor; kodda sabit olsalardı her projede dosya
 * düzenlemek gerekirdi.
 */
return new class extends Migration
{
    /** @var array<string, array{value: string|null, type: string}> */
    private const SETTINGS = [
        'pwa_enabled'          => ['value' => '1', 'type' => 'boolean'],
        'pwa_short_name'       => ['value' => null, 'type' => 'text'],
        'pwa_theme_color'      => ['value' => '#4f46e5', 'type' => 'text'],
        'pwa_background_color' => ['value' => '#ffffff', 'type' => 'text'],
        'pwa_icon'             => ['value' => null, 'type' => 'image'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::SETTINGS as $key => $setting) {
            if (DB::table('settings')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('settings')->insert([
                'key'        => $key,
                'value'      => $setting['value'],
                'group'      => 'appearance',
                'type'       => $setting['type'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_keys(self::SETTINGS))->delete();
    }
};
