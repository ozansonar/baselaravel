<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckMaintenanceMode
{
    /**
     * Paths that are always accessible during maintenance mode.
     *
     * @var array<int, string>
     */
    private const ALLOWED_PATHS = [
        'giris',
        'admin',
        'admin/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $maintenanceEnabled = Setting::getValue('maintenance_mode') === '1';

        if (! $maintenanceEnabled) {
            return $next($request);
        }

        if ($this->isAllowedPath($request)) {
            return $next($request);
        }

        if ($this->isAllowedIp($request)) {
            return $next($request);
        }

        // Sayfanın ihtiyacı olan her şey buradan gidiyor. Görünüm ayar
        // okumuyor: Laravel aynı sayfayı "php artisan down" için de basıyor
        // ve orada veritabanı düşmüş olabilir. Burada ise ayarın okunabildiği
        // kesin — bakım modunda olduğumuzu zaten ondan öğrendik.
        return response()->view('errors.503', [
            'maintenanceMessage' => Setting::getValue('maintenance_message') ?: __('site.errors.503_message'),
            'siteName'           => Setting::getValue('site_name', config('app.name')),
            'siteLogo'           => Setting::getValue('site_logo'),
        ], 503);
    }

    private function isAllowedPath(Request $request): bool
    {
        foreach (self::ALLOWED_PATHS as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isAllowedIp(Request $request): bool
    {
        $allowedIps = Setting::getValue('maintenance_allowed_ips');

        if (empty($allowedIps)) {
            return false;
        }

        $ipList = array_map('trim', explode("\n", $allowedIps));
        $ipList = array_filter($ipList);

        return in_array($request->ip(), $ipList, true);
    }
}
