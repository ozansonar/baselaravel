<?php

declare(strict_types=1);

use App\Models\AiPromptSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Süt zinciri odaklı sade ürün listesine geçiş — blog AI havuzu 17 üründen
 * 6 ürüne indirildi (markanın "Çorum süt ürünleri uzmanı" konumunu
 * güçlendirmek için). Yancı ürünler (salça, pekmez, bulgur, yufka,
 * kurban/adak) sitede satılmaya devam eder, sadece blog AI üretmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('ai_prompt_settings')->where('id', 1)->exists()) {
            return;
        }

        DB::table('ai_prompt_settings')
            ->where('id', 1)
            ->update([
                'products'   => json_encode(
                    AiPromptSetting::defaultProducts(),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Forward-only — eski 17 ürünlük dağınık listeye geri dönmek istemiyoruz.
    }
};
