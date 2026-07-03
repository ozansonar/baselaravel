<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AiGenerateController;
use App\Http\Controllers\Admin\AiLogController;
use App\Http\Controllers\Admin\AiPromptSettingController;
use App\Http\Controllers\Admin\AiReviewController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BlogAiController;
use App\Http\Controllers\Admin\BlogCategoryController;
use App\Http\Controllers\Admin\BlogCommentController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CityAiController;
use App\Http\Controllers\Admin\CityLandingPageController as AdminCityLandingPageController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GalleryCategoryController;
use App\Http\Controllers\Admin\GalleryItemController;
use App\Http\Controllers\Admin\GoogleReviewController;
use App\Http\Controllers\Admin\InstagramLogController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\InstagramPostBulkController;
use App\Http\Controllers\Admin\InstagramPostController;
use App\Http\Controllers\Admin\BestTimeAnalyticsController;
use App\Http\Controllers\Admin\CaptionAbTestController;
use App\Http\Controllers\Admin\HashtagAnalyticsController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\ImageLibraryController;
use App\Http\Controllers\Admin\OnboardingController;
use App\Http\Controllers\Admin\ShotListController;
use App\Http\Controllers\Admin\SpecialDayController;
use App\Http\Controllers\Admin\YouTubeVideoController;
use App\Http\Controllers\Admin\MailLogController;
use App\Http\Controllers\Admin\MailTemplateController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\RedirectController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PageAiController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\ProductAiController;
use App\Http\Controllers\Admin\ProductTopicController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TiktokOAuthController;
use App\Http\Controllers\Admin\PopupController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
| Prefix: /admin
| Middleware: web, admin
| Name prefix: admin.
|--------------------------------------------------------------------------
*/

// Dashboard
Route::get('/', DashboardController::class)->name('dashboard');

// Categories
Route::resource('categories', CategoryController::class)->except('show');
Route::patch('categories/{category}/restore', [CategoryController::class, 'restore'])->name('categories.restore')->withTrashed();

// Products
Route::post('products/ai-fill', [ProductAiController::class, 'fill'])->name('products.ai-fill');
Route::resource('products', ProductController::class);
Route::patch('products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore')->withTrashed();
Route::post('products/{product}/images', [ProductController::class, 'uploadImage'])->name('products.images.upload');
Route::patch('products/{product}/images/reorder', [ProductController::class, 'reorderImages'])->name('products.images.reorder');
Route::delete('products/images/{image}', [ProductController::class, 'deleteImage'])->name('products.images.destroy');
Route::post('products/{product}/enhance-image', [ProductController::class, 'enhanceImage'])->name('products.enhance-image');
Route::post('products/{product}/generate-faq', [ProductController::class, 'generateFaq'])->name('products.generate-faq');

// Topic Cluster (Modül 4) — ürün başına 7 blog konusu öner + tek tıkla blog'a çevir
Route::get('product-topics', [ProductTopicController::class, 'index'])->name('product-topics.index');
Route::post('product-topics/generate', [ProductTopicController::class, 'generate'])->name('product-topics.generate')
    ->middleware('throttle:6,1');
Route::post('product-topics/{productTopic}/convert', [ProductTopicController::class, 'convertToBlog'])->name('product-topics.convert')
    ->middleware('throttle:30,1');
Route::delete('product-topics/{productTopic}', [ProductTopicController::class, 'destroy'])->name('product-topics.destroy');

// Orders
Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
Route::delete('orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');

// Campaigns
Route::resource('campaigns', CampaignController::class)->except('show');
Route::patch('campaigns/{campaign}/restore', [CampaignController::class, 'restore'])->name('campaigns.restore')->withTrashed();

// Pages
Route::resource('pages', PageController::class)->except('show');
Route::patch('pages/{page}/restore', [PageController::class, 'restore'])->name('pages.restore')->withTrashed();
Route::post('pages/ai-fill', [PageAiController::class, 'fill'])->name('pages.ai-fill');

// City Landing Pages (Şehir Bazlı Hizmet Sayfaları)
Route::post('city-pages/ai-fill', [CityAiController::class, 'fill'])->name('city-pages.ai-fill');
Route::get('city-pages/bulk-create', [AdminCityLandingPageController::class, 'bulkCreatePage'])->name('city-pages.bulk-create');
Route::post('city-pages/bulk-store', [AdminCityLandingPageController::class, 'bulkStore'])->name('city-pages.bulk-store');
Route::resource('city-pages', AdminCityLandingPageController::class)
    ->except('show')
    ->parameters(['city-pages' => 'cityPage']);
Route::patch('city-pages/{id}/restore', [AdminCityLandingPageController::class, 'restore'])
    ->name('city-pages.restore')
    ->withTrashed();

// Sliders
Route::resource('sliders', SliderController::class)->except('show');
Route::patch('sliders/{slider}/restore', [SliderController::class, 'restore'])->name('sliders.restore')->withTrashed();

// Popups
Route::resource('popups', PopupController::class)->except('show');
Route::patch('popups/{popup}/restore', [PopupController::class, 'restore'])->name('popups.restore')->withTrashed();

// Gallery Categories
Route::resource('gallery-categories', GalleryCategoryController::class)->except('show');
Route::patch('gallery-categories/{galleryCategory}/restore', [GalleryCategoryController::class, 'restore'])->name('gallery-categories.restore')->withTrashed();

// Gallery Items
Route::resource('gallery-items', GalleryItemController::class)->except('show');
Route::patch('gallery-items/{galleryItem}/restore', [GalleryItemController::class, 'restore'])->name('gallery-items.restore')->withTrashed();

// FAQs
Route::resource('faqs', FaqController::class)->except('show');
Route::patch('faqs/{faq}/restore', [FaqController::class, 'restore'])->name('faqs.restore')->withTrashed();

// Reviews
Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
Route::get('reviews/{review}', [ReviewController::class, 'show'])->name('reviews.show');
Route::put('reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
Route::patch('reviews/{review}/approve', [ReviewController::class, 'approve'])->name('reviews.approve');
Route::patch('reviews/{review}/reject', [ReviewController::class, 'reject'])->name('reviews.reject');
Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
Route::patch('reviews/{review}/restore', [ReviewController::class, 'restore'])->name('reviews.restore')->withTrashed();

// AI Review Generation
Route::get('ai-reviews/create', [AiReviewController::class, 'create'])->name('ai-reviews.create');
Route::post('ai-reviews/generate', [AiReviewController::class, 'generate'])->name('ai-reviews.generate')
    ->middleware('throttle:3,1');

// Google Reviews
Route::get('google-reviews', [GoogleReviewController::class, 'index'])->name('google-reviews.index');
Route::post('google-reviews', [GoogleReviewController::class, 'store'])->name('google-reviews.store');
Route::patch('google-reviews/{googleReview}/toggle', [GoogleReviewController::class, 'toggleVisibility'])->name('google-reviews.toggle');
Route::post('google-reviews/sync', [GoogleReviewController::class, 'sync'])->name('google-reviews.sync');
Route::delete('google-reviews/{googleReview}', [GoogleReviewController::class, 'destroy'])->name('google-reviews.destroy');
Route::patch('google-reviews/{googleReview}/restore', [GoogleReviewController::class, 'restore'])->name('google-reviews.restore')->withTrashed();

// YouTube Videos
Route::get('youtube-videos', [YouTubeVideoController::class, 'index'])->name('youtube-videos.index');
Route::patch('youtube-videos/{youtubeVideo}/toggle', [YouTubeVideoController::class, 'toggleVisibility'])->name('youtube-videos.toggle');
Route::post('youtube-videos/sync', [YouTubeVideoController::class, 'sync'])->name('youtube-videos.sync');
Route::delete('youtube-videos/{youtubeVideo}', [YouTubeVideoController::class, 'destroy'])->name('youtube-videos.destroy');
Route::patch('youtube-videos/{youtubeVideo}/restore', [YouTubeVideoController::class, 'restore'])->name('youtube-videos.restore')->withTrashed();

// Instagram Posts
Route::get('instagram-posts/calendar', [InstagramPostController::class, 'calendar'])->name('instagram-posts.calendar');

// Instagram Bulk Schedule (Toplu Plan)
Route::get('instagram-posts/bulk', [InstagramPostBulkController::class, 'form'])->name('instagram-posts.bulk.form');
Route::get('instagram-posts/bulk/template', [InstagramPostBulkController::class, 'template'])->name('instagram-posts.bulk.template');
Route::post('instagram-posts/bulk/preview', [InstagramPostBulkController::class, 'preview'])->name('instagram-posts.bulk.preview');
Route::post('instagram-posts/bulk/topics', [InstagramPostBulkController::class, 'suggestTopics'])->name('instagram-posts.bulk.topics');
Route::post('instagram-posts/bulk/from-tsv/preview',  [InstagramPostBulkController::class, 'fromTsvPreview'])->name('instagram-posts.bulk.from-tsv.preview');
Route::post('instagram-posts/bulk/from-tsv/download', [InstagramPostBulkController::class, 'fromTsvDownload'])->name('instagram-posts.bulk.from-tsv.download');
Route::post('instagram-posts/bulk/from-tsv/import',   [InstagramPostBulkController::class, 'fromTsvImport'])->name('instagram-posts.bulk.from-tsv.import');
Route::post('instagram-posts/bulk/upload', [InstagramPostBulkController::class, 'upload'])->name('instagram-posts.bulk.upload');
Route::get('instagram-posts/bulk/{bulkImport}', [InstagramPostBulkController::class, 'show'])->name('instagram-posts.bulk.show')->whereNumber('bulkImport');
Route::get('instagram-posts/bulk/{bulkImport}/status', [InstagramPostBulkController::class, 'status'])->name('instagram-posts.bulk.status')->whereNumber('bulkImport');

// Vertex ile Aylık Plan
Route::get('instagram-posts/vertex-plan', [\App\Http\Controllers\Admin\VertexPlanController::class, 'index'])->name('instagram-posts.vertex-plan');
Route::post('instagram-posts/vertex-plan/preview', [\App\Http\Controllers\Admin\VertexPlanController::class, 'preview'])->name('instagram-posts.vertex-plan.preview');
Route::post('instagram-posts/vertex-plan/confirm', [\App\Http\Controllers\Admin\VertexPlanController::class, 'confirm'])->name('instagram-posts.vertex-plan.confirm');

// Hashtag havuzu — Instagram caption AI için manuel hashtag yönetimi
Route::prefix('hashtag-pool')->name('hashtag-pool.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\HashtagPoolController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\Admin\HashtagPoolController::class, 'store'])->name('store');
    Route::post('/bulk', [\App\Http\Controllers\Admin\HashtagPoolController::class, 'bulkStore'])->name('bulk-store');
    Route::put('/{hashtagPool}', [\App\Http\Controllers\Admin\HashtagPoolController::class, 'update'])->name('update');
    Route::delete('/{hashtagPool}', [\App\Http\Controllers\Admin\HashtagPoolController::class, 'destroy'])->name('destroy');
    Route::patch('/{hashtagPool}/toggle', [\App\Http\Controllers\Admin\HashtagPoolController::class, 'toggle'])->name('toggle');
});
Route::post('instagram-posts/{instagramPost}/publish-now', [InstagramPostController::class, 'publishNow'])->name('instagram-posts.publish-now');
Route::post('instagram-posts/{instagramPost}/reset-retry', [InstagramPostController::class, 'resetRetry'])->name('instagram-posts.reset-retry');
// TikTok cross-post recovery (docs/tiktok.md Bölüm 6.4)
Route::post('instagram-posts/{instagramPost}/tt-publish-now', [InstagramPostController::class, 'tiktokPublishNow'])->name('instagram-posts.tt-publish-now');
Route::post('instagram-posts/{instagramPost}/tt-reset-retry', [InstagramPostController::class, 'tiktokResetRetry'])->name('instagram-posts.tt-reset-retry');
Route::patch('instagram-posts/{id}/restore', [InstagramPostController::class, 'restore'])->name('instagram-posts.restore')->withTrashed();
Route::post('instagram-posts/bulk-action', [InstagramPostController::class, 'bulkAction'])->name('instagram-posts.bulk-action');

// ─── Özel Günler ──────────────────────────────────────────────
Route::prefix('ozel-gunler')->name('special-days.')->group(function () {
    Route::get('/',                              [SpecialDayController::class, 'index'])->name('index');
    Route::get('/yeni',                          [SpecialDayController::class, 'create'])->name('create');
    Route::post('/',                             [SpecialDayController::class, 'store'])->name('store');
    Route::post('/ai-getir',                     [SpecialDayController::class, 'fetchAi'])->name('fetch-ai');
    Route::post('/formul-seed',                  [SpecialDayController::class, 'seedFormulas'])->name('seed-formulas');
    Route::get('/{specialDay}/duzenle',          [SpecialDayController::class, 'edit'])->name('edit');
    Route::put('/{specialDay}',                  [SpecialDayController::class, 'update'])->name('update');
    Route::delete('/{specialDay}',               [SpecialDayController::class, 'destroy'])->name('destroy');
    Route::get('/{specialDay}/galeri',           [SpecialDayController::class, 'gallery'])->name('gallery');
    Route::post('/{specialDay}/galeri/yukle',    [SpecialDayController::class, 'uploadImages'])->name('gallery.upload');
    Route::patch('/galeri-image/{image}',        [SpecialDayController::class, 'updateImage'])->name('gallery.update-image');
    Route::delete('/galeri-image/{image}',       [SpecialDayController::class, 'destroyImage'])->name('gallery.destroy-image');
});

// ─── Görsel Kütüphanesi (Hibrit kütüphane sistemi) ────────────
Route::prefix('gorsel-kutuphanesi')->name('image-library.')->group(function () {
    Route::get('/',                  [ImageLibraryController::class, 'index'])->name('index');
    Route::get('/analiz',            [ImageLibraryController::class, 'analyze'])->name('analyze');
    Route::post('/analiz/ai-oneri',  [ImageLibraryController::class, 'aiSuggestions'])->name('ai-suggestions');
    Route::get('/yukle',             [ImageLibraryController::class, 'uploadForm'])->name('upload-form');
    Route::post('/yukle',            [ImageLibraryController::class, 'upload'])->name('upload');
    Route::post('/yukle/tek',        [ImageLibraryController::class, 'uploadSingle'])->name('upload-single');
    Route::post('/yukle/poster',     [ImageLibraryController::class, 'uploadVideoPoster'])->name('upload-poster');
    Route::get('/{image}/duzenle',   [ImageLibraryController::class, 'edit'])->name('edit');
    Route::put('/{image}',           [ImageLibraryController::class, 'update'])->name('update');
    Route::delete('/{image}',        [ImageLibraryController::class, 'destroy'])->name('destroy');
    Route::post('/{image}/auto-tag', [ImageLibraryController::class, 'autoTag'])->name('auto-tag');
    Route::post('/batch/auto-tag',   [ImageLibraryController::class, 'batchAutoTag'])->name('batch-auto-tag');
    Route::post('/batch/update',     [ImageLibraryController::class, 'bulkUpdate'])->name('bulk-update');
    Route::post('/plan/olustur',     [ImageLibraryController::class, 'buildPlan'])->name('build-plan');
    Route::post('/plan/uygula',      [ImageLibraryController::class, 'executePlan'])->name('execute-plan');
    Route::get('/export',            [ImageLibraryController::class, 'export'])->name('export');
    Route::post('/import',           [ImageLibraryController::class, 'import'])->name('import');
});

// ─── Sistem Sağlık ────────────────────────────────────────────
Route::get('sistem-saglik',       [HealthController::class, 'index'])->name('system-health.index');
Route::get('sistem-saglik/json',  [HealthController::class, 'json'])->name('system-health.json');

// ─── TikTok OAuth (docs/tiktok.md Bölüm 2.11 + Faz 2) ────────
Route::get('tiktok/oauth/connect',  [TiktokOAuthController::class, 'connect'])->name('tiktok.oauth.connect');
Route::get('tiktok/oauth/callback', [TiktokOAuthController::class, 'callback'])->name('tiktok.oauth.callback');

// ─── Aktivite Logları (Audit Trail) ──────────────────────────
Route::get('aktivite-loglari',                   [AuditLogController::class, 'index'])->name('audit-logs.index');
Route::get('aktivite-loglari/{auditLog}',        [AuditLogController::class, 'show'])->name('audit-logs.show');

// ─── Bildirimler (Admin Notification Center) ─────────────────
Route::get('bildirimler',                       [NotificationController::class, 'index'])->name('notifications.index');
Route::get('bildirimler/recent',                [NotificationController::class, 'recent'])->name('notifications.recent');
Route::post('bildirimler/{notification}/okundu',[NotificationController::class, 'markRead'])->name('notifications.mark-read');
Route::post('bildirimler/tumunu-okundu',        [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
Route::delete('bildirimler/{notification}',     [NotificationController::class, 'destroy'])->name('notifications.destroy');

// ─── Hashtag Performans Raporu ───────────────────────────────
Route::get('hashtag-raporu', [HashtagAnalyticsController::class, 'index'])->name('hashtag-analytics.index');

// ─── En İyi Paylaşım Saati ───────────────────────────────────
Route::get('en-iyi-saat', [BestTimeAnalyticsController::class, 'index'])->name('best-time-analytics.index');

// ─── Caption A/B Test ────────────────────────────────────────
Route::get('caption-ab-test', [CaptionAbTestController::class, 'index'])->name('caption-ab-test.index');

// ─── Onboarding (İlk Kurulum Sihirbazı) ──────────────────────
Route::get('kurulum',          [OnboardingController::class, 'index'])->name('onboarding.index');
Route::post('kurulum/tamamla', [OnboardingController::class, 'complete'])->name('onboarding.complete');
Route::post('kurulum/reset',   [OnboardingController::class, 'reset'])->name('onboarding.reset');

// ─── Yedekler ────────────────────────────────────────────────
Route::get('yedekler',                  [BackupController::class, 'index'])->name('backups.index');
Route::post('yedekler/olustur',         [BackupController::class, 'create'])->name('backups.create');
Route::get('yedekler/indir/{filename}', [BackupController::class, 'download'])->where('filename', '[A-Za-z0-9\-_.]+')->name('backups.download');
Route::delete('yedekler/{filename}',    [BackupController::class, 'destroy'])->where('filename', '[A-Za-z0-9\-_.]+')->name('backups.destroy');

// ─── AI Çek Listesi (Shot List) ──────────────────────────────
Route::prefix('cek-listesi')->name('shot-list.')->group(function () {
    Route::get('/',                  [ShotListController::class, 'index'])->name('index');
    Route::post('/',                 [ShotListController::class, 'store'])->name('store');
    Route::post('/bulk-from-ai',     [ShotListController::class, 'bulkAddFromAi'])->name('bulk-from-ai');
    Route::patch('/{item}',          [ShotListController::class, 'update'])->name('update');
    Route::delete('/{item}',         [ShotListController::class, 'destroy'])->name('destroy');
});

Route::post('instagram-posts/generate-caption', [InstagramPostController::class, 'generateCaption'])->name('instagram-posts.generate-caption');
Route::post('instagram-posts/generate-image', [InstagramPostController::class, 'generateImage'])->name('instagram-posts.generate-image');
Route::post('instagram-posts/auto-fix-image', [InstagramPostController::class, 'autoFixImage'])->name('instagram-posts.auto-fix-image');
Route::get('instagram-posts/{instagramPost}/show', [InstagramPostController::class, 'show'])->name('instagram-posts.show');
Route::resource('instagram-posts', InstagramPostController::class)->except('show');

// Instagram API Logları
Route::get('instagram-logs', [InstagramLogController::class, 'index'])->name('instagram-logs.index');
Route::post('instagram-logs/clear', [InstagramLogController::class, 'clear'])->name('instagram-logs.clear');
Route::post('instagram-logs/replay/{instagramPost}', [InstagramLogController::class, 'replay'])->name('instagram-logs.replay');
Route::get('instagram-logs/{log}', [InstagramLogController::class, 'show'])->name('instagram-logs.show')->whereNumber('log');

// Contact Messages
Route::get('contact-messages', [ContactMessageController::class, 'index'])->name('contact-messages.index');
Route::post('contact-messages/mark-all-read', [ContactMessageController::class, 'markAllRead'])->name('contact-messages.mark-all-read');
Route::get('contact-messages/{contactMessage}', [ContactMessageController::class, 'show'])->name('contact-messages.show')->withTrashed();
Route::post('contact-messages/{contactMessage}/reply', [ContactMessageController::class, 'reply'])->name('contact-messages.reply');
Route::delete('contact-messages/{contactMessage}', [ContactMessageController::class, 'destroy'])->name('contact-messages.destroy');
Route::patch('contact-messages/{contactMessage}/restore', [ContactMessageController::class, 'restore'])->name('contact-messages.restore')->withTrashed();

// Users
Route::resource('users', UserController::class)->except('show');
Route::patch('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore')->withTrashed();

// Redirects
Route::resource('redirects', RedirectController::class)->except('show');
Route::patch('redirects/{redirect}/restore', [RedirectController::class, 'restore'])->name('redirects.restore')->withTrashed();
Route::patch('redirects/{redirect}/toggle-active', [RedirectController::class, 'toggleActive'])->name('redirects.toggle-active');

// Analytics (Ziyaretçi İstatistikleri)
Route::prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/', [AnalyticsController::class, 'index'])->name('index');
    Route::get('/visits', [AnalyticsController::class, 'visits'])->name('visits');
    Route::get('/chart/{type}', [AnalyticsController::class, 'chart'])->name('chart');
});

// Menus
Route::prefix('menus')->name('menus.')->group(function () {
    Route::get('/', [MenuController::class, 'index'])->name('index');
    Route::put('{menu}', [MenuController::class, 'update'])->name('update');
    Route::get('{menu}/items', [MenuItemController::class, 'index'])->name('items.index');
    Route::post('{menu}/items', [MenuItemController::class, 'store'])->name('items.store');
    Route::patch('{menu}/items/reorder', [MenuItemController::class, 'reorder'])->name('items.reorder');
    Route::put('items/{item}', [MenuItemController::class, 'update'])->name('items.update');
    Route::delete('items/{item}', [MenuItemController::class, 'destroy'])->name('items.destroy');
    Route::patch('items/{item}/restore', [MenuItemController::class, 'restore'])->name('items.restore')->withTrashed();
});

// Settings
Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
Route::post('settings/test-email', [SettingController::class, 'testEmail'])->name('settings.test-email');
Route::post('settings/clear-cache', [SettingController::class, 'clearCache'])->name('settings.clear-cache');
Route::post('settings/test-google-api', [SettingController::class, 'testGoogleApi'])->name('settings.test-google-api');
Route::post('settings/test-youtube-api', [SettingController::class, 'testYoutubeApi'])->name('settings.test-youtube-api');
Route::post('settings/test-instagram', [SettingController::class, 'testInstagram'])->name('settings.test-instagram');
Route::post('settings/refresh-instagram-token', [SettingController::class, 'refreshInstagramToken'])->name('settings.refresh-instagram-token');
Route::post('settings/test-facebook', [SettingController::class, 'testFacebook'])->name('settings.test-facebook');
Route::post('settings/fetch-facebook-page-token', [SettingController::class, 'fetchFacebookPageToken'])->name('settings.fetch-facebook-page-token');
Route::post('settings/test-telegram', [SettingController::class, 'testTelegram'])->name('settings.test-telegram');

// Blog Categories (İçerik Kategorileri)
Route::resource('blog-categories', BlogCategoryController::class)->except('show');
Route::patch('blog-categories/{blogCategory}/restore', [BlogCategoryController::class, 'restore'])->name('blog-categories.restore')->withTrashed();

// Blog Posts (İçerikler)
Route::resource('blog-posts', BlogPostController::class);
Route::patch('blog-posts/{blogPost}/restore', [BlogPostController::class, 'restore'])->name('blog-posts.restore')->withTrashed();
Route::post('blog-posts/{blogPost}/share-to-social', [BlogPostController::class, 'shareToSocial'])->name('blog-posts.share-to-social');
Route::post('blog-posts/ai-fill', [BlogAiController::class, 'fill'])->name('blog-posts.ai-fill');

// Blog Comments (Yorumlar)
Route::get('blog-comments', [BlogCommentController::class, 'index'])->name('blog-comments.index');
Route::get('blog-comments/{blogComment}', [BlogCommentController::class, 'show'])->name('blog-comments.show');
Route::patch('blog-comments/{blogComment}/approve', [BlogCommentController::class, 'approve'])->name('blog-comments.approve');
Route::patch('blog-comments/{blogComment}/reject', [BlogCommentController::class, 'reject'])->name('blog-comments.reject');
Route::delete('blog-comments/{blogComment}', [BlogCommentController::class, 'destroy'])->name('blog-comments.destroy');
Route::patch('blog-comments/{blogComment}/restore', [BlogCommentController::class, 'restore'])->name('blog-comments.restore')->withTrashed();

// Mail Templates
Route::get('mail-templates', [MailTemplateController::class, 'index'])->name('mail-templates.index');
Route::get('mail-templates/{mailTemplate}/edit', [MailTemplateController::class, 'edit'])->name('mail-templates.edit');
Route::put('mail-templates/{mailTemplate}', [MailTemplateController::class, 'update'])->name('mail-templates.update');
Route::post('mail-templates/{mailTemplate}/reset', [MailTemplateController::class, 'reset'])->name('mail-templates.reset');
Route::post('mail-templates/{mailTemplate}/preview', [MailTemplateController::class, 'preview'])->name('mail-templates.preview');

// Mail Logs
Route::get('mail-logs', [MailLogController::class, 'index'])->name('mail-logs.index');
Route::get('mail-logs/{mailLog}', [MailLogController::class, 'show'])->name('mail-logs.show');
Route::get('mail-logs/{mailLog}/body', [MailLogController::class, 'body'])->name('mail-logs.body');
Route::post('mail-logs/{mailLog}/resend', [MailLogController::class, 'resend'])->name('mail-logs.resend');

// AI Logs
Route::get('ai-logs', [AiLogController::class, 'index'])->name('ai-logs.index');
Route::get('ai-logs/{aiLog}', [AiLogController::class, 'show'])->name('ai-logs.show');
Route::post('ai-logs/{aiLog}/retry', [AiLogController::class, 'retry'])->name('ai-logs.retry');

// AI Prompt Settings
Route::get('ai-prompt-settings/edit', [AiPromptSettingController::class, 'edit'])->name('ai-prompt-settings.edit');
Route::put('ai-prompt-settings', [AiPromptSettingController::class, 'update'])->name('ai-prompt-settings.update');
Route::post('ai-prompt-settings/reset', [AiPromptSettingController::class, 'reset'])->name('ai-prompt-settings.reset');
Route::post('ai-prompt-settings/reset-rotation', [AiPromptSettingController::class, 'resetRotation'])->name('ai-prompt-settings.reset-rotation');

// AI Generate (manuel üretim)
Route::get('ai-generate', [AiGenerateController::class, 'create'])->name('ai-generate.create');
Route::post('ai-generate', [AiGenerateController::class, 'store'])->name('ai-generate.store');

// AI Image Generator
Route::prefix('ai-images')->name('ai-images.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\AiImageController::class, 'index'])->name('index');
    Route::post('/generate', [\App\Http\Controllers\Admin\AiImageController::class, 'generate'])->name('generate');
    Route::post('/prompts', [\App\Http\Controllers\Admin\AiImageController::class, 'storePrompt'])->name('prompts.store');
    Route::put('/prompts/{prompt}', [\App\Http\Controllers\Admin\AiImageController::class, 'updatePrompt'])->name('prompts.update');
    Route::delete('/prompts/{prompt}', [\App\Http\Controllers\Admin\AiImageController::class, 'destroyPrompt'])->name('prompts.destroy');
    Route::delete('/images/{image}', [\App\Http\Controllers\Admin\AiImageController::class, 'destroyImage'])->name('images.destroy');
    // Detay modal için JSON — final_prompt, response_payload, template, ref görsel
    Route::get('/images/{image}/detail', [\App\Http\Controllers\Admin\AiImageController::class, 'showImage'])->name('images.show');
});

// AI Image Logs (read-only viewer over ai_generated_images)
Route::prefix('ai-image-logs')->name('ai-image-logs.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\AiImageLogController::class, 'index'])->name('index');
    Route::get('/{aiImageLog}', [\App\Http\Controllers\Admin\AiImageLogController::class, 'show'])->name('show');
    Route::delete('/{aiImageLog}', [\App\Http\Controllers\Admin\AiImageLogController::class, 'destroy'])->name('destroy');
});

// AI Cost Report — aylık maliyet + bütçe takibi
Route::get('ai-costs', [\App\Http\Controllers\Admin\AiCostController::class, 'index'])->name('ai-costs.index');

// ─── Google Vertex AI — Gemini Image Generation (Nano Banana) ───
// Diğer Gemini key'lerinden tamamen ayrı, GOOGLE_VERTEX_AI_KEY kullanır.
Route::prefix('vertex')->name('vertex.')->group(function () {
    // Ana üretim ekranı — her istek (1 veya N) batch olarak kuyruğa alınır, cron işler
    Route::get('/', [\App\Http\Controllers\Admin\VertexImageController::class, 'index'])->name('index');
    Route::post('/generate', [\App\Http\Controllers\Admin\VertexImageController::class, 'generate'])
        ->middleware('throttle:30,1')
        ->name('generate');
    Route::get('/status/{generation}', [\App\Http\Controllers\Admin\VertexImageController::class, 'status'])->name('status');
    Route::get('/gallery-api', [\App\Http\Controllers\Admin\VertexImageController::class, 'gallery'])->name('gallery-api');
    Route::get('/system-status', [\App\Http\Controllers\Admin\VertexImageController::class, 'systemStatus'])->name('system-status');

    // Batch durum takibi ve iptal
    Route::get('/batch/{batch}', [\App\Http\Controllers\Admin\VertexImageController::class, 'batchStatus'])->name('batch-status');
    Route::post('/batch/{batch}/cancel', [\App\Http\Controllers\Admin\VertexImageController::class, 'cancelBatch'])->name('batch-cancel');
    Route::post('/batch/{batch}/retry', [\App\Http\Controllers\Admin\VertexImageController::class, 'retryBatch'])->name('batch-retry');

    // Prompt şablonları (CRUD)
    Route::prefix('prompts')->name('prompts.')->group(function () {
        Route::get('/',                  [\App\Http\Controllers\Admin\VertexPromptController::class, 'index'])->name('index');
        Route::get('/create',            [\App\Http\Controllers\Admin\VertexPromptController::class, 'create'])->name('create');
        Route::post('/',                 [\App\Http\Controllers\Admin\VertexPromptController::class, 'store'])->name('store');
        Route::get('/{prompt}/edit',     [\App\Http\Controllers\Admin\VertexPromptController::class, 'edit'])->name('edit');
        Route::put('/{prompt}',          [\App\Http\Controllers\Admin\VertexPromptController::class, 'update'])->name('update');
        Route::delete('/{prompt}',       [\App\Http\Controllers\Admin\VertexPromptController::class, 'destroy'])->name('destroy');
        Route::post('/{prompt}/toggle-pin',           [\App\Http\Controllers\Admin\VertexPromptController::class, 'togglePin'])->name('toggle-pin');
        Route::get('/{prompt}/versions',              [\App\Http\Controllers\Admin\VertexPromptController::class, 'versions'])->name('versions');
        Route::get('/{prompt}/versions/{version}',    [\App\Http\Controllers\Admin\VertexPromptController::class, 'showVersion'])->name('versions.show');
        Route::post('/{prompt}/versions/{version}/restore', [\App\Http\Controllers\Admin\VertexPromptController::class, 'restoreVersion'])->name('versions.restore');
    });

    // Üretim geçmişi
    Route::prefix('generations')->name('generations.')->group(function () {
        Route::get('/',                       [\App\Http\Controllers\Admin\VertexGenerationController::class, 'index'])->name('index');
        Route::post('/bulk-download',         [\App\Http\Controllers\Admin\VertexGenerationController::class, 'bulkDownload'])->name('bulk-download');
        Route::get('/download-by-filter',    [\App\Http\Controllers\Admin\VertexGenerationController::class, 'downloadByFilter'])->name('download-by-filter');
        Route::post('/bulk-destroy',          [\App\Http\Controllers\Admin\VertexGenerationController::class, 'bulkDestroy'])->name('bulk-destroy');
        Route::get('/{generation}/download-jpg', [\App\Http\Controllers\Admin\VertexGenerationController::class, 'downloadJpg'])->name('download-jpg');
        Route::post('/{generation}/share',    [\App\Http\Controllers\Admin\VertexGenerationController::class, 'share'])->name('share');
        Route::post('/{generation}/retry',   [\App\Http\Controllers\Admin\VertexGenerationController::class, 'retry'])->name('retry');
        Route::get('/{generation}',           [\App\Http\Controllers\Admin\VertexGenerationController::class, 'show'])->name('show');
        Route::delete('/{generation}',        [\App\Http\Controllers\Admin\VertexGenerationController::class, 'destroy'])->name('destroy');
    });

    // Zamanlama (otomatik tekrarlayan üretim)
    Route::prefix('schedules')->name('schedules.')->group(function () {
        Route::get('/',                    [\App\Http\Controllers\Admin\VertexScheduleController::class, 'index'])->name('index');
        Route::post('/',                   [\App\Http\Controllers\Admin\VertexScheduleController::class, 'store'])->name('store');
        Route::put('/{schedule}',          [\App\Http\Controllers\Admin\VertexScheduleController::class, 'update'])->name('update');
        Route::post('/{schedule}/toggle',  [\App\Http\Controllers\Admin\VertexScheduleController::class, 'toggle'])->name('toggle');
        Route::post('/{schedule}/run-now', [\App\Http\Controllers\Admin\VertexScheduleController::class, 'runNow'])->name('run-now');
        Route::delete('/{schedule}',       [\App\Http\Controllers\Admin\VertexScheduleController::class, 'destroy'])->name('destroy');
    });

    // Özel Gün Görsel Üretimi
    Route::prefix('special-days')->name('special-days.')->group(function () {
        Route::get('/',         [\App\Http\Controllers\Admin\SpecialDayVertexController::class, 'index'])->name('index');
        Route::post('/generate', [\App\Http\Controllers\Admin\SpecialDayVertexController::class, 'generate'])
            ->middleware('throttle:10,1')
            ->name('generate');
        Route::post('/preview',  [\App\Http\Controllers\Admin\SpecialDayVertexController::class, 'preview'])->name('preview');
    });
});

// Media Library — kuratoryal görsel kütüphanesi (cron blog kapağı için)
Route::prefix('media-library')->name('media-library.')->group(function () {
    Route::get('/',                [\App\Http\Controllers\Admin\MediaLibraryController::class, 'index'])->name('index');
    Route::post('/upload',         [\App\Http\Controllers\Admin\MediaLibraryController::class, 'upload'])->name('upload');
    Route::patch('/{asset}',       [\App\Http\Controllers\Admin\MediaLibraryController::class, 'update'])
        ->whereNumber('asset')
        ->name('update');
    Route::delete('/{asset}',      [\App\Http\Controllers\Admin\MediaLibraryController::class, 'destroy'])
        ->whereNumber('asset')
        ->name('destroy');
    // {productSlug} en sonda — diğer route'larla çakışmasın
    Route::get('/{productSlug}',   [\App\Http\Controllers\Admin\MediaLibraryController::class, 'show'])
        ->where('productSlug', '[a-z0-9\-]+')
        ->name('show');
});

// File Manager — genel dosya yöneticisi (admin elle PDF/Word/Excel/görsel yükler)
Route::prefix('files')->name('files.')->group(function () {
    Route::get('/',           [\App\Http\Controllers\Admin\FileManagerController::class, 'index'])->name('index');
    Route::post('/upload',    [\App\Http\Controllers\Admin\FileManagerController::class, 'upload'])->name('upload');
    Route::get('/{file}',     [\App\Http\Controllers\Admin\FileManagerController::class, 'show'])->whereNumber('file')->name('show');
    Route::patch('/{file}',   [\App\Http\Controllers\Admin\FileManagerController::class, 'update'])->whereNumber('file')->name('update');
    Route::delete('/{file}',  [\App\Http\Controllers\Admin\FileManagerController::class, 'destroy'])->whereNumber('file')->name('destroy');
});

// Profile
Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

// Uploads (CKEditor)
Route::post('upload/ckeditor', [UploadController::class, 'ckeditor'])->name('upload.ckeditor');

// SEO Performance (Google Search Console)
Route::prefix('seo-performance')->name('seo-performance.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\SeoPerformanceController::class, 'index'])->name('index');
    Route::post('/fetch-now', [\App\Http\Controllers\Admin\SeoPerformanceController::class, 'fetchNow'])->name('fetch-now');
    Route::post('/test-connection', [\App\Http\Controllers\Admin\SeoPerformanceController::class, 'testConnection'])->name('test-connection');
    Route::post('/reindex/{indexingRequest}', [\App\Http\Controllers\Admin\SeoPerformanceController::class, 'reindex'])->name('reindex');
});

// Google Integration (Service Account JSON + OAuth 2.0 user flow)
Route::prefix('google-integration')->name('google-integration.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\GoogleIntegrationController::class, 'index'])->name('index');
    Route::post('/upload', [\App\Http\Controllers\Admin\GoogleIntegrationController::class, 'upload'])->name('upload');
    Route::delete('/', [\App\Http\Controllers\Admin\GoogleIntegrationController::class, 'delete'])->name('delete');
    Route::put('/settings', [\App\Http\Controllers\Admin\GoogleIntegrationController::class, 'updateSettings'])->name('update-settings');
    Route::post('/test-connection', [\App\Http\Controllers\Admin\GoogleIntegrationController::class, 'testConnection'])->name('test-connection');

    // OAuth 2.0 client credentials (admin saves Client ID + Secret here)
    Route::put('/oauth-credentials', [\App\Http\Controllers\Admin\GoogleIntegrationController::class, 'saveOAuthCredentials'])->name('oauth-credentials.update');

    // OAuth 2.0 user flow (preferred; service-account is fallback)
    Route::prefix('oauth')->name('oauth.')->group(function () {
        Route::get('/start',           [\App\Http\Controllers\Admin\GoogleOAuthController::class, 'start'])->name('start');
        Route::get('/callback',        [\App\Http\Controllers\Admin\GoogleOAuthController::class, 'callback'])->name('callback');
        Route::post('/disconnect',     [\App\Http\Controllers\Admin\GoogleOAuthController::class, 'disconnect'])->name('disconnect');
    });
});

// Google Merchant Center XML feed yönetimi
Route::prefix('google-merchant-feed')->name('google-merchant-feed.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\GoogleMerchantFeedController::class, 'index'])->name('index');
    Route::post('/rebuild', [\App\Http\Controllers\Admin\GoogleMerchantFeedController::class, 'rebuild'])->name('rebuild');
});
