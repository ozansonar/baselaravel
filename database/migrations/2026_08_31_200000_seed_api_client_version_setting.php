<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mobil uygulamanın en eski desteklenen sürümü.
 *
 * Boş bırakıldığında hiçbir istemci zorlanmıyor; bir değer yazıldığında
 * (örneğin 1.4.0) daha eski sürümler /health ucunda "güncelle" cevabı alıyor.
 *
 * Ayar panelde duruyor çünkü kararı veren kişi geliştirici değil: sunucu
 * sözleşmesi değiştiğinde eski uygulamaların ne zaman kesileceği işletmenin
 * kararı.
 */
return new class extends Migration
{
    private const KEY = 'api_minimum_client_version';

    public function up(): void
    {
        if (DB::table('settings')->where('key', self::KEY)->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'key'        => self::KEY,
            'value'      => null,
            'group'      => 'appearance',
            'type'       => 'text',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
    }
};
