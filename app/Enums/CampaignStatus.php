<?php

declare(strict_types=1);

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft     = 'draft';
    case Scheduled = 'scheduled';
    case Sending   = 'sending';
    case Paused    = 'paused';
    case Sent      = 'sent';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Taslak',
            self::Scheduled => 'Zamanlanmış',
            self::Sending   => 'Gönderiliyor',
            self::Paused    => 'Duraklatıldı',
            self::Sent      => 'Gönderildi',
            self::Cancelled => 'İptal edildi',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft     => 'muted',
            self::Scheduled => 'info',
            self::Sending   => 'warning',
            self::Paused    => 'warning',
            self::Sent      => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * The dispatcher only picks up campaigns in these states.
     */
    public function isDispatchable(): bool
    {
        return in_array($this, [self::Scheduled, self::Sending], true);
    }

    /**
     * Content may only change while nothing has gone out yet — otherwise the
     * first half of the list would receive a different mail from the rest.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled], true);
    }
}
