<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogCommentController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContentFileDownloadController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RssFeedController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Localized Frontend Routes
|--------------------------------------------------------------------------
| Loaded from routes/web.php inside a {locale} prefix, so every page has one
| URL per language (/tr/blog, /en/blog). Search engines need a distinct URL
| per language before they can be told the pages are translations of each
| other, which is what the sitemap and the hreflang tags then do.
|
| The {locale} segment never has to be passed to route(): SetLocale registers
| it as a URL default, so route('blog.index') keeps working unchanged.
*/

Route::get('/', HomeController::class)->name('home');

// Contact
Route::get('/iletisim', [ContactController::class, 'create'])->name('contact');
Route::post('/iletisim', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

// İçerik ekleri — blog yazısının da sayfanın da ekleri buradan iniyor.
// /{slug} sayfa adresini yakalayan kalıp tek parçalı; bu iki parçalı olduğu
// için çakışmıyor.
Route::get('/dosya/{file}', ContentFileDownloadController::class)->name('content.files.download');

// Blog
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{categorySlug}', [BlogController::class, 'category'])->name('blog.category');
Route::get('/blog/{categorySlug}/{slug}', [BlogController::class, 'show'])->name('blog.show');
Route::post('/blog/yorum', [BlogCommentController::class, 'store'])->middleware('throttle:5,1')->name('blog-comments.store');

// Gallery
Route::get('/galeri', GalleryController::class)->name('gallery');

// Site geneli arama — blog, sayfa, SSS ve galeriyi tek kutudan tarar.
// Yakalayıcı /{slug} kalıbından önce tanımlı olmak zorunda; sonra gelseydi
// "arama" adında bir sayfa varmış gibi aranırdı.
Route::get('/arama', SearchController::class)->name('search');

// FAQ
Route::get('/sikca-sorulan-sorular', FaqController::class)->name('faq');

// RSS Feed — one feed per language.
Route::get('/feed', RssFeedController::class)->name('feed');

/*
|--------------------------------------------------------------------------
| Auth Routes (Guest)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/giris', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/giris', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::get('/kayit', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/kayit', [AuthController::class, 'register'])->middleware('throttle:register');

    Route::get('/sifremi-unuttum', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/sifremi-unuttum', [AuthController::class, 'forgotPassword'])->name('password.email');

    Route::get('/sifre-sifirla/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/sifre-sifirla', [AuthController::class, 'resetPassword'])->name('password.update');

    /*
     * Girişin ikinci adımı. Misafir grubunda: buraya gelen kişinin oturumu
     * henüz açılmadı, yalnız şifresi doğrulandı.
     *
     * Hız sınırı girişinkiyle aynı kovadan: altı haneli bir kod, sınırsız
     * denemeye açık bırakılsaydı dakikalar içinde tükenirdi.
     */
    Route::get('/giris/iki-adim', [AuthController::class, 'showTwoFactorChallenge'])->name('login.two-factor');
    Route::post('/giris/iki-adim', [AuthController::class, 'twoFactorChallenge'])
        ->middleware('throttle:login')
        ->name('login.two-factor.verify');
});

Route::post('/cikis', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Email Verification
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function (): void {
    Route::get('/e-posta-dogrula', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::get('/e-posta-dogrula/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('/e-posta-dogrula/tekrar-gonder', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Account Routes (Authenticated + Verified)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->prefix('hesabim')->name('account.')->group(function (): void {
    Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');

    // Profile
    Route::get('/profil', [AccountController::class, 'profile'])->name('profile');
    Route::put('/profil', [AccountController::class, 'updateProfile'])->name('profile.update');

    /*
     * Cihazlarım. API'deki /auth/devices ile aynı işi görüyor ama iki kaynağı
     * birden listeliyor: tarayıcı oturumları ve uygulama jetonları.
     *
     * Oturum kimliği 40 haneli; kalıp onu bağlıyor ki adres çubuğuna yazılan
     * herhangi bir şey sorguya inmesin.
     */
    Route::get('/cihazlar', [AccountController::class, 'devices'])->name('devices');
    Route::delete('/cihazlar', [AccountController::class, 'destroyOtherDevices'])->name('devices.destroy-others');
    Route::delete('/cihazlar/oturum/{session}', [AccountController::class, 'destroySession'])
        ->where('session', '[A-Za-z0-9]{1,100}')
        ->name('devices.sessions.destroy');
    Route::delete('/cihazlar/uygulama/{token}', [AccountController::class, 'destroyToken'])
        ->whereNumber('token')
        ->name('devices.tokens.destroy');

    /*
     * Güvenlik: iki adımlı doğrulamanın kurulumu, kapatılması ve kurtarma
     * kodları. Kapatma ile kod yenileme şifre onayı istiyor.
     */
    Route::get('/guvenlik', [TwoFactorController::class, 'show'])->name('security');
    Route::post('/guvenlik/iki-adim', [TwoFactorController::class, 'enable'])->name('security.two-factor.enable');
    Route::post('/guvenlik/iki-adim/onayla', [TwoFactorController::class, 'confirm'])
        ->middleware('throttle:6,1')
        ->name('security.two-factor.confirm');
    Route::delete('/guvenlik/iki-adim', [TwoFactorController::class, 'disable'])->name('security.two-factor.disable');
    Route::post('/guvenlik/kurtarma-kodlari', [TwoFactorController::class, 'recoveryCodes'])->name('security.recovery-codes');

    /*
     * Verilerim: taşınabilirlik ve silinme hakkı. İkisi de kişinin kendi
     * kaydına dokunuyor, ikisi de şifreyle korunuyor — indirme dosyası
     * kişisel veri taşıyor, kapatma geri dönüşü zor bir işlem.
     */
    /*
     * Bildirim tercihleri: hangi e-postaların geleceği. Güvenlik postaları
     * (şifre sıfırlama, doğrulama, adres değişikliği uyarısı) listede yok ve
     * kapatılamıyor — kapatılabilseydi hesabı ele geçiren ilk iş onları
     * susturur, sahibi olan bitenden habersiz kalırdı.
     */
    Route::get('/bildirimler', [AccountController::class, 'notifications'])->name('notifications');
    Route::put('/bildirimler', [AccountController::class, 'updateNotifications'])->name('notifications.update');

    Route::get('/veriler', [AccountController::class, 'data'])->name('data');
    Route::get('/veriler/indir', [AccountController::class, 'downloadData'])
        ->middleware('throttle:6,1')
        ->name('data.download');
    Route::delete('/veriler/hesabi-kapat', [AccountController::class, 'closeAccount'])->name('data.close');
});

/*
|--------------------------------------------------------------------------
| Static pages (catch-all for dynamic pages — must be last)
|--------------------------------------------------------------------------
*/

Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show')->where('slug', '^(?!admin).*$');
