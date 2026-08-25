<?php

declare(strict_types=1);

namespace App\Enums;

enum RedirectStatus: int
{
    case MovedPermanently = 301;
    case Found            = 302;
    case TemporaryRedirect = 307;
    case PermanentRedirect = 308;
    case NotFound         = 404;
    case Gone             = 410;

    public function label(): string
    {
        return match ($this) {
            self::MovedPermanently  => '301 — Kalıcı Yönlendirme',
            self::Found             => '302 — Geçici Yönlendirme',
            self::TemporaryRedirect => '307 — Geçici (Metot Korunur)',
            self::PermanentRedirect => '308 — Kalıcı (Metot Korunur)',
            self::NotFound          => '404 — Bulunamadı',
            self::Gone              => '410 — Kaldırıldı',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MovedPermanently, self::PermanentRedirect => 'success',
            self::Found, self::TemporaryRedirect            => 'info',
            self::NotFound                                  => 'warning',
            self::Gone                                      => 'danger',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MovedPermanently  => 'URL kalıcı olarak taşındı. SEO değeri yeni URL\'ye aktarılır. Google eski URL\'yi zamanla düşürür.',
            self::Found             => 'URL geçici olarak başka yere yönlendirildi. SEO değeri eski URL\'de kalır.',
            self::TemporaryRedirect => '302 gibi ama HTTP metodu (POST/GET) değişmez. API yönlendirmeleri için uygundur.',
            self::PermanentRedirect => '301 gibi ama HTTP metodu değişmez. API\'ler için kalıcı taşıma.',
            self::NotFound          => 'Sayfa mevcut değil. Yönlendirme yapılmaz, 404 sayfası gösterilir.',
            self::Gone              => 'Sayfa bilinçli olarak kaldırıldı ve geri gelmeyecek. Google daha hızlı düşürür.',
        };
    }

    /**
     * Codes that send the visitor somewhere; the rest terminate the request
     * and therefore need no target URL.
     */
    public function redirectsSomewhere(): bool
    {
        return match ($this) {
            self::NotFound, self::Gone => false,
            default                    => true,
        };
    }

    /**
     * @return array<int, int>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): int => $case->value, self::cases());
    }
}
