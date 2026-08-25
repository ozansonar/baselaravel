<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How noisy the Telegram/e-mail alerting should be about failed mail.
 */
enum MailErrorNotifyLevel: string
{
    case None          = 'none';
    case PermanentOnly = 'permanent_only';
    case EveryFailure  = 'every_failure';

    public function label(): string
    {
        return match ($this) {
            self::None          => 'Bildirim gönderme',
            self::PermanentOnly => 'Yalnızca kalıcı hatalarda',
            self::EveryFailure  => 'Her hatada',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::None          => 'Başarısız gönderimlerde hiçbir uyarı gönderilmez.',
            self::PermanentOnly => 'Tüm denemeler tükendiğinde tek uyarı gönderilir.',
            self::EveryFailure  => 'Her başarısız denemede ayrı uyarı gönderilir.',
        };
    }
}
