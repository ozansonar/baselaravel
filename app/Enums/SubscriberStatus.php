<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriberStatus: string
{
    case Subscribed   = 'subscribed';
    case Unsubscribed = 'unsubscribed';
    case Bounced      = 'bounced';

    public function label(): string
    {
        return match ($this) {
            self::Subscribed   => 'Abone',
            self::Unsubscribed => 'Ayrıldı',
            self::Bounced      => 'Ulaşılamıyor',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Subscribed   => 'success',
            self::Unsubscribed => 'muted',
            self::Bounced      => 'danger',
        };
    }
}
