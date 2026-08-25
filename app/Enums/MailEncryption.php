<?php

declare(strict_types=1);

namespace App\Enums;

enum MailEncryption: string
{
    case Tls  = 'tls';
    case Ssl  = 'ssl';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Tls  => 'TLS',
            self::Ssl  => 'SSL',
            self::None => 'Yok',
        };
    }
}
