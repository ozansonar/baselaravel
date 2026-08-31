<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pasife alınan hesabın jetonu da çalışmamalı.
 *
 * {@see EnsureUserIsActive} aynı işi web tarafında yapıyor ama oturumu
 * kapatarak: `Auth::logout()` ve `session()->invalidate()`. API'de oturum yok,
 * jeton var — ve jeton, oturumdan farklı olarak kendiliğinden sona ermiyor.
 * Yönetici hesabı kapattığında elindeki jetonla erişmeye devam eden bir mobil
 * uygulama, düğmeye basan kişinin en son beklediği şey.
 *
 * Yalnız bu isteğin jetonu siliniyor, hepsi değil: hesabı yeniden açılan
 * kullanıcı öteki cihazlarında kaldığı yerden devam edebilsin. Zaten her cihaz
 * bir sonraki isteğinde aynı kapıya çarpıyor.
 */
final class EnsureApiUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->is_active) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return ApiResponse::error(__('site.login.deactivated'), status: 403);
    }
}
