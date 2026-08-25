<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tabs of the settings screen. A new group only needs a case here.
 */
enum SettingGroup: string
{
    case General    = 'general';
    case Contact    = 'contact';
    case Social     = 'social';
    case Seo        = 'seo';
    case Mail       = 'mail';
    case Appearance = 'appearance';

    public function label(): string
    {
        return match ($this) {
            self::General    => 'Genel',
            self::Contact    => 'İletişim',
            self::Social     => 'Sosyal Medya',
            self::Seo        => 'SEO',
            self::Mail       => 'E-posta',
            self::Appearance => 'Görünüm',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::General    => 'bi-gear-fill',
            self::Contact    => 'bi-telephone-fill',
            self::Social     => 'bi-share-fill',
            self::Seo        => 'bi-graph-up-arrow',
            self::Mail       => 'bi-envelope-fill',
            self::Appearance => 'bi-palette-fill',
        };
    }
}
