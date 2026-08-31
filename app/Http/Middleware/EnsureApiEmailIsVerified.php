<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Doğrulanmamış e-postayla hesap işlemi yapılamaz.
 *
 * Ön yüzde `/hesabim` `verified` ara katmanının arkasında; API'nin aynı şeyi
 * söylemesi gerekiyor, yoksa web'den kapalı olan kapı mobilden açık kalırdı.
 *
 * Çerçevenin kendi `verified` ara katmanı kullanılmıyor: JSON isteğinde
 * İngilizce sabit bir metinle 403 atıyor. Buradaki yanıt hem projenin zarfını
 * taşıyor hem de ziyaretçinin dilinde — ve istemcinin ne yapması gerektiğini
 * söyleyen bir kod (`email_unverified`) veriyor ki doğrulama ekranına
 * yönlendirebilsin.
 *
 * `/auth/me` bilerek bu ara katmanın dışında: kullanıcı kendi durumunu
 * (`email_verified`) görebilmeli, yoksa uygulama "doğrula" ekranını neye
 * bakarak çizeceğini bilemez.
 */
final class EnsureApiEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return ApiResponse::error(
                __('api.auth.email_unverified'),
                ['code' => ['email_unverified']],
                403,
            );
        }

        return $next($request);
    }
}
