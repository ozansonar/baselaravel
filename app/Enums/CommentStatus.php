<?php

declare(strict_types=1);

namespace App\Enums;

enum CommentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Beklemede',
            self::Approved => 'Onaylı',
            self::Rejected => 'Reddedildi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending  => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending  => 'bi-hourglass-split',
            self::Approved => 'bi-check-circle-fill',
            self::Rejected => 'bi-x-circle-fill',
        };
    }
}
