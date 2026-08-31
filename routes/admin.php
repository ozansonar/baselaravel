<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CustomRouteController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BlogCategoryController;
use App\Http\Controllers\Admin\BlogCommentController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\ContentFileController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\FileBrowserController;
use App\Http\Controllers\Admin\GalleryBulkUploadController;
use App\Http\Controllers\Admin\GalleryCategoryController;
use App\Http\Controllers\Admin\GalleryItemController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\ContentListController;
use App\Http\Controllers\Admin\HelpController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\MailLogController;
use App\Http\Controllers\Admin\QueueController;
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
use App\Http\Controllers\Admin\SubscriberListController;
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

// Liste dışa aktarma (Excel / PDF)
// Bütün listeleme ekranları tek uçtan geçiyor: hangi liste olduğu anahtardan,
// hangi kayıtların gireceği ekrandaki süzgeçlerden geliyor. Hız sınırı, ağır
// dosya üreten bu ucun art arda tetiklenmesini engelliyor.
Route::get('disa-aktar/{key}/{format}', ExportController::class)
    ->middleware('throttle:30,1')
    ->name('export');

// Pages
// Toplu işlemler kaynak rotalarından ÖNCE: "toplu-sil" de bir {page} kalıbına
// uyuyor, sıra tersine dönerse istek tek kayıt silmeye giderdi.
Route::delete('pages/toplu-sil', [PageController::class, 'bulkDestroy'])->name('pages.bulk-destroy');
Route::patch('pages/toplu-geri-yukle', [PageController::class, 'bulkRestore'])->name('pages.bulk-restore');
Route::resource('pages', PageController::class)->except('show');
Route::patch('pages/{page}/restore', [PageController::class, 'restore'])->name('pages.restore')->withTrashed();

// Sliders
// Toplu işlemler kaynak rotalarından ÖNCE: "toplu-sil" de bir {slider} kalıbına
// uyuyor, sıra tersine dönerse istek tek kayıt silmeye giderdi.
Route::delete('sliders/toplu-sil', [SliderController::class, 'bulkDestroy'])->name('sliders.bulk-destroy');
Route::patch('sliders/toplu-geri-yukle', [SliderController::class, 'bulkRestore'])->name('sliders.bulk-restore');
Route::resource('sliders', SliderController::class)->except('show');
Route::patch('sliders/{slider}/restore', [SliderController::class, 'restore'])->name('sliders.restore')->withTrashed();

// Popups / Modals (belirli sayfa + tarih aralığı)
Route::resource('popups', PopupController::class)->except('show');
Route::patch('popups/{popup}/restore', [PopupController::class, 'restore'])->name('popups.restore')->withTrashed();

// Gallery Categories
Route::resource('gallery-categories', GalleryCategoryController::class)->except('show');
Route::patch('gallery-categories/{galleryCategory}/restore', [GalleryCategoryController::class, 'restore'])->name('gallery-categories.restore')->withTrashed();

// Gallery Items
// Toplu yükleme kaynak rotalarından önce: gallery-items/{galleryItem} kalıbı
// "toplu-yukleme"yi de yakalar, sıra tersine dönerse ekran öğe düzenlemeye gider.
Route::get('gallery-items/toplu-yukleme', [GalleryBulkUploadController::class, 'create'])->name('gallery-items.bulk.create');
Route::post('gallery-items/toplu-yukleme', [GalleryBulkUploadController::class, 'store'])->name('gallery-items.bulk.store');
Route::put('gallery-items/toplu-yukleme', [GalleryBulkUploadController::class, 'update'])->name('gallery-items.bulk.update');
Route::delete('gallery-items/toplu-yukleme/{galleryItem}', [GalleryBulkUploadController::class, 'destroy'])->name('gallery-items.bulk.destroy');
// Toplu işlemler kaynak rotalarından ÖNCE: "toplu-sil" de bir {galleryItem}
// kalıbına uyuyor, sıra tersine dönerse istek öğe silmeye giderdi.
Route::delete('gallery-items/toplu-sil', [GalleryItemController::class, 'bulkDestroy'])->name('gallery-items.bulk-destroy');
Route::patch('gallery-items/toplu-geri-yukle', [GalleryItemController::class, 'bulkRestore'])->name('gallery-items.bulk-restore');
Route::resource('gallery-items', GalleryItemController::class)->except('show');
Route::patch('gallery-items/{galleryItem}/restore', [GalleryItemController::class, 'restore'])->name('gallery-items.restore')->withTrashed();

// FAQs
// Toplu işlemler kaynak rotalarından ÖNCE: "toplu-sil" de bir {faq} kalıbına
// uyuyor, sıra tersine dönerse istek tek kayıt silmeye giderdi.
Route::delete('faqs/toplu-sil', [FaqController::class, 'bulkDestroy'])->name('faqs.bulk-destroy');
Route::patch('faqs/toplu-geri-yukle', [FaqController::class, 'bulkRestore'])->name('faqs.bulk-restore');
Route::resource('faqs', FaqController::class)->except('show');
Route::patch('faqs/{faq}/restore', [FaqController::class, 'restore'])->name('faqs.restore')->withTrashed();

// System Health
/*
|--------------------------------------------------------------------------
| Yardım merkezi
|--------------------------------------------------------------------------
| Yetki istemiyor: panele girebilen herkes panelin nasıl çalıştığını
| okuyabilmeli.
*/
Route::get('yardim', [HelpController::class, 'index'])->name('help.index');

/*
|--------------------------------------------------------------------------
| Genel içerik listesi
|--------------------------------------------------------------------------
| Blog, sayfa, galeri ve SSS tek listede. Düzenleme buradan yapılmıyor; her
| kayıt kendi ekranına bağlanıyor.
*/
Route::get('icerikler', [ContentListController::class, 'index'])->name('content-list.index');

/*
|--------------------------------------------------------------------------
| Rapor merkezi
|--------------------------------------------------------------------------
| Raporun kendisi ekranda, indirmesi genel dışa aktarma yolunda
| (/admin/disa-aktar/reports/{format}) — rapor da bir liste ve ikinci bir indirme
| kodu yazmanın anlamı yok.
*/
Route::get('raporlar', [ReportController::class, 'index'])->name('reports.index');
Route::get('raporlar/onizleme/{type}', [ReportController::class, 'preview'])->name('reports.preview');
Route::post('raporlar/zamanlama', [ReportController::class, 'storeSchedule'])->name('reports.schedules.store');
Route::put('raporlar/zamanlama/{schedule}', [ReportController::class, 'updateSchedule'])->name('reports.schedules.update');
Route::delete('raporlar/zamanlama/{schedule}', [ReportController::class, 'destroySchedule'])->name('reports.schedules.destroy');
Route::post('raporlar/zamanlama/{schedule}/calistir', [ReportController::class, 'runSchedule'])->name('reports.schedules.run');

Route::get('sistem-saglik', [HealthController::class, 'index'])->name('system-health.index');
Route::get('sistem-saglik/json', [HealthController::class, 'json'])->name('system-health.json');

// Audit Logs (Audit Trail)
Route::get('aktivite-loglari', [AuditLogController::class, 'index'])->name('audit-logs.index');
Route::get('aktivite-loglari/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

// Notifications (Admin Notification Center)
Route::get('bildirimler', [NotificationController::class, 'index'])->name('notifications.index');
Route::get('bildirimler/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
Route::post('bildirimler/{notification}/okundu', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
Route::post('bildirimler/{notification}/okunmadi', [NotificationController::class, 'markUnread'])->name('notifications.mark-unread');
Route::post('bildirimler/tumunu-okundu', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
// Toplu işlemler tekil silme kuralından ÖNCE: "toplu-sil" de bir {notification}
// parametresi gibi görünür ve sonra tanımlanırsa oraya takılır.
Route::post('bildirimler/toplu-okundu', [NotificationController::class, 'bulkMarkRead'])->name('notifications.bulk-mark-read');
Route::delete('bildirimler/toplu-sil', [NotificationController::class, 'bulkDestroy'])->name('notifications.bulk-destroy');
Route::delete('bildirimler/tumunu-sil', [NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');
Route::delete('bildirimler/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

// Backups
Route::get('yedekler', [BackupController::class, 'index'])->name('backups.index');
Route::post('yedekler/olustur', [BackupController::class, 'create'])->name('backups.create');
Route::get('yedekler/indir/{filename}', [BackupController::class, 'download'])->where('filename', '[A-Za-z0-9\-_.]+')->name('backups.download');
// Toplu silme, tekil silme kuralından önce tanımlı: "toplu-sil" de bir dosya
// adı gibi görünüyor ve sonra tanımlanırsa {filename} kuralına takılır.
Route::post('yedekler/yukle', [BackupController::class, 'upload'])->name('backups.upload');
Route::get('yedekler/incele/{filename}', [BackupController::class, 'inspect'])->where('filename', '[A-Za-z0-9\-_.]+')->name('backups.inspect');
Route::post('yedekler/geri-yukle/{filename}', [BackupController::class, 'restore'])->where('filename', '[A-Za-z0-9\-_.]+')->name('backups.restore');
Route::delete('yedekler/toplu-sil', [BackupController::class, 'bulkDestroy'])->name('backups.bulk-destroy');
Route::delete('yedekler/{filename}', [BackupController::class, 'destroy'])->where('filename', '[A-Za-z0-9\-_.]+')->name('backups.destroy');

// Editörün dosya seçicisi: public/uploads dizinini gezip dosya seçmek, yüklemek
// ve silmek için. Dosya yöneticisi tabloyu listeliyor, bu uçlar diski.
Route::prefix('dosya-secici')->name('file-browser.')->group(function () {
    Route::get('/', [FileBrowserController::class, 'index'])->name('index');
    Route::post('/', [FileBrowserController::class, 'store'])->name('store');
    Route::delete('/', [FileBrowserController::class, 'destroy'])->name('destroy');
});

// Contact Messages
Route::get('contact-messages', [ContactMessageController::class, 'index'])->name('contact-messages.index');
Route::post('contact-messages/mark-all-read', [ContactMessageController::class, 'markAllRead'])->name('contact-messages.mark-all-read');
Route::get('contact-messages/{contactMessage}', [ContactMessageController::class, 'show'])->name('contact-messages.show')->withTrashed();
Route::post('contact-messages/{contactMessage}/reply', [ContactMessageController::class, 'reply'])->name('contact-messages.reply');
Route::delete('contact-messages/{contactMessage}', [ContactMessageController::class, 'destroy'])->name('contact-messages.destroy');
Route::patch('contact-messages/{contactMessage}/restore', [ContactMessageController::class, 'restore'])->name('contact-messages.restore')->withTrashed();

// Roles & Permissions
Route::get('roller', [RoleController::class, 'index'])->name('roles.index');
Route::post('roller', [RoleController::class, 'store'])->name('roles.store');
Route::put('roller/izinler', [RoleController::class, 'syncPermissions'])->name('roles.permissions.sync');
Route::put('roller/{role}', [RoleController::class, 'update'])->name('roles.update');
Route::delete('roller/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

// Users
// Toplu işlemler kaynak rotalarından ÖNCE: "toplu-sil" de bir {user} kalıbına
// uyuyor, sıra tersine dönerse istek tek kayıt silmeye giderdi.
Route::delete('users/toplu-sil', [UserController::class, 'bulkDestroy'])->name('users.bulk-destroy');
Route::patch('users/toplu-geri-yukle', [UserController::class, 'bulkRestore'])->name('users.bulk-restore');
Route::resource('users', UserController::class)->except('show');
Route::patch('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore')->withTrashed();

// Redirects
// Ekleme ve düzenleme listedeki modalda yapılıyor; ayrı create/edit sayfası yok.
// Bunlar resource'tan çıkarılmazsa route kayıtlı görünür ama controller'da
// karşılığı olmadığı için adres 404 yerine 500 döner.
// Ekleme ve düzenleme kendi sayfasında; pencere yerine tam form.
Route::resource('redirects', RedirectController::class)->except(['show']);
Route::patch('redirects/{redirect}/restore', [RedirectController::class, 'restore'])->name('redirects.restore')->withTrashed();

/*
|--------------------------------------------------------------------------
| Özel Adresler (URL Yönlendirme Yöneticisi)
|--------------------------------------------------------------------------
| Panelden açılan adresler: bir slug, var olan bir rotaya bağlanıyor.
*/
Route::prefix('custom-routes')->name('custom-routes.')->group(function () {
    Route::delete('toplu-sil', [CustomRouteController::class, 'bulkDestroy'])->name('bulk-destroy');
    Route::patch('toplu-geri-yukle', [CustomRouteController::class, 'bulkRestore'])->name('bulk-restore');
    Route::patch('{id}/restore', [CustomRouteController::class, 'restore'])->name('restore');
});

Route::resource('custom-routes', CustomRouteController::class)->except('show')->parameters([
    'custom-routes' => 'custom_route',
]);
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
    Route::get('/', [TranslationController::class, 'index'])->name('index');
    Route::put('/', [TranslationController::class, 'update'])->name('update');
    Route::post('sifirla', [TranslationController::class, 'reset'])->name('reset');
});

// Diller
Route::prefix('diller')->name('languages.')->group(function () {
    Route::get('/', [LanguageController::class, 'index'])->name('index');
    Route::get('yeni', [LanguageController::class, 'create'])->name('create');
    Route::post('/', [LanguageController::class, 'store'])->name('store');
    Route::get('{language}/duzenle', [LanguageController::class, 'edit'])->name('edit');
    Route::put('{language}', [LanguageController::class, 'update'])->name('update');
    Route::post('{language}/varsayilan', [LanguageController::class, 'makeDefault'])->name('default');
    Route::delete('{language}', [LanguageController::class, 'destroy'])->name('destroy');
});

// Mail kampanyaları (toplu gönderim)
Route::prefix('kampanyalar')->name('campaigns.')->group(function () {
    Route::get('/', [CampaignController::class, 'index'])->name('index');
    Route::get('yeni', [CampaignController::class, 'create'])->name('create');
    Route::get('sablon-indir', [CampaignController::class, 'template'])->name('template');
    Route::post('alici-onizleme', [CampaignController::class, 'previewRecipients'])->name('recipients.preview');
    // Ekler kampanya formuna binmiyor: her dosya kendi isteğiyle önden yüklenir,
    // yoksa on dosya post_max_size'ı aşar ve form 419 ile komple kaybolur.
    Route::post('ek-yukle', [CampaignController::class, 'uploadAttachment'])->name('attachments.upload');
    Route::delete('ek-yukle/{token}', [CampaignController::class, 'destroyPendingAttachment'])->name('attachments.discard');
    Route::post('/', [CampaignController::class, 'store'])->name('store');
    Route::get('{campaign}', [CampaignController::class, 'show'])->name('show');
    Route::get('{campaign}/duzenle', [CampaignController::class, 'edit'])->name('edit');
    Route::put('{campaign}', [CampaignController::class, 'update'])->name('update');
    Route::post('{campaign}/gonder', [CampaignController::class, 'send'])->name('send');
    Route::post('{campaign}/duraklat', [CampaignController::class, 'pause'])->name('pause');
    Route::post('{campaign}/surdur', [CampaignController::class, 'resume'])->name('resume');
    Route::post('{campaign}/iptal', [CampaignController::class, 'cancel'])->name('cancel');
    Route::post('{campaign}/test', [CampaignController::class, 'sendTest'])->name('test');
    Route::delete('{campaign}/ek/{attachment}', [CampaignController::class, 'destroyAttachment'])->name('attachments.destroy');
    // Alıcı listesi gönderimden önce de kurulabiliyor: yönetici listeyi görüp
    // ayıklamadan onaylamak zorunda kalmamalı.
    Route::post('{campaign}/alicilar/hazirla', [CampaignController::class, 'prepareRecipients'])->name('recipients.prepare');
    // Alıcıyı gönderim dışında bırakma / sıraya geri alma. Kayıt silinmiyor,
    // durumu değişiyor: kimin neden gitmediği sonradan da görülebilmeli.
    Route::post('{campaign}/alici/{recipient}/cikar', [CampaignController::class, 'excludeRecipient'])->name('recipients.exclude');
    Route::post('{campaign}/alici/{recipient}/geri-al', [CampaignController::class, 'restoreRecipient'])->name('recipients.restore');
    Route::post('{campaign}/alicilar/toplu', [CampaignController::class, 'bulkRecipients'])->name('recipients.bulk');
    Route::post('{campaign}/alicilar/yeniden-dene', [CampaignController::class, 'retryFailed'])->name('recipients.retry');
    Route::get('{campaign}/alicilar/disa-aktar', [CampaignController::class, 'exportRecipients'])->name('recipients.export');
    Route::delete('{campaign}', [CampaignController::class, 'destroy'])->name('destroy');
    Route::patch('{campaign}/geri-yukle', [CampaignController::class, 'restore'])->name('restore')->withTrashed();
});

// Mail listesi (aboneler)
Route::prefix('aboneler')->name('subscribers.')->group(function () {
    Route::get('/', [SubscriberController::class, 'index'])->name('index');
    Route::post('/', [SubscriberController::class, 'store'])->name('store');
    // Dosya önce burada okunup ekrana dökülüyor, kaydetme ayrı adım: kullanıcı
    // hatalı satırı görüp düzeltmeden içeri almak zorunda kalmasın.
    Route::post('ice-aktar-onizleme', [SubscriberController::class, 'importPreview'])->name('import.preview');
    Route::post('ice-aktar', [SubscriberController::class, 'import'])->name('import');
    Route::post('toplu-liste', [SubscriberController::class, 'bulkList'])->name('bulk-list');
    Route::put('{subscriber}', [SubscriberController::class, 'update'])->name('update');
    Route::post('{subscriber}/cikar', [SubscriberController::class, 'unsubscribe'])->name('unsubscribe');
    Route::post('{subscriber}/geri-al', [SubscriberController::class, 'resubscribe'])->name('resubscribe');
    Route::delete('{subscriber}', [SubscriberController::class, 'destroy'])->name('destroy');
    Route::patch('{subscriber}/geri-yukle', [SubscriberController::class, 'restore'])->name('restore')->withTrashed();
});

// Abone listeleri (tedarikçiler, pazarlamacılar, bülten…)
Route::prefix('abone-listeleri')->name('subscriber-lists.')->group(function () {
    Route::post('/', [SubscriberListController::class, 'store'])->name('store');
    Route::put('{subscriberList}', [SubscriberListController::class, 'update'])->name('update');
    Route::delete('{subscriberList}', [SubscriberListController::class, 'destroy'])->name('destroy');
});

// Settings
Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
Route::post('settings/test-email', [SettingController::class, 'testEmail'])->name('settings.test-email');
Route::post('settings/clear-cache', [SettingController::class, 'clearCache'])->name('settings.clear-cache');
Route::post('settings/test-telegram', [SettingController::class, 'testTelegram'])->name('settings.test-telegram');

// Blog Categories
// Toplu işlemler kaynak rotalarından ÖNCE: "toplu-sil" de bir {blogCategory} kalıbına
// uyuyor, sıra tersine dönerse istek tek kayıt silmeye giderdi.
Route::delete('blog-categories/toplu-sil', [BlogCategoryController::class, 'bulkDestroy'])->name('blog-categories.bulk-destroy');
Route::patch('blog-categories/toplu-geri-yukle', [BlogCategoryController::class, 'bulkRestore'])->name('blog-categories.bulk-restore');
Route::resource('blog-categories', BlogCategoryController::class)->except('show');
Route::patch('blog-categories/{blogCategory}/restore', [BlogCategoryController::class, 'restore'])->name('blog-categories.restore')->withTrashed();

// İçerik ekleri — blog yazısı da sayfa da aynı uçları kullanıyor. Hedef içerik
// istekteki kısa tür anahtarından çözülüyor (App\Enums\AttachableContent).
Route::post('icerik-dosyasi', [ContentFileController::class, 'store'])->name('content-files.upload');
Route::delete('icerik-dosyasi/bekleyen/{token}', [ContentFileController::class, 'destroyPending'])->name('content-files.discard');
Route::delete('icerik-dosyasi/{file}', [ContentFileController::class, 'destroy'])->name('content-files.destroy');

// Blog Posts
// Toplu işlemler kaynak rotalarından ÖNCE: "toplu-sil" de bir {blogPost} kalıbına
// uyuyor, sıra tersine dönerse istek tek kayıt silmeye giderdi.
Route::delete('blog-posts/toplu-sil', [BlogPostController::class, 'bulkDestroy'])->name('blog-posts.bulk-destroy');
Route::patch('blog-posts/toplu-geri-yukle', [BlogPostController::class, 'bulkRestore'])->name('blog-posts.bulk-restore');
Route::patch('blog-posts/toplu-durum/{status}', [BlogPostController::class, 'bulkStatus'])
    ->whereIn('status', ['publish', 'draft'])->name('blog-posts.bulk-status');
Route::resource('blog-posts', BlogPostController::class);
Route::patch('blog-posts/{blogPost}/restore', [BlogPostController::class, 'restore'])->name('blog-posts.restore')->withTrashed();

// Blog Comments
Route::get('blog-comments', [BlogCommentController::class, 'index'])->name('blog-comments.index');
// Toplu işlemler kaynak rotalarından ÖNCE: "toplu-sil" de bir {blogComment} kalıbına
// uyuyor, sıra tersine dönerse istek tek kayıt silmeye giderdi.
Route::patch('blog-comments/toplu-onayla', [BlogCommentController::class, 'bulkApprove'])->name('blog-comments.bulk-approve');
Route::delete('blog-comments/toplu-sil', [BlogCommentController::class, 'bulkDestroy'])->name('blog-comments.bulk-destroy');
Route::patch('blog-comments/toplu-geri-yukle', [BlogCommentController::class, 'bulkRestore'])->name('blog-comments.bulk-restore');
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
Route::post('mail-logs/{mailLog}/send-now', [MailLogController::class, 'sendNow'])->name('mail-logs.send-now');

// Kuyruk izleyici — bekleyen ve başarısız işler.
// {uuid} kalıbı daraltıldı: failed_jobs.uuid bir UUID, serbest metin değil.
Route::prefix('kuyruk')->name('queue.')->group(function () {
    Route::get('/', [QueueController::class, 'index'])->name('index');
    Route::post('calistir', [QueueController::class, 'run'])->name('run');
    Route::delete('temizle', [QueueController::class, 'flush'])->name('flush');
    Route::get('{uuid}', [QueueController::class, 'show'])->whereUuid('uuid')->name('show');
    Route::post('{uuid}/yeniden-dene', [QueueController::class, 'retry'])->whereUuid('uuid')->name('retry');
    Route::delete('{uuid}', [QueueController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
});

// File Manager (general-purpose uploads: PDF/Word/Excel/image)
Route::prefix('files')->name('files.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\FileManagerController::class, 'index'])->name('index');
    Route::post('/upload', [\App\Http\Controllers\Admin\FileManagerController::class, 'upload'])->name('upload');
    Route::get('/{file}', [\App\Http\Controllers\Admin\FileManagerController::class, 'show'])->whereNumber('file')->name('show');
    Route::patch('/{file}', [\App\Http\Controllers\Admin\FileManagerController::class, 'update'])->whereNumber('file')->name('update');
    Route::delete('/{file}', [\App\Http\Controllers\Admin\FileManagerController::class, 'destroy'])->whereNumber('file')->name('destroy');
});

// Profile
Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

// Uploads (CKEditor)
Route::post('upload/ckeditor', [UploadController::class, 'ckeditor'])->name('upload.ckeditor');
