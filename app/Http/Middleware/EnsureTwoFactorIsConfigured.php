<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zorunluluk açıkken, ikinci adımı kurmamış yöneticiyi panele almaz.
 *
 * Ayar tek başına bir dilekti: açık olsa bile 2FA kurmamış yönetici panele
 * girmeye devam ediyordu. Zorunluluğun karşılığı bu ara katman — panele giden
 * her istek, kurulum tamamlanana kadar güvenlik ekranına dönüyor.
 *
 * Kurulum ekranı ön yüzde (`/hesabim/guvenlik`) ve bilerek tek: aynı ekranın
 * bir de panel sürümü olsaydı iki ayrı yerde bakımı gereken aynı akış olurdu.
 */
final class EnsureTwoFactorIsConfigured
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $this->twoFactor->requiredForAdmins() || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if (! $user->hasAnyRole(['admin', 'editor', 'moderator'])) {
            return $next($request);
        }

        return redirect()->route('account.security')
            ->with('error', __('site.two_factor.setup_required'));
    }
}
