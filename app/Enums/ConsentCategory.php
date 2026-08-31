<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Çerez ve izleme rızasının kategorileri.
 *
 * Ayrım hukuki: zorunlu olanlar rıza istemez (sitenin çalışması için gerekli),
 * geri kalanı ziyaretçi açıkça izin vermeden çalışamaz.
 */
enum ConsentCategory: string
{
    /** Oturum, güvenlik jetonu, dil ve tema tercihi. Kapatılamaz. */
    case Necessary = 'necessary';

    /** Kendi ziyaret istatistiklerimiz ve Google Analytics. */
    case Analytics = 'analytics';

    /** Google Tag Manager ve içine konan her etiket. */
    case Marketing = 'marketing';

    public function label(): string
    {
        return match ($this) {
            self::Necessary => 'Zorunlu',
            self::Analytics => 'Analitik',
            self::Marketing => 'Pazarlama',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Necessary => 'Oturumun açık kalması, form güvenliği, dil ve tema tercihi. Bunlar olmadan site çalışmaz, bu yüzden kapatılamaz.',
            self::Analytics => 'Hangi sayfaların ne kadar okunduğunu ölçer. Ziyaret kaydı ve Google Analytics bu izne bağlıdır.',
            self::Marketing => 'Google Tag Manager ve onun yüklediği etiketler. Reklam ölçümü ve yeniden hedefleme bu izne bağlıdır.',
        };
    }

    /** Rıza istenmeyen, her zaman açık kategori. */
    public function isRequired(): bool
    {
        return $this === self::Necessary;
    }

    /**
     * Ziyaretçinin seçebildiği kategoriler.
     *
     * @return list<self>
     */
    public static function optional(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $case): bool => ! $case->isRequired()));
    }
}
