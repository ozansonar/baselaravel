<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * Zamanlanmış raporun ne sıklıkla gideceği.
 *
 * Saat seçimi bilerek yok: paylaşımlı hosting'de cron dakikada bir çalışıyor
 * ve rapor üretimi kısa sürüyor; kullanıcıya "sabah 7'de" gibi bir söz vermek,
 * tutulmasını sunucunun yüküne bırakmak olurdu. Gün başına bir kez gönderilir.
 */
enum ReportFrequency: string
{
    case Daily   = 'daily';
    case Weekly  = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily   => 'Her gün',
            self::Weekly  => 'Her pazartesi',
            self::Monthly => 'Ayın ilk günü',
        };
    }

    /**
     * Bugün bu sıklık için gönderim günü mü?
     */
    public function dueOn(Carbon $date): bool
    {
        return match ($this) {
            self::Daily   => true,
            self::Weekly  => $date->isMonday(),
            self::Monthly => $date->day === 1,
        };
    }
}
