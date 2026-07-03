<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Beklemede',
            self::Processing => 'Hazırlanıyor',
            self::Shipped    => 'Kargoda',
            self::Delivered  => 'Teslim Edildi',
            self::Cancelled  => 'İptal Edildi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending    => 'warning',
            self::Processing => 'info',
            self::Shipped    => 'primary',
            self::Delivered  => 'success',
            self::Cancelled  => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending    => 'bi-clock',
            self::Processing => 'bi-gear',
            self::Shipped    => 'bi-truck',
            self::Delivered  => 'bi-check-circle',
            self::Cancelled  => 'bi-x-circle',
        };
    }
}
