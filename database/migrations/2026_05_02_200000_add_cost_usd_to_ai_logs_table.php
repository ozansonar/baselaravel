<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AiLog (text generation log'u) için maliyet alanı.
 *
 * Mevcut: token_count + used_model var ama cost yok.
 * Yeni: token_count × pricing rate → cost_usd hesaplanır + saklanır.
 *
 * Aynı yapıyı ai_generated_images.cost_usd zaten var (görsel için).
 * Bu kolon AI Cost Report Service'in tek bir source-of-truth ile
 * text + image toplam maliyetini hesaplamasını sağlar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_logs', function (Blueprint $table): void {
            $table->decimal('cost_usd', 10, 6)
                ->nullable()
                ->after('token_count')
                ->comment('AI text generation maliyeti USD (config/ai-pricing.php rate × token_count)');
        });
    }

    public function down(): void
    {
        Schema::table('ai_logs', function (Blueprint $table): void {
            $table->dropColumn('cost_usd');
        });
    }
};
