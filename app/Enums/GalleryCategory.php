<?php

declare(strict_types=1);

namespace App\Enums;

enum GalleryCategory: string
{
    case Ciftlik    = 'ciftlik';
    case Hayvanlar  = 'hayvanlar';
    case Urunler    = 'urunler';
    case Manzara    = 'manzara';

    public function label(): string
    {
        return match ($this) {
            self::Ciftlik   => 'Çiftlik',
            self::Hayvanlar => 'Hayvanlar',
            self::Urunler   => 'Ürünler',
            self::Manzara   => 'Manzara',
        };
    }
}
