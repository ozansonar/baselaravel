<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AnalyticsTrackController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogCommentController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CityLandingPageController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapPageController;
use App\Http\Controllers\RssFeedController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Frontend Routes
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');

// Products
Route::get('/urunler', [ProductController::class, 'all'])->name('products.all');
Route::get('/urunler/{categorySlug}', [ProductController::class, 'index'])->name('products.index');
Route::get('/urun/{slug}', [ProductController::class, 'show'])->name('products.show');

// Product Reviews
Route::post('/urun/degerlendirme', [ProductReviewController::class, 'store'])->middleware('throttle:5,1')->name('reviews.store');

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

// Search
Route::get('/ara', [SearchController::class, 'index'])->name('search');
Route::get('/ara/oneri', [SearchController::class, 'suggest'])->name('search.suggest');

// Location (Cities & Districts)
Route::get('/api/iller', [LocationController::class, 'cities'])->name('location.cities');
Route::get('/api/iller/{city}/ilceler', [LocationController::class, 'districts'])->name('location.districts');

// Cart
Route::get('/sepet', [CartController::class, 'index'])->name('cart.index');
Route::post('/sepet/ekle', [CartController::class, 'add'])->name('cart.add');
Route::patch('/sepet/guncelle', [CartController::class, 'update'])->name('cart.update');
Route::delete('/sepet/kaldir', [CartController::class, 'remove'])->name('cart.remove');
Route::delete('/sepet/temizle', [CartController::class, 'clear'])->name('cart.clear');
Route::get('/sepet/mini', [CartController::class, 'miniCart'])->name('cart.mini');

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
| Checkout Routes (Authenticated)
|--------------------------------------------------------------------------
*/

Route::prefix('siparis')->name('checkout.')->group(function (): void {
    Route::get('/', [CheckoutController::class, 'index'])->name('index');
    Route::post('/', [CheckoutController::class, 'store'])->name('store');
    Route::post('/kupon', [CheckoutController::class, 'applyCoupon'])->name('apply-coupon');
    Route::get('/basarili/{orderNumber}', [CheckoutController::class, 'success'])->name('success');
    Route::get('/takip/{orderNumber}/{trackingToken}', [OrderTrackingController::class, 'show'])->name('tracking');
});

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

    // Orders
    Route::get('/siparisler', [AccountController::class, 'orders'])->name('orders');
    Route::get('/siparisler/{id}', [AccountController::class, 'orderShow'])->name('orders.show');

    // Addresses
    Route::get('/adresler', [AccountController::class, 'addresses'])->name('addresses');
    Route::get('/adresler/yeni', [AccountController::class, 'addressCreate'])->name('addresses.create');
    Route::post('/adresler', [AccountController::class, 'addressStore'])->name('addresses.store');
    Route::get('/adresler/{address}/duzenle', [AccountController::class, 'addressEdit'])->name('addresses.edit');
    Route::put('/adresler/{address}', [AccountController::class, 'addressUpdate'])->name('addresses.update');
    Route::delete('/adresler/{address}', [AccountController::class, 'addressDestroy'])->name('addresses.destroy');
    Route::patch('/adresler/{address}/varsayilan', [AccountController::class, 'addressSetDefault'])->name('addresses.set-default');
});

// Sitemap (XML — Google için makine-okunur)
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Site Haritası (HTML — kullanıcı için kategorize liste, topic siloing)
Route::get('/site-haritasi', SitemapPageController::class)->name('sitemap-page');

// RSS Feed
Route::get('/feed', RssFeedController::class)->name('feed');

// Google Merchant Center XML feed (Scheduled Fetch için)
Route::get('/feeds/google-merchant.xml', \App\Http\Controllers\GoogleMerchantFeedController::class)
    ->name('feeds.google-merchant');

// Analytics tracking endpoint (asenkron, site hızını etkilemez)
Route::post('/api/analytics/track', [AnalyticsTrackController::class, 'store'])
    ->name('analytics.track')
    ->middleware('throttle:60,1');

// Static pages — 301 redirect from old prefix
Route::get('/sayfa/{slug}', fn (string $slug) => redirect('/' . $slug, 301));

// City landing pages: /{city_slug}-koy-urunleri (must be before catch-all page route)
Route::get('/{citySlug}-koy-urunleri', [CityLandingPageController::class, 'show'])
    ->name('city.landing')
    ->where('citySlug', '[a-z0-9\-]+');

// Static pages (catch-all for dynamic pages — must be last)
Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show')->where('slug', '^(?!admin).*$');
