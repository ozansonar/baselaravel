<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Throwable;

/**
 * Kayda düşmeden önce adresten sırları çıkarır.
 *
 * Bu projede adres satırında sır taşıyan iki akış var:
 *
 *  - `GET {locale}/sifre-sifirla/{token}` — şifre sıfırlama jetonu **yolun
 *    içinde**. Onu ele geçiren, o hesabın şifresini değiştirebilir.
 *  - `GET {locale}/e-posta-dogrula/{id}/{hash}` — imzalı adres; `signature`
 *    sorgu dizesinde.
 *
 * Bu adresler dört yere yazılıyordu: `error_logs` (60 gün), `audit_logs`
 * (90 gün), çerez rızası kaydı ve **Telegram**. Sonuncusu sunucudan tamamen
 * çıkıyor. Yani o sayfalarda oluşan tek bir hata, geçerli bir sıfırlama
 * jetonunu panele bakan herkesin ve Telegram grubundaki herkesin önüne
 * koyabiliyordu. Jetonun ömrü kısa, kaydın ömrü uzun.
 *
 * Temizlik **adla** çalışıyor, biçimle değil: parametrenin adı `token` ise
 * değeri neye benzerse benzesin gider. Değere bakan bir kural (uzunluk,
 * rastgelelik) er geç yanılır.
 *
 * Adres okunur kalıyor — yalnız sır maskeleniyor. Hangi sayfada patladığı
 * görülemeyen bir hata kaydı işe yaramaz.
 */
final class SafeUrl
{
    public const MASK = '***';

    /**
     * Adı sır taşıdığını gösteren parametreler.
     *
     * `hash` de listede: e-posta doğrulama bağlantısında kullanıcının
     * e-postasının imzası olarak geçiyor.
     */
    private const SENSITIVE = [
        'token', 'signature', 'hash', 'secret', 'password', 'api_token',
        'access_token', 'refresh_token', 'key', 'code', 'otp',
    ];

    /**
     * İsteğin adresi — sırları maskelenmiş hâlde.
     */
    public static function forRequest(Request $request, int $limit = 2048): string
    {
        $url = rescue(static fn (): string => $request->fullUrl(), '', false);

        if ($url === '') {
            return '';
        }

        $url = self::maskQuery($url);
        $url = self::maskRouteParameters($request, $url);

        return mb_substr($url, 0, $limit);
    }

    /**
     * Ham bir adres dizgesi — sorgu dizesindeki sırları maskelenmiş hâlde.
     *
     * Rota bilgisi olmayan adresler için: `referer` başlığı gibi dışarıdan
     * gelen ve hangi rotaya karşılık geldiği bilinmeyen değerler. Yol
     * parametresi çözülemediği için yalnız sorgu alanı temizlenebiliyor.
     */
    public static function sanitize(string $url): string
    {
        return $url === '' ? '' : self::maskQuery($url);
    }

    /**
     * Sorgu dizesindeki hassas alanlar.
     */
    private static function maskQuery(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return $url;
        }

        parse_str($query, $params);

        $touched = false;

        foreach ($params as $name => $value) {
            if (self::isSensitive((string) $name)) {
                $params[$name] = self::MASK;
                $touched = true;
            }
        }

        if (! $touched) {
            return $url;
        }

        $base = strtok($url, '?');

        return ($base === false ? $url : $base) . '?' . http_build_query($params);
    }

    /**
     * Yol parametreleri: `/sifre-sifirla/{token}` gibi.
     *
     * Değer yolun içinde düz metin olarak duruyor, sorgu alanı gibi ayrı
     * durmuyor; bu yüzden dizge değişimiyle çıkarılıyor. Rota çözülmemişse
     * (yönlendirmeden önce oluşan hata) yapılacak bir şey yok.
     */
    private static function maskRouteParameters(Request $request, string $url): string
    {
        try {
            $route = $request->route();
        } catch (Throwable) {
            return $url;
        }

        if ($route === null) {
            return $url;
        }

        foreach ($route->parameters() as $name => $value) {
            // Bağlanmış model değil, ham dizge olanlar. Kısa değerler kasten
            // atlanıyor: "1" gibi bir kimliği maskelemek adresi okunmaz yapar
            // ve zaten sır değildir.
            if (! is_string($value) || mb_strlen($value) < 8 || ! self::isSensitive((string) $name)) {
                continue;
            }

            $url = str_replace([rawurlencode($value), $value], self::MASK, $url);
        }

        return $url;
    }

    private static function isSensitive(string $name): bool
    {
        return in_array(mb_strtolower($name), self::SENSITIVE, true);
    }
}
