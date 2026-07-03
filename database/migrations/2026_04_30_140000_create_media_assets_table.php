<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MediaLibrary modülü — kuratoryal görsel kütüphanesi.
 *
 * Otomatik blog cron'u (ve ileride sosyal medya akışı) AI üretmek yerine
 * bu kütüphaneden rastgele görsel seçer. Tek format: 1:1 kare 1500×1500
 * (validation seviyesinde garanti edilir, bu tabloda enforce edilmez).
 *
 * Görsel-ürün eşleşmesi:
 *   - product_name = null         → "Genel" havuz (ürün-bağımsız fallback)
 *   - product_name = "Süt" vb.    → AiPromptSetting.products listesindeki ürün
 *
 * Cron seçim mantığı (BlogCoverImageService):
 *   1. WHERE product_name = AiLog.selected_product
 *   2. Boşsa: WHERE product_name IS NULL
 *   3. Boşsa: hardcoded placeholder (AI çağrısı YOK)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('product_name', 191)->nullable();
            $table->string('title', 191)->nullable();           // admin için açıklama / SEO alt text
            $table->string('image_path', 255);                  // public/uploads/media-library/...
            $table->unsignedInteger('width')->nullable();       // doğrulama amaçlı (kare 1500×1500 olmalı)
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Smart selection için kompozit index — pickFor() bu kombinasyonla sorgular.
            $table->index(['product_name', 'is_active'], 'media_assets_product_active_idx');
            // LRU sıralaması (last_used_at ASC + usage_count ASC) için tek tek index'ler yeterli.
            $table->index('last_used_at');
            $table->index('usage_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
