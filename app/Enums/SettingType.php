<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a setting value is rendered and stored in the settings screen.
 */
enum SettingType: string
{
    case Text     = 'text';
    case Textarea = 'textarea';
    case Password = 'password';
    case Image    = 'image';
    case Boolean  = 'boolean';

    public function label(): string
    {
        return match ($this) {
            self::Text     => 'Metin',
            self::Textarea => 'Uzun Metin',
            self::Password => 'Parola',
            self::Image    => 'Görsel',
            self::Boolean  => 'Aç/Kapa',
        };
    }
}
