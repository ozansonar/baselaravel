<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * AI maliyet kontrol önlemleri.
 *
 * Sorun: 13 günde ₺89 Gemini API faturası. Beklenen ~5 TL.
 * Olası ana neden: AiService::callApiWithRetry() içinde her fail için
 * ai_max_attempts (default 5) × fallback model sayısı (5) = 25 hit retry storm.
 *
 * Bu migration 2 katmanlı koruma ekler:
 *  1. Retry limitini düşür (5 → 2)         — paniğe karşı brake
 *  2. Aylık $3 sert bütçe + block flag      — toplam üst sınır
 *
 * Idempotent: Setting::updateOrCreate ile çalışır, mevcut değer override edilir.
 * Down(): değerleri başlangıç default'larına geri çevirir.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mevcut durum analizi:
        //  - Kullanıcı zaten ai_monthly_budget_usd=$10 set etmiş (9% kullanım)
        //  - May 2'de instagram-create bulk üretiminden $0.58 spike yaşandı (1× pro model)
        //  - Asıl risk: AiService retry storm (max_attempts=5 × 5 fallback model = 25 hit)
        //
        // Bu migration TEK aksiyon alır: retry'ı 5 → 2'ye düşürür.
        // Diğer bütçe ayarları zaten admin tarafından set edilmiş, dokunmayalım.
        $settings = [
            [
                'key'   => 'ai_max_attempts',
                'value' => '2',
                'group' => 'ai',
                'type'  => 'text',
            ],
        ];

        foreach ($settings as $row) {
            Setting::updateOrCreate(
                ['key' => $row['key']],
                ['value' => $row['value'], 'group' => $row['group'], 'type' => $row['type']],
            );
        }
    }

    public function down(): void
    {
        // Default değere geri çevir
        Setting::where('key', 'ai_max_attempts')->update(['value' => '5']);
    }
};
