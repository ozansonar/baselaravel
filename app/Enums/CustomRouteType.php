<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Özel bir adresin ne yapacağı.
 *
 * İçeriği doğrudan basmak ile başka bir adrese göndermek aynı şey değil:
 * ilkinde adres kalıcı olarak o içeriğin adresi olur, ikincisinde ziyaretçi
 * (ve arama motoru) hedefe taşınır.
 */
enum CustomRouteType: string
{
    /** Hedef sayfayı bu adres altında bas. */
    case Render = 'render';

    /** Kalıcı taşındı: arama motoru eski adresi bırakıp yenisini alır. */
    case MovedPermanently = 'redirect_301';

    /** Geçici yönlendirme: eski adres dizinde kalır. */
    case Found = 'redirect_302';

    public function label(): string
    {
        return match ($this) {
            self::Render           => 'Sayfayı bu adreste göster',
            self::MovedPermanently => 'Kalıcı yönlendir (301)',
            self::Found            => 'Geçici yönlendir (302)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Render           => 'Adres çubuğunda bu adres kalır, içerik burada basılır.',
            self::MovedPermanently => 'Ziyaretçi hedefe taşınır; arama motoru eski adresi bırakır.',
            self::Found            => 'Ziyaretçi hedefe taşınır; eski adres dizinde kalır.',
        };
    }

    public function isRedirect(): bool
    {
        return $this !== self::Render;
    }

    /** Yönlendirme durum kodu; gösterim türünde anlamsız. */
    public function statusCode(): int
    {
        return match ($this) {
            self::MovedPermanently => 301,
            default                => 302,
        };
    }
}
