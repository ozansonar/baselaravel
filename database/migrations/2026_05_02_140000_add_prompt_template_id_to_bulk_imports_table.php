<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk import için form-level (default) prompt template seçimi.
 * - null = template kullanılmıyor (mevcut davranış: gorsel_degeri/konu raw gönderilir)
 * - dolu = bu template tüm gorsel_kaynagi=ai satırları için default; Excel'deki
 *          'prompt_template' kolonu varsa o satıra özel override edebilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_imports', function (Blueprint $table): void {
            $table->foreignId('prompt_template_id')
                ->nullable()
                ->after('zip_path')
                ->constrained('ai_image_prompts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bulk_imports', function (Blueprint $table): void {
            $table->dropForeign(['prompt_template_id']);
            $table->dropColumn('prompt_template_id');
        });
    }
};
