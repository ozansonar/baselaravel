<?php

declare(strict_types=1);

namespace App\Enums;

enum InstagramPostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft      => 'Taslak',
            self::Scheduled  => 'Planlanmış',
            self::Publishing => 'Yayınlanıyor',
            self::Published  => 'Yayınlandı',
            self::Failed     => 'Başarısız',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft      => 'inactive',
            self::Scheduled  => 'info',
            self::Publishing => 'pending',
            self::Published  => 'active',
            self::Failed     => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft      => 'bi-file-earmark',
            self::Scheduled  => 'bi-calendar-event',
            self::Publishing => 'bi-cloud-upload',
            self::Published  => 'bi-check-circle-fill',
            self::Failed     => 'bi-exclamation-triangle-fill',
        };
    }
}
