<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Draft = 'draft';

    public function label(): string
    {
        return match ($this) {
            self::Active   => 'Aktif',
            self::Inactive => 'Pasif',
            self::Draft    => 'Taslak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active   => 'success',
            self::Inactive => 'danger',
            self::Draft    => 'warning',
        };
    }
}
