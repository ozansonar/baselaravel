<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API isteği ne isterse istesin, cevap JSON.
 *
 * Laravel'in hata üreticileri (kimlik doğrulama, 404, hız sınırı, bakım modu)
 * kararı `Accept` başlığına bakarak veriyor. Başlığı yollamayı unutan bir mobil
 * istemci —ya da tarayıcının adres çubuğu— hata anında JSON yerine HTML hata
 * sayfası alıyor, istemci onu ayrıştıramıyor ve elinde "beklenmeyen karakter
 * '<'" kalıyor. Asıl hata da böylece görünmez oluyor.
 *
 * Başlık yalnızca istekte değiştiriliyor; gerçekten ne istendiği bilgisi
 * `X-Requested-Accept` altında saklı kalıyor ki gerekirse okunabilsin.
 */
final class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $original = $request->headers->get('Accept');

        if ($original !== null && ! str_contains((string) $original, '/json')) {
            $request->headers->set('X-Requested-Accept', (string) $original);
        }

        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
