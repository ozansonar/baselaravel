<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationLevel: string
{
    case Info     = 'info';
    case Success  = 'success';
    case Warning  = 'warning';
    case Error    = 'error';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info     => 'Bilgi',
            self::Success  => 'Başarılı',
            self::Warning  => 'Uyarı',
            self::Error    => 'Hata',
            self::Critical => 'Kritik',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Info     => 'info',
            self::Success  => 'success',
            self::Warning  => 'warning',
            self::Error    => 'danger',
            self::Critical => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Info     => 'bi-info-circle-fill',
            self::Success  => 'bi-check-circle-fill',
            self::Warning  => 'bi-exclamation-triangle-fill',
            self::Error    => 'bi-x-octagon-fill',
            self::Critical => 'bi-fire',
        };
    }
}
