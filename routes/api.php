<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlogCategoryController;
use App\Http\Controllers\Api\V1\BlogCommentController;
use App\Http\Controllers\Api\V1\BlogPostController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\FaqController;
use App\Http\Controllers\Api\V1\GalleryCategoryController;
use App\Http\Controllers\Api\V1\GalleryController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\LanguageController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\NewsletterController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\SliderController;
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
        // Çıkış bilerek yetkisiz: bir jeton her zaman kendini iptal
        // edebilmeli, yoksa dar yetkili bir jeton ele geçtiğinde sahibi onu
        // kapatamaz.
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Doğrulanmamış kullanıcı da kendi durumunu görebilmeli: uygulama
        // "e-postanı doğrula" ekranını buna bakarak çiziyor.
        Route::get('/me', [AuthController::class, 'me'])
            ->middleware('abilities:profile:read')
            ->name('me');

        Route::post('/email/resend', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:api-verification')
            ->name('email.resend');

        // "Cihazlarım". Doğrulanmış e-posta şartı bilerek yok: hesabına
        // şüpheli bir erişim olduğunu düşünen kişi, doğrulama adımını
        // tamamlayamamış olsa bile oturumları kapatabilmeli.
        Route::middleware('abilities:devices:manage')->group(function (): void {
            Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
            Route::delete('/devices', [DeviceController::class, 'destroyOthers'])->name('devices.destroy-others');
            Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])
                ->whereNumber('device')
                ->name('devices.destroy');
        });
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
        Route::put('/profile', [AccountController::class, 'updateProfile'])
            ->middleware('abilities:profile:write')
            ->name('profile.update');
    });

/*
|--------------------------------------------------------------------------
| Genel servisler
|--------------------------------------------------------------------------
| Jeton gerektirmiyor: menü, ayarlar, içerik ve diller sitenin herkese açık
| yüzü. Bakım modunda kapanıyorlar.
*/

/*
 * Seyrek değişen uçlar ETag ile dönüyor: istemci `If-None-Match` gönderdiğinde
 * içerik değişmemişse 304 alıyor ve gövde hiç inmiyor. Çeviri sözlüğü yüz
 * kilobayta yaklaşabildiği için mobil veri açısından en ucuz kazanç bu.
 *
 * İçerik listeleri (yazılar, galeri, açılış ekranı) bilerek dışarıda: orada
 * tazelik önbellekten daha değerli ve sayfalama zaten ETag'i sürekli
 * değiştiriyor.
 *
 * Dile duyarlılık `Vary` ile bildiriliyor (SetApiLocale) — olmasaydı araya
 * giren her önbellek ilk gelenin dilini ötekilere de servis ederdi.
 */
$cacheable = 'cache.headers:public;max_age=' . (int) config('api.cache.max_age', 60) . ';etag';

Route::middleware('api.available')->group(function () use ($cacheable): void {

    // ── Açılış ekranı ──
    // Parçalar aşağıda ayrı ayrı da yayında; bu uç üçünü bir araya getiriyor
    // çünkü uygulama açılışında üç gidiş dönüş, ekranın gecikmesinin büyük
    // kısmı demek.
    Route::get('/home', HomeController::class)->name('api.v1.home');

    // ── Site geneli ──

    Route::get('/languages', [LanguageController::class, 'index'])->middleware($cacheable)->name('api.v1.languages.index');
    Route::get('/sliders', [SliderController::class, 'index'])->middleware($cacheable)->name('api.v1.sliders.index');
    Route::get('/faqs', [FaqController::class, 'index'])->middleware($cacheable)->name('api.v1.faqs.index');

    // Site geneli arama — blog, sayfa, SSS ve galeriyi tek sorguda tarar.
    // Önbelleklenmiyor: her terim ayrı bir sonuç ve ETag'ler hiç
    // tekrarlanmadan birikirdi.
    Route::get('/search', SearchController::class)->name('api.v1.search');
    Route::get('/settings', [SettingController::class, 'index'])->middleware($cacheable)->name('api.v1.settings.index');
    Route::get('/translations', [TranslationController::class, 'index'])->middleware($cacheable)->name('api.v1.translations.index');

    // Statik sayfalar — Hakkımızda ve mağazaların şart koştuğu yasal metinler
    // (gizlilik politikası, KVKK, kullanım koşulları).
    Route::get('/pages', [PageController::class, 'index'])->middleware($cacheable)->name('api.v1.pages.index');
    Route::get('/pages/{slug}', [PageController::class, 'show'])->middleware($cacheable)->name('api.v1.pages.show');

    Route::get('/menus', [MenuController::class, 'index'])->middleware($cacheable)->name('api.v1.menus.index');
    Route::get('/menus/{location}', [MenuController::class, 'show'])
        ->where('location', '[a-z0-9_-]+')
        ->middleware($cacheable)
        ->name('api.v1.menus.show');

    // ── Blog ──
    // 'categories' yolu '{slug}' kalıbından önce tanımlı olmak zorunda değil
    // (ayrı segmentte duruyorlar) ama okunurluk için birlikte durmaları iyi.

    Route::prefix('blog')->name('api.v1.blog.')->group(function () use ($cacheable): void {
        Route::get('/categories', [BlogCategoryController::class, 'index'])->middleware($cacheable)->name('categories.index');
        Route::get('/posts', [BlogPostController::class, 'index'])->name('posts.index');
        Route::get('/posts/{slug}', [BlogPostController::class, 'show'])->name('posts.show');

        // Yorumlar detaydan ayrı: kırk yorumlu bir yazının detayı, yorumları
        // hiç açmayan bir ekran için bile kırk yorum taşımasın.
        Route::get('/posts/{slug}/comments', [BlogCommentController::class, 'index'])->name('comments.index');

        // reCAPTCHA mobilde yok; yorum alanları da spam'in birinci hedefi.
        // Buradaki tek fren hız sınırı — ikincisi gecikmeli: yorum onay
        // bekleyerek kaydediliyor, yani spam yayına değil kuyruğa düşüyor.
        Route::post('/comments', [BlogCommentController::class, 'store'])
            ->middleware('throttle:api-comment')
            ->name('comments.store');
    });

    // ── Galeri ──

    Route::prefix('gallery')->name('api.v1.gallery.')->group(function () use ($cacheable): void {
        Route::get('/categories', [GalleryCategoryController::class, 'index'])->middleware($cacheable)->name('categories.index');
        Route::get('/', [GalleryController::class, 'index'])->name('index');
    });

    // ── Formlar ──

    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:api-contact')
        ->name('api.v1.contact.store');

    // Abonelikten çıkma bilerek yok: çıkış bağlantısı her kampanya mailinin
    // altında, imzalı ve girişsiz. Uygulamaya taşımak çıkışı zorlaştırırdı.
    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
        ->middleware('throttle:api-newsletter')
        ->name('api.v1.newsletter.subscribe');
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
