<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Panelin dili ziyaretçinin ön yüz tercihine bağlı olmamalı;
            // admin.locale, web grubundaki SetLocale'den sonra çalışıp
            // Türkçe'ye sabitliyor.
            Route::middleware(['web', 'admin.locale', 'admin'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'admin.locale' => \App\Http\Middleware\SetAdminLocale::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\HandleRedirects::class,
            \App\Http\Middleware\SetLocale::class,
            // Panelden açılmış adresler burada karşılanıyor. SetLocale'den
            // sonra: hangi dilin adresi olduğunu bilmesi gerekiyor. Eşleşme
            // yoksa hiçbir şey yapmadan çekiliyor.
            \App\Http\Middleware\ResolveCustomRoutes::class,
            \App\Http\Middleware\CheckMaintenanceMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
