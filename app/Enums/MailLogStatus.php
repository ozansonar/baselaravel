<?php

declare(strict_types=1);

namespace App\Enums;

enum MailLogStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Beklemede',
            self::Sent    => 'Gönderildi',
            self::Failed  => 'Başarısız',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sent    => 'success',
            self::Failed  => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'bi-clock-fill',
            self::Sent    => 'bi-check-circle-fill',
            self::Failed  => 'bi-x-circle-fill',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Pending => 'ord-status-pending',
            self::Sent    => 'ord-status-delivered',
            self::Failed  => 'ord-status-cancelled',
        };
    }
}
