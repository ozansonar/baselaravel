<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Yönetim paneli her zaman Türkçe.
 *
 * Panelin metinleri koda Türkçe yazılmış — tek bir __() çağrısı yok. Buna
 * karşılık SetLocale her istekte ziyaretçinin ön yüz tercihini uyguluyordu, o
 * tercih de oturumda ya da tarayıcının Accept-Language başlığında duruyordu.
 * Sonuç: başlıklar Türkçe, tarihler İngilizceydi ("1 day ago", "26 Aug 2026").
 * Doğrulama mesajları da aynı sebeple İngilizce dönüyordu.
 *
 * Dil burada sabitleniyor. app()->setLocale bir LocaleUpdated olayı yayıyor,
 * Carbon'un servis sağlayıcısı da onu dinleyip kendi dilini güncelliyor;
 * diffForHumans() ve translatedFormat() böylece Türkçe yazıyor.
 *
 * URL::defaults'a kasten dokunulmuyor: panelden ön yüze verilen bağlantılar
 * (bir blog yazısını sitede görüntüle gibi) içeriğin dilini izlemeli, panelin
 * dilini değil. Türkçeye sabitlemek İngilizce bir yazının bağlantısını bozardı.
 */
final class SetAdminLocale
{
    /**
     * Panelin dili. Ön yüzün desteklediği diller ayrı bir mesele; burası
     * yönetim ekranı ve tek dilde yazılmış.
     */
    public const LOCALE = 'tr';

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(self::LOCALE);

        return $next($request);
    }
}
