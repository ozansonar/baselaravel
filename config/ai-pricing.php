<?php

declare(strict_types=1);

/**
 * AI servis maliyet tablosu — Cost Report Service ve runtime cost hesaplaması
 * tarafından kullanılır.
 *
 * KAYNAK: https://ai.google.dev/pricing (Gemini) — fiyatlar USD bazında.
 *
 * GENELLEMELER:
 * - Text generation: input/output token bazlı; basitleştirme için "blended"
 *   ortalama rate kullanılır ($/1M token). Tek token_count ile hesap kolay,
 *   marj ±%10 kabul edilebilir.
 * - Image generation: çağrı başına sabit fiyat (pipeline'da zaten cost_usd
 *   alanı `ai_generated_images` tablosunda doğrudan AiImageGenerator'da set
 *   ediliyor — burada referans amaçlı tutulur).
 *
 * PRICING DEĞIŞIRSE: Sadece bu dosyayı güncelle. Cache 5 dk TTL.
 */

return [
    /*
     * Gemini text models — blended rate ($/1M token).
     * Hesap: cost = (token_count / 1_000_000) × rate
     *
     * NOT: Gemini bedava tier'ı var (60 RPM Flash-Lite); ancak bu tier'dan
     * çıkıldığında otomatik faturalandırma başlar. Defansif olarak hesap.
     */
    'text_models' => [
        // Flash-Lite — en ucuz, blog/hashtag üretimi için ideal
        'gemini-2.5-flash-lite'              => 0.20,
        'gemini-2.0-flash-lite'              => 0.20,

        // Flash — orta seviye
        'gemini-2.5-flash'                   => 1.50,
        'gemini-2.0-flash'                   => 1.50,
        'gemini-2.5-flash-image'             => 1.50,
        'gemini-2.5-flash-image-preview'     => 1.50,

        // Pro — yüksek kalite
        'gemini-2.5-pro'                     => 6.00,
        'gemini-1.5-pro'                     => 6.00,

        // Bilinmeyen model için fallback
        '_default'                           => 1.00,
    ],

    /*
     * Image generation — çağrı başına USD.
     * Bu tablo runtime cost hesabı için DEĞİL (AiImageGenerator kendi
     * sabit array'ini kullanıyor); rapor sayfasında "Şu modelin birim
     * fiyatı" gösterimi için referans amaçlı tutulur.
     */
    'image_models' => [
        'gemini-2.5-flash-image'         => 0.04,
        'gemini-2.5-flash-image-preview' => 0.04,
        'gemini-3.1-flash-image-preview' => 0.07,
        'gemini-3-pro-image-preview'     => 0.10,
        'imagen-3.0-generate-001'        => 0.04,
        'imagen-3.0-generate-002'        => 0.04,
        '_default'                       => 0.05,
    ],

    /*
     * Bütçe ayarları default değerleri (Setting'ler override eder).
     */
    'defaults' => [
        'monthly_budget_usd'        => 50.00,  // Aylık üst limit (görsel + text dahil)
        'alert_threshold_percent'   => 80,     // %80'de sarı uyarı
        'block_when_exceeded'       => true,   // %100'de yeni AI çağrısı engelle (görsel)
    ],

    /*
     * Cache TTL — rapor servisi için (5 dk = 300 sn).
     * Cron blog her 6 saatte bir çalışıyor, bulk import nadiren →
     * 5dk yeterli, kullanıcı dashboard yenilemeden taze veri görür.
     */
    'cache_ttl_seconds' => 300,
];
