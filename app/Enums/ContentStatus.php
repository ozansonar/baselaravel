<?php

declare(strict_types=1);

namespace App\Enums;

enum ContentStatus: string
{
    case Published = 'published';
    case Draft = 'draft';
    case Archived = 'archived';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Published => 'Yayında',
            self::Draft     => 'Taslak',
            self::Archived  => 'Arşivlendi',
            self::Scheduled => 'Zamanlanmış',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Published => 'success',
            self::Draft     => 'warning',
            self::Archived  => 'secondary',
            self::Scheduled => 'info',
        };
    }
}
