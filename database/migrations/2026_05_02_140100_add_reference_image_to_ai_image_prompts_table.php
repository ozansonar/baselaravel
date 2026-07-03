<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Image Prompt template'lerine opsiyonel "referans görsel" eklenir.
 *
 * Kullanım: Logo, ürün fotoğrafı veya marka öğesi yüklenir; template'in prompt
 * metninde AI'a bu görselin nasıl kullanılacağı yazılır (örn: "Bu logoyu
 * şişenin etiketine yerleştir"). Generator image-to-image flow ile çalışır
 * (AiImageGenerator::enhance) — referans görsel AI'a aynen iletilir, AI prompt
 * talimatına göre içeriğe entegre eder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_image_prompts', function (Blueprint $table): void {
            $table->string('reference_image_path', 512)
                ->nullable()
                ->after('prompt')
                ->comment('public/uploads/ altındaki referans görsel yolu — image-to-image flow için AI\'a yollanır');
        });
    }

    public function down(): void
    {
        Schema::table('ai_image_prompts', function (Blueprint $table): void {
            $table->dropColumn('reference_image_path');
        });
    }
};
