<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BlogCategoryController;
use App\Http\Controllers\Admin\BlogCommentController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\GalleryCategoryController;
use App\Http\Controllers\Admin\GalleryItemController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\MailLogController;
use App\Http\Controllers\Admin\MailTemplateController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PopupController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RedirectController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\TranslationController;
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

// Pages
Route::resource('pages', PageController::class)->except('show');
Route::patch('pages/{page}/restore', [PageController::class, 'restore'])->name('pages.restore')->withTrashed();

// Sliders
Route::resource('sliders', SliderController::class)->except('show');
Route::patch('sliders/{slider}/restore', [SliderController::class, 'restore'])->name('sliders.restore')->withTrashed();

// Popups / Modals (belirli sayfa + tarih aralığı)
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

// System Health
Route::get('sistem-saglik',       [HealthController::class, 'index'])->name('system-health.index');
Route::get('sistem-saglik/json',  [HealthController::class, 'json'])->name('system-health.json');

// Audit Logs (Audit Trail)
Route::get('aktivite-loglari',            [AuditLogController::class, 'index'])->name('audit-logs.index');
Route::get('aktivite-loglari/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

// Notifications (Admin Notification Center)
Route::get('bildirimler',                        [NotificationController::class, 'index'])->name('notifications.index');
Route::get('bildirimler/recent',                 [NotificationController::class, 'recent'])->name('notifications.recent');
Route::post('bildirimler/{notification}/okundu',   [NotificationController::class, 'markRead'])->name('notifications.mark-read');
Route::post('bildirimler/{notification}/okunmadi', [NotificationController::class, 'markUnread'])->name('notifications.mark-unread');
Route::post('bildirimler/tumunu-okundu',          [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
// Toplu işlemler tekil silme kuralından ÖNCE: "toplu-sil" de bir {notification}
// parametresi gibi görünür ve sonra tanımlanırsa oraya takılır.
Route::post('bildirimler/toplu-okundu',           [NotificationController::class, 'bulkMarkRead'])->name('notifications.bulk-mark-read');
Route::delete('bildirimler/toplu-sil',            [NotificationController::class, 'bulkDestroy'])->name('notifications.bulk-destroy');
Route::delete('bildirimler/tumunu-sil',           [NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');
Route::delete('bildirimler/{notification}',       [NotificationController::class, 'destroy'])->name('notifications.destroy');

// Backups
Route::get('yedekler',                  [BackupController::class, 'index'])->name('backups.index');
Route::post('yedekler/olustur',         [BackupController::class, 'create'])->name('backups.create');
Route::get('yedekler/indir/{filename}', [BackupController::class, 'download'])->where('filename', '[A-Za-z0-9\-_.]+')->name('backups.download');
// Toplu silme, tekil silme kuralından önce tanımlı: "toplu-sil" de bir dosya
// adı gibi görünüyor ve sonra tanımlanırsa {filename} kuralına takılır.
Route::delete('yedekler/toplu-sil',     [BackupController::class, 'bulkDestroy'])->name('backups.bulk-destroy');
Route::delete('yedekler/{filename}',    [BackupController::class, 'destroy'])->where('filename', '[A-Za-z0-9\-_.]+')->name('backups.destroy');

// Contact Messages
Route::get('contact-messages', [ContactMessageController::class, 'index'])->name('contact-messages.index');
Route::post('contact-messages/mark-all-read', [ContactMessageController::class, 'markAllRead'])->name('contact-messages.mark-all-read');
Route::get('contact-messages/{contactMessage}', [ContactMessageController::class, 'show'])->name('contact-messages.show')->withTrashed();
Route::post('contact-messages/{contactMessage}/reply', [ContactMessageController::class, 'reply'])->name('contact-messages.reply');
Route::delete('contact-messages/{contactMessage}', [ContactMessageController::class, 'destroy'])->name('contact-messages.destroy');
Route::patch('contact-messages/{contactMessage}/restore', [ContactMessageController::class, 'restore'])->name('contact-messages.restore')->withTrashed();

// Roles & Permissions
Route::get('roller',                 [RoleController::class, 'index'])->name('roles.index');
Route::post('roller',                [RoleController::class, 'store'])->name('roles.store');
Route::put('roller/izinler',         [RoleController::class, 'syncPermissions'])->name('roles.permissions.sync');
Route::put('roller/{role}',          [RoleController::class, 'update'])->name('roles.update');
Route::delete('roller/{role}',       [RoleController::class, 'destroy'])->name('roles.destroy');

// Users
Route::resource('users', UserController::class)->except('show');
Route::patch('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore')->withTrashed();

// Redirects
Route::resource('redirects', RedirectController::class)->except('show');
Route::patch('redirects/{redirect}/restore', [RedirectController::class, 'restore'])->name('redirects.restore')->withTrashed();
Route::patch('redirects/{redirect}/toggle-active', [RedirectController::class, 'toggleActive'])->name('redirects.toggle-active');

// Analytics (Visitor Statistics)
Route::prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/', [AnalyticsController::class, 'index'])->name('index');
    Route::get('/visits', [AnalyticsController::class, 'visits'])->name('visits');
    Route::get('/canli', [AnalyticsController::class, 'live'])->name('live');
    Route::get('/canli/veri', [AnalyticsController::class, 'liveData'])->name('live.data');
    Route::get('/chart/{type}', [AnalyticsController::class, 'chart'])->name('chart');
});

// Menus
Route::prefix('menus')->name('menus.')->group(function () {
    Route::get('/', [MenuController::class, 'index'])->name('index');
    Route::put('{menu}', [MenuController::class, 'update'])->name('update');
    Route::post('{menu}/copy/{locale}', [MenuController::class, 'copy'])->name('copy');
    Route::get('{menu}/items', [MenuItemController::class, 'index'])->name('items.index');
    Route::post('{menu}/items', [MenuItemController::class, 'store'])->name('items.store');
    Route::patch('{menu}/items/reorder', [MenuItemController::class, 'reorder'])->name('items.reorder');
    Route::put('items/{item}', [MenuItemController::class, 'update'])->name('items.update');
    Route::delete('items/{item}', [MenuItemController::class, 'destroy'])->name('items.destroy');
    Route::patch('items/{item}/restore', [MenuItemController::class, 'restore'])->name('items.restore')->withTrashed();
});

// Dil yazıları (arayüz metinleri)
Route::prefix('dil-yazilari')->name('translations.')->group(function () {
    Route::get('/',          [TranslationController::class, 'index'])->name('index');
    Route::put('/',          [TranslationController::class, 'update'])->name('update');
    Route::post('sifirla',   [TranslationController::class, 'reset'])->name('reset');
});

// Diller
Route::prefix('diller')->name('languages.')->group(function () {
    Route::get('/',                        [LanguageController::class, 'index'])->name('index');
    Route::get('yeni',                     [LanguageController::class, 'create'])->name('create');
    Route::post('/',                       [LanguageController::class, 'store'])->name('store');
    Route::get('{language}/duzenle',       [LanguageController::class, 'edit'])->name('edit');
    Route::put('{language}',               [LanguageController::class, 'update'])->name('update');
    Route::post('{language}/varsayilan',   [LanguageController::class, 'makeDefault'])->name('default');
    Route::delete('{language}',            [LanguageController::class, 'destroy'])->name('destroy');
});

// Mail kampanyaları (toplu gönderim)
Route::prefix('kampanyalar')->name('campaigns.')->group(function () {
    Route::get('/',                        [CampaignController::class, 'index'])->name('index');
    Route::get('yeni',                     [CampaignController::class, 'create'])->name('create');
    Route::get('sablon-indir',             [CampaignController::class, 'template'])->name('template');
    Route::post('/',                       [CampaignController::class, 'store'])->name('store');
    Route::get('{campaign}',               [CampaignController::class, 'show'])->name('show');
    Route::get('{campaign}/duzenle',       [CampaignController::class, 'edit'])->name('edit');
    Route::put('{campaign}',               [CampaignController::class, 'update'])->name('update');
    Route::post('{campaign}/gonder',       [CampaignController::class, 'send'])->name('send');
    Route::post('{campaign}/duraklat',     [CampaignController::class, 'pause'])->name('pause');
    Route::post('{campaign}/surdur',       [CampaignController::class, 'resume'])->name('resume');
    Route::post('{campaign}/iptal',        [CampaignController::class, 'cancel'])->name('cancel');
    Route::post('{campaign}/test',         [CampaignController::class, 'sendTest'])->name('test');
    Route::delete('{campaign}/ek/{attachment}', [CampaignController::class, 'destroyAttachment'])->name('attachments.destroy');
    Route::delete('{campaign}',            [CampaignController::class, 'destroy'])->name('destroy');
    Route::patch('{campaign}/geri-yukle',  [CampaignController::class, 'restore'])->name('restore')->withTrashed();
});

// Mail listesi (aboneler)
Route::prefix('aboneler')->name('subscribers.')->group(function () {
    Route::get('/',                       [SubscriberController::class, 'index'])->name('index');
    Route::post('/',                      [SubscriberController::class, 'store'])->name('store');
    Route::post('ice-aktar',              [SubscriberController::class, 'import'])->name('import');
    Route::post('{subscriber}/cikar',     [SubscriberController::class, 'unsubscribe'])->name('unsubscribe');
    Route::delete('{subscriber}',         [SubscriberController::class, 'destroy'])->name('destroy');
    Route::patch('{subscriber}/geri-yukle', [SubscriberController::class, 'restore'])->name('restore')->withTrashed();
});

// Settings
Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
Route::post('settings/test-email', [SettingController::class, 'testEmail'])->name('settings.test-email');
Route::post('settings/clear-cache', [SettingController::class, 'clearCache'])->name('settings.clear-cache');
Route::post('settings/test-telegram', [SettingController::class, 'testTelegram'])->name('settings.test-telegram');

// Blog Categories
Route::resource('blog-categories', BlogCategoryController::class)->except('show');
Route::patch('blog-categories/{blogCategory}/restore', [BlogCategoryController::class, 'restore'])->name('blog-categories.restore')->withTrashed();

// Blog Posts
Route::resource('blog-posts', BlogPostController::class);
Route::patch('blog-posts/{blogPost}/restore', [BlogPostController::class, 'restore'])->name('blog-posts.restore')->withTrashed();

// Blog Comments
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

// File Manager (general-purpose uploads: PDF/Word/Excel/image)
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
