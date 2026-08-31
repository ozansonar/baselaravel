<?php

declare(strict_types=1);

use App\Http\Controllers\AnalyticsTrackController;
use App\Http\Controllers\LegacyUrlController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\RootRedirectController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Language-agnostic Routes
|--------------------------------------------------------------------------
| Endpoints that mean the same thing in every language: machine-readable
| files, links that travel in e-mails and must keep working forever, and the
| language switcher itself.
*/

// Sitemap (XML — machine-readable for search engines)
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// robots.txt — a route rather than a file in public/. The disallow list is
// built from the routes themselves and from the addresses opened in the panel,
// so it cannot fall behind them, and the Sitemap line names this site rather
// than whichever one the file was first written for.
Route::get('/robots.txt', RobotsController::class)->name('robots');

// Language switcher — forwards to the same page in the requested language.
Route::get('/dil/{code}', LocaleController::class)->name('locale.switch');

// Newsletter — abonelik ve abonelikten çıkma.
// Çıkış bağlantısı her kampanya mailinin altında yer alır; giriş gerektirmez.
Route::post('/bulten/abone-ol', [NewsletterController::class, 'subscribe'])
    ->middleware('throttle:10,1')
    ->name('newsletter.subscribe');
Route::get('/bulten/cikis/{token}', [NewsletterController::class, 'unsubscribe'])
    ->name('newsletter.unsubscribe');

// Analytics tracking endpoint (async, does not affect page speed)
Route::post('/api/analytics/track', [AnalyticsTrackController::class, 'store'])
    ->name('analytics.track')
    ->middleware('throttle:60,1');

// The bare root carries no language, so it forwards to the visitor's own —
// this is the URL hreflang advertises as x-default.
Route::get('/', RootRedirectController::class)->name('root');

/*
|--------------------------------------------------------------------------
| Localized Frontend Routes
|--------------------------------------------------------------------------
| Every front page lives under its language code (/tr/blog, /en/blog). Having
| a URL of its own is what lets each language version be indexed separately
| and linked to its translations with hreflang.
*/

Route::prefix('{locale}')
    ->where(['locale' => '[a-z]{2}(?:-[a-z]{2})?'])
    ->group(base_path('routes/front.php'));

/*
|--------------------------------------------------------------------------
| Legacy URLs
|--------------------------------------------------------------------------
| Addresses from before the language prefix (/blog, /hakkimizda) move
| permanently into the default language rather than 404, so old links and the
| ranking they carry survive.
*/

Route::fallback(LegacyUrlController::class);
