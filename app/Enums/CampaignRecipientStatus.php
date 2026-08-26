<?php

declare(strict_types=1);

namespace App\Enums;

enum CampaignRecipientStatus: string
{
    case Pending = 'pending';
    case Sent    = 'sent';
    case Failed  = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Sırada',
            self::Sent    => 'Gönderildi',
            self::Failed  => 'Başarısız',
            self::Skipped => 'Atlandı',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'muted',
            self::Sent    => 'success',
            self::Failed  => 'danger',
            self::Skipped => 'warning',
        };
    }
}
