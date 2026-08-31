<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Yöneticiler için iki adımlı doğrulama zorunlu" ayarı.
 *
 * Tohumlayıcı yalnız taze kurulumlarda çalışıyor; bu satır olmadan mevcut
 * kurulumlarda ayar ekranda görünür ama kaydedilecek bir kaydı olmazdı.
 *
 * Varsayılan kapalı: göç, çalışan bir kurulumun bütün yöneticilerini bir
 * sonraki girişte kurulum ekranına göndermemeli. Açmak yöneticinin kararı.
 */
return new class extends Migration
{
    private const KEY = 'two_factor_required_admins';

    public function up(): void
    {
        if (DB::table('settings')->where('key', self::KEY)->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'key'        => self::KEY,
            'value'      => '0',
            'group'      => 'appearance',
            'type'       => 'boolean',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
    }
};
