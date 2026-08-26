<?php

declare(strict_types=1);

namespace App\Enums;

enum CampaignAudience: string
{
    case Users       = 'users';
    case Subscribers = 'subscribers';
    case Import      = 'import';
    case Manual      = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Users       => 'Site üyeleri',
            self::Subscribers => 'Mail listesi',
            self::Import      => 'Excel / CSV yükle',
            self::Manual      => 'Elle gireceğim',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Users       => 'Kayıtlı ve aktif kullanıcılar',
            self::Subscribers => 'Bültene abone olmuş kişiler',
            self::Import      => 'Ad ve e-posta sütunu olan bir dosya',
            self::Manual      => 'Ad soyad ve e-postayı satır satır yaz',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Users       => 'bi-people-fill',
            self::Subscribers => 'bi-envelope-heart-fill',
            self::Import      => 'bi-file-earmark-spreadsheet-fill',
            self::Manual      => 'bi-pencil-square',
        };
    }
}
