<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogCommentController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RssFeedController;
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

// Blog
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{categorySlug}', [BlogController::class, 'category'])->name('blog.category');
Route::get('/blog/{categorySlug}/{slug}', [BlogController::class, 'show'])->name('blog.show');
Route::post('/blog/yorum', [BlogCommentController::class, 'store'])->middleware('throttle:5,1')->name('blog-comments.store');

// Gallery
Route::get('/galeri', GalleryController::class)->name('gallery');

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
});

/*
|--------------------------------------------------------------------------
| Static pages (catch-all for dynamic pages — must be last)
|--------------------------------------------------------------------------
*/

Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show')->where('slug', '^(?!admin).*$');
