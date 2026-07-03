<?php

declare(strict_types=1);

use App\Models\AiPromptSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * SEO odaklı yeni prompt sürümüne geçiş.
     *
     * Eski prompt'taki çelişkileri (link/görsel/ürün-rastgele), çift H1 riskini,
     * eksik min kelime sayısını ve hedef anahtar kelime belirsizliğini düzeltir.
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
                'user_prompt'        => AiPromptSetting::defaultUserPrompt(),
                'updated_at'         => now(),
            ]);
    }

    public function down(): void
    {
        // Eski prompt'a geri dönüş yok — yeni sürüm SEO açısından zorunlu düzeltme.
    }
};
