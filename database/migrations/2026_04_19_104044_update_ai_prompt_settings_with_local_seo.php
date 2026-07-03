<?php

declare(strict_types=1);

use App\Models\AiPromptSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Yerel SEO odaklı prompt güncellemesi.
     *
     * Çorum, Büyük Palabıyık Köyü ve İç Anadolu bölgesi referanslarını
     * her AI üretilen blog yazısına zorunlu kılar — "çorum köy ürünleri",
     * "çorum çiftlik" gibi yerel aramalarda görünürlük için.
     */
    public function up(): void
    {
        if (! DB::table('ai_prompt_settings')->where('id', 1)->exists()) {
            return;
        }

        DB::table('ai_prompt_settings')
            ->where('id', 1)
            ->update([
                'system_instruction' => AiPromptSetting::defaultSystemInstruction(),
                'updated_at'         => now(),
            ]);
    }

    public function down(): void
    {
        // Forward-only — yerel SEO geri alınmaz.
    }
};
