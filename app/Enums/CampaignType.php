<?php

declare(strict_types=1);

namespace App\Enums;

enum CampaignType: string
{
    case Discount = 'discount';
    case Coupon = 'coupon';
    case FlashSale = 'flash_sale';
    case FreeShipping = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::Discount     => 'İndirim',
            self::Coupon       => 'Kupon',
            self::FlashSale    => 'Flaş Satış',
            self::FreeShipping => 'Ücretsiz Kargo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Discount     => 'warning',
            self::Coupon       => 'info',
            self::FlashSale    => 'danger',
            self::FreeShipping => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Discount     => 'bi-percent',
            self::Coupon       => 'bi-ticket-perforated',
            self::FlashSale    => 'bi-lightning',
            self::FreeShipping => 'bi-truck',
        };
    }
}
