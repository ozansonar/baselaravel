<?php

declare(strict_types=1);

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Male   => 'Erkek',
            self::Female => 'Kadın',
            self::Other  => 'Belirtmek İstemiyorum',
        };
    }
}
