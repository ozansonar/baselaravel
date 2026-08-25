<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Interface languages the panel and front end can run in.
 *
 * Adding a case here also needs a matching directory under lang/.
 */
enum AppLocale: string
{
    case Tr = 'tr';
    case En = 'en';

    public function label(): string
    {
        return match ($this) {
            self::Tr => 'Türkçe',
            self::En => 'English',
        };
    }

    public static function default(): self
    {
        return self::Tr;
    }
}
