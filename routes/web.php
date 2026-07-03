<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AnalyticsTrackController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogCommentController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RssFeedController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Frontend Routes
|--------------------------------------------------------------------------
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
| Account Routes (Authenticated)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->prefix('hesabim')->name('account.')->group(function (): void {
    Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');

    // Profile
    Route::get('/profil', [AccountController::class, 'profile'])->name('profile');
    Route::put('/profil', [AccountController::class, 'updateProfile'])->name('profile.update');
});

// Sitemap (XML — machine-readable for search engines)
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// RSS Feed
Route::get('/feed', RssFeedController::class)->name('feed');

// Analytics tracking endpoint (async, does not affect page speed)
Route::post('/api/analytics/track', [AnalyticsTrackController::class, 'store'])
    ->name('analytics.track')
    ->middleware('throttle:60,1');

// Static pages (catch-all for dynamic pages — must be last)
Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show')->where('slug', '^(?!admin).*$');
