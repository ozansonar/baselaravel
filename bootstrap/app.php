<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
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
        // Hangi başlıkların ziyaretçi adına konuşabileceği. Hangi proxy'ye
        // güvenildiği istek anında config/trustedproxy.php'den okunuyor —
        // burası uygulama ayağa kalkmadan çalıştığı için config() henüz yok.
        // AWS_ELB ve PREFIX bilinçli olarak dışarıda: bu proje o iki başlığı
        // üreten hiçbir katmanın arkasında çalışmıyor, güvenilen yüzey de
        // gereğinden geniş olmamalı.
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'admin.locale' => \App\Http\Middleware\SetAdminLocale::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\HandleRedirects::class,
            \App\Http\Middleware\SetLocale::class,
            // Pasife alınan kullanıcı buradan geri çevriliyor. SetLocale'den
            // sonra: uyarının ziyaretçinin dilinde çıkması gerekiyor.
            \App\Http\Middleware\EnsureUserIsActive::class,
            // Panelden açılmış adresler burada karşılanıyor. SetLocale'den
            // sonra: hangi dilin adresi olduğunu bilmesi gerekiyor. Eşleşme
            // yoksa hiçbir şey yapmadan çekiliyor.
            \App\Http\Middleware\ResolveCustomRoutes::class,
            \App\Http\Middleware\CheckMaintenanceMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // İşlenmeyen hata yöneticiye ulaşsın. Buraya yalnızca beklenmedik
        // olanlar geliyor: 404, 403, 419, 429, doğrulama ve kimlik hataları
        // Laravel tarafından raporlanmadan eleniyor.
        //
        // Kapanış hiçbir şey döndürmüyor — `false` dönseydi hatanın loga
        // yazılmasını da durdururdu; bildirim logun yerine değil yanına
        // ekleniyor.
        $exceptions->report(function (\Throwable $e): void {
            app(\App\Services\ExceptionNotifier::class)->notify($e);
        });
    })->create();
