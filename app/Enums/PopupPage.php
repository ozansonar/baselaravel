<?php

declare(strict_types=1);

namespace App\Enums;

enum PopupPage: string
{
    case All = 'all';
    case Home = 'home';
    case Products = 'products';
    case ProductDetail = 'product_detail';
    case About = 'about';
    case Contact = 'contact';
    case Blog = 'blog';
    case Gallery = 'gallery';
    case Faq = 'faq';
    case Cart = 'cart';

    public function label(): string
    {
        return match ($this) {
            self::All           => 'Tüm Sayfalar',
            self::Home          => 'Anasayfa',
            self::Products      => 'Ürünler',
            self::ProductDetail => 'Ürün Detay',
            self::About         => 'Hakkımızda',
            self::Contact       => 'İletişim',
            self::Blog          => 'Blog',
            self::Gallery       => 'Galeri',
            self::Faq           => 'SSS',
            self::Cart          => 'Sepet',
        };
    }
}
