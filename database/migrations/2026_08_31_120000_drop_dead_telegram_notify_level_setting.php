<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ölü `telegram_notify_level` ayarını siler.
 *
 * Ayar panelde kaydediliyordu ama kodda hiçbir yerde okunmuyordu. Sunduğu
 * seçim — "her başarısızlıkta" ya da "yalnız 3/3 deneme sonunda" — bu projede
 * karşılığı olmayan bir yeniden deneme mekanizmasını anlatıyordu: QueueRunner
 * bir işi bir kez çalıştırıyor, patlarsa doğrudan başarısız sayıyor.
 *
 * Alan ekrandan kaldırıldığı için satır bir daha düzenlenemez hâle geldi;
 * bırakılsaydı kimsenin okumadığı ve kimsenin silemeyeceği bir kayıt olarak
 * kalırdı.
 */
return new class extends Migration
{
    private const KEY = 'telegram_notify_level';

    public function up(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
    }

    public function down(): void
    {
        $exists = DB::table('settings')->where('key', self::KEY)->exists();

        if ($exists) {
            return;
        }

        // Enum silindiği için tarihsel varsayılan burada düz metin duruyor;
        // grup da ayar ekranının bu alanı kaydettiği gruptu.
        DB::table('settings')->insert([
            'key'        => self::KEY,
            'value'      => 'permanent_only',
            'group'      => 'telegram',
            'type'       => 'text',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
