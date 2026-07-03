<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Log'a "seçilen ürün" alanı ekler. product_name eski anlamını
 * korur (içerik başlığı / topic), selected_product ayrıca admin'in
 * AiPromptSetting.products listesinden cron tarafından rastgele
 * seçilen ürünün adını tutar.
 *
 * Admin AI Loglar sayfasında HEM içerik başlığı HEM seçilen ürün
 * yan yana görünür.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_logs', function (Blueprint $table): void {
            $table->string('selected_product', 191)
                ->nullable()
                ->after('product_name');
            $table->index('selected_product');
        });
    }

    public function down(): void
    {
        Schema::table('ai_logs', function (Blueprint $table): void {
            $table->dropIndex(['selected_product']);
            $table->dropColumn('selected_product');
        });
    }
};
