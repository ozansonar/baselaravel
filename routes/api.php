<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlogCategoryController;
use App\Http\Controllers\Api\V1\BlogPostController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\GalleryCategoryController;
use App\Http\Controllers\Api\V1\GalleryController;
use App\Http\Controllers\Api\V1\LanguageController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\TranslationController;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Adres öneki bootstrap/app.php'de veriliyor (`apiPrefix: 'api/v1'`), yani
| buradaki her yol /api/v1 altında yayınlanıyor. Sürüm önekte duruyor ki
| kırıcı bir değişiklik geldiğinde v2 açılıp v1 bir süre ayakta kalabilsin —
| mobil uygulama mağazadan güncellenene kadar eski sözleşmeyi konuşur.
|
| Dil `Accept-Language` / `?lang=` üzerinden çözülüyor (SetApiLocale) ve
| yanıtta `Content-Language` ile geri bildiriliyor.
|
| Bütün yanıtlar aynı zarfı taşır — {@see ApiResponse}.
*/

/*
|--------------------------------------------------------------------------
| Kimlik doğrulama
|--------------------------------------------------------------------------
| Bakım modu bu gruba uygulanmıyor: ön yüzde de /giris kapalı sitede açık
| kalıyor, yöneticinin kendi uygulamasından giriş yapabilmesi gerekiyor.
*/

Route::prefix('auth')->name('api.v1.auth.')->group(function (): void {
    // Kaba kuvvete karşı iki ayrı kova: giriş e-posta+IP başına sayılıyor
    // (tek bir hesabı denemek), kayıt yalnız IP başına.
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:api-register')
        ->name('register');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('login');

    // Şifre sıfırlama — altı haneli kod. Kodun kırılmaması hız sınırına
    // bağlı: bir milyon olasılık, sınırsız denemeye açık bırakılsaydı dakikalar
    // içinde tükenirdi. Gerekçenin tamamı PasswordResetCodeService'te.
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:api-password')
        ->name('password.forgot');

    Route::post('/password/reset', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:api-password')
        ->name('password.reset');

    Route::middleware(['auth:sanctum', 'api.active'])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Doğrulanmamış kullanıcı da kendi durumunu görebilmeli: uygulama
        // "e-postanı doğrula" ekranını buna bakarak çiziyor.
        Route::get('/me', [AuthController::class, 'me'])->name('me');

        Route::post('/email/resend', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:api-verification')
            ->name('email.resend');
    });
});

/*
|--------------------------------------------------------------------------
| Hesap
|--------------------------------------------------------------------------
| Ön yüzdeki /hesabim ile aynı kapı: giriş yapmış, hesabı açık ve e-postası
| doğrulanmış kullanıcı. Üçünden biri eksikse web'de de girilemiyor.
*/

Route::prefix('account')
    ->name('api.v1.account.')
    ->middleware(['auth:sanctum', 'api.active', 'api.verified'])
    ->group(function (): void {
        // Avatar aynı istekte gidiyor. PHP çok parçalı gövdeyi yalnız POST'ta
        // ayrıştırdığı için istemci dosyayla birlikte POST + _method=PUT
        // kullanmalı; Laravel bunu bu rotaya eşliyor.
        Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    });

/*
|--------------------------------------------------------------------------
| Genel servisler
|--------------------------------------------------------------------------
| Jeton gerektirmiyor: menü, ayarlar, içerik ve diller sitenin herkese açık
| yüzü. Bakım modunda kapanıyorlar.
*/

Route::middleware('api.available')->group(function (): void {

    // ── Site geneli ──

    Route::get('/languages', [LanguageController::class, 'index'])->name('api.v1.languages.index');
    Route::get('/settings', [SettingController::class, 'index'])->name('api.v1.settings.index');
    Route::get('/translations', [TranslationController::class, 'index'])->name('api.v1.translations.index');

    // Statik sayfalar — Hakkımızda ve mağazaların şart koştuğu yasal metinler
    // (gizlilik politikası, KVKK, kullanım koşulları).
    Route::get('/pages', [PageController::class, 'index'])->name('api.v1.pages.index');
    Route::get('/pages/{slug}', [PageController::class, 'show'])->name('api.v1.pages.show');

    Route::get('/menus', [MenuController::class, 'index'])->name('api.v1.menus.index');
    Route::get('/menus/{location}', [MenuController::class, 'show'])
        ->where('location', '[a-z0-9_-]+')
        ->name('api.v1.menus.show');

    // ── Blog ──
    // 'categories' yolu '{slug}' kalıbından önce tanımlı olmak zorunda değil
    // (ayrı segmentte duruyorlar) ama okunurluk için birlikte durmaları iyi.

    Route::prefix('blog')->name('api.v1.blog.')->group(function (): void {
        Route::get('/categories', [BlogCategoryController::class, 'index'])->name('categories.index');
        Route::get('/posts', [BlogPostController::class, 'index'])->name('posts.index');
        Route::get('/posts/{slug}', [BlogPostController::class, 'show'])->name('posts.show');
    });

    // ── Galeri ──

    Route::prefix('gallery')->name('api.v1.gallery.')->group(function (): void {
        Route::get('/categories', [GalleryCategoryController::class, 'index'])->name('categories.index');
        Route::get('/', [GalleryController::class, 'index'])->name('index');
    });

    // ── Formlar ──

    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:api-contact')
        ->name('api.v1.contact.store');
});

/*
|--------------------------------------------------------------------------
| Bilinmeyen API adresi
|--------------------------------------------------------------------------
| Bu satır olmadan /api/v1/yanlis-adres web tarafındaki fallback'e düşüyor ve
| istemciye JSON yerine bir yönlendirme ya da HTML hata sayfası dönüyordu.
| API rotaları web'den önce kaydedildiği için buradaki fallback öne geçer.
*/

Route::fallback(fn () => ApiResponse::error(__('api.common.not_found'), status: 404))
    ->name('api.v1.fallback');
