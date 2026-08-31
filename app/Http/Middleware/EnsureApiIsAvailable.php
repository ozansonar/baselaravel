<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bakım modunda API de kapalı.
 *
 * {@see CheckMaintenanceMode} ön yüz için aynı kararı veriyor ama HTML bir
 * sayfa basıyor; mobil istemci onu ayrıştıramaz. Ayarı okuma ve izinli IP
 * mantığı aynı: site kapalıyken uygulamanın hiçbir şey olmamış gibi içerik
 * sunması, yöneticinin kapattığını sandığı sitenin yarısının açık kalması
 * demek.
 *
 * Kimlik uçlarına uygulanmıyor (routes/api.php) — ön yüzde de /giris bakım
 * modunda açık kalıyor.
 */
final class EnsureApiIsAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Setting::getValue('maintenance_mode') !== '1') {
            return $next($request);
        }

        if ($this->isAllowedIp($request)) {
            return $next($request);
        }

        return ApiResponse::error(
            Setting::getValue('maintenance_message') ?: __('site.errors.503_message'),
            status: 503,
        );
    }

    private function isAllowedIp(Request $request): bool
    {
        $allowedIps = Setting::getValue('maintenance_allowed_ips');

        if (empty($allowedIps)) {
            return false;
        }

        $ipList = array_filter(array_map('trim', explode("\n", $allowedIps)));

        return in_array($request->ip(), $ipList, true);
    }
}
