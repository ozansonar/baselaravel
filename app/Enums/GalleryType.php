<?php

declare(strict_types=1);

namespace App\Enums;

enum GalleryType: string
{
    case Photo = 'photo';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Fotoğraf',
            self::Video => 'Video',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Photo => 'bi bi-image',
            self::Video => 'bi bi-camera-video',
        };
    }
}
