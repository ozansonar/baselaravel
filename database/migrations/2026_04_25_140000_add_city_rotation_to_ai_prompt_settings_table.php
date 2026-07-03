<?php

declare(strict_types=1);

use App\Models\AiPromptSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_prompt_settings', function (Blueprint $table) {
            // Şehir rotasyonu için hedef şehir listesi (örn: ["İstanbul","Ankara",...]).
            $table->json('target_cities')->nullable()->after('products');

            // Round-robin index — her cron çalıştırmasında atomic olarak artırılır.
            $table->unsignedInteger('city_rotation_index')->default(0)->after('target_cities');

            // N. çalıştırmada bir kez şehirsiz "genel" yazı üretilir (haftada 1).
            // Cron günde 4 kez çalıştığı için: 28 → haftada 1, 4 → günde 1.
            // 0 ise: hiç genel yazı üretme (her çalıştırma şehir hedefli).
            $table->unsignedSmallInteger('general_post_every_n_runs')->default(28)->after('city_rotation_index');
        });

        // ALTER TABLE ile yeni alan eklendiğinde mevcut satır(lar) için
        // target_cities NULL olarak gelir — singleton (id=1) kaydı zaten varsa
        // default şehir listesini hemen doldur, aksi halde cron rotasyonu hiçbir
        // şehri seçemez ve sadece "genel yazı" üretir.
        if (DB::table('ai_prompt_settings')->where('id', 1)->exists()) {
            DB::table('ai_prompt_settings')
                ->where('id', 1)
                ->update([
                    'target_cities' => json_encode(
                        AiPromptSetting::defaultTargetCities(),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('ai_prompt_settings', function (Blueprint $table) {
            $table->dropColumn(['target_cities', 'city_rotation_index', 'general_post_every_n_runs']);
        });
    }
};
