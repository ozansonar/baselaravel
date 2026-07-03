<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Setting::getValue('app_locale', config('app.locale', 'tr'));
        $timezone = Setting::getValue('app_timezone', config('app.timezone', 'Europe/Istanbul'));

        app()->setLocale($locale);
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        return $next($request);
    }
}
