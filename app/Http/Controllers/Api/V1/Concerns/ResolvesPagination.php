<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\Request;

/**
 * Sayfa boyutunu istemciden alır ama ona bırakmaz.
 *
 * `?per_page` olmadan mobil istemci listeyi sunucunun seçtiği boyutta almak
 * zorunda kalırdı; sınırsız bırakılsaydı `?per_page=100000` ile tek istekte
 * bütün tablo çekilebilirdi — hem sorgu hem yanıt hem de bellek açısından.
 * Tavan config/api.php'de.
 */
trait ResolvesPagination
{
    protected function perPage(Request $request): int
    {
        $default = (int) config('api.pagination.per_page', 15);
        $max     = (int) config('api.pagination.max_per_page', 100);

        $requested = (int) $request->integer('per_page', $default);

        // 0 ya da negatif değer "varsayılanı kullan" demek; hata döndürmek
        // istemciyi bir liste için iki gidiş dönüşe zorlardı.
        return $requested < 1 ? $default : min($requested, $max);
    }
}
