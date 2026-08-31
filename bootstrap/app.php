<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        // Mobil uygulamaların ve harici ön yüzlerin konuştuğu katman. Sürüm
        // adresin içinde: kırıcı bir değişiklik geldiğinde /api/v2 açılır ve
        // v1 bir süre ayakta kalır — mağazadan güncellenmemiş uygulamalar eski
        // sözleşmeyi konuşmaya devam eder.
        //
        // Çerçeve API rotalarını web'den ÖNCE kaydediyor; routes/api.php'deki
        // fallback bu yüzden routes/web.php'dekinden önce eşleşir ve bilinmeyen
        // bir /api/v1 adresi HTML yerine JSON 404 döner.
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api/v1',
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
            // Jetonu geçerli ama hesabı kapatılmış kullanıcıyı durdurur.
            'api.active' => \App\Http\Middleware\EnsureApiUserIsActive::class,
            // Bakım modunda genel API uçlarını kapatır.
            'api.available' => \App\Http\Middleware\EnsureApiIsAvailable::class,
            // E-postası doğrulanmamış kullanıcıyı hesap uçlarından çevirir.
            'api.verified' => \App\Http\Middleware\EnsureApiEmailIsVerified::class,
        ]);

        // Kendi alan adımızdaki ön yüz API'yi oturum çereziyle de
        // kullanabilsin: config/sanctum.php'deki stateful listesindeki
        // kaynaklardan gelen isteklerde jeton aranmaz. Mobil uygulama listede
        // olmadığı için her zaman Bearer jetonuyla gelir.
        $middleware->statefulApi();

        // Taban hız sınırı — her API isteği için. Uçlara özel daha sıkı
        // sınırlar (giriş, kayıt, form) rotalarda ayrıca tanımlı.
        $middleware->throttleApi('api');

        // Sıra bilinçli. Üçü de hız sınırından ÖNCE çalışmalı:
        //   - SecurityHeaders başlıkları hata yanıtlarına da koysun,
        //   - ForceJsonResponse 429'un gövdesi de JSON olsun diye,
        //   - SetApiLocale uyarı metni ziyaretçinin dilinde çıksın diye.
        // Sınırlayıcı istisna fırlattığında ondan sonraki hiçbir ara katman
        // çalışmıyor; öncekiler ise yanıtı geri dönerken görüyor.
        $middleware->api(prepend: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\SetApiLocale::class,
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

        // API hataları tek bir zarfa giriyor. Çerçevenin varsayılan JSON
        // hataları üç ayrı şekilde geliyordu (doğrulama, kimlik, 500) ve mobil
        // istemcinin üçünü de ayrı ayrı tanıması gerekiyordu.
        //
        // null dönmek "sen karışma" demek: /api/v1 dışındaki istekler —panel,
        // ön yüz, /api/analytics/track— eskisi gibi işleniyor.
        $exceptions->render(fn (\Throwable $e, \Illuminate\Http\Request $request) =>
            \App\Exceptions\ApiExceptionRenderer::render($e, $request));
    })->create();
