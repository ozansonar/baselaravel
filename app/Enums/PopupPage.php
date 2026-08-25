<?php

declare(strict_types=1);

namespace App\Enums;

enum PopupPage: string
{
    case All = 'all';
    case Home = 'home';
    case Blog = 'blog';
    case Gallery = 'gallery';
    case Contact = 'contact';
    case Faq = 'faq';

    public function label(): string
    {
        return match ($this) {
            self::All     => 'Tüm Sayfalar',
            self::Home    => 'Anasayfa',
            self::Blog    => 'Blog / İçerikler',
            self::Gallery => 'Galeri',
            self::Contact => 'İletişim',
            self::Faq     => 'SSS',
        };
    }
}
