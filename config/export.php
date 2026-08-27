<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | PDF satır tavanı
    |--------------------------------------------------------------------------
    | Bir PDF dosyasına en fazla bu kadar satır girer. Üstü paylaşımlı hosting'de
    | belleği ve süreyi zorluyor; sınır aşıldığında dosya sessizce kırpılmaz,
    | kullanıcı uyarılıp Excel'e yönlendirilir. Excel tarafında tavan yoktur.
    */
    'pdf_row_limit' => (int) env('EXPORT_PDF_ROW_LIMIT', 5000),

    /*
    |--------------------------------------------------------------------------
    | PDF bellek profili
    |--------------------------------------------------------------------------
    | mPDF ürettiği sayfaları belge kapanana kadar bellekte tutar; tüketim satır
    | sayısıyla doğrusal artar. Ölçüm (5 sütunlu liste, A4 dikey): yaklaşık 60 MB
    | taban + satır başına ~55 KB. Buradaki değerler gerçek satır tavanını
    | sunucunun memory_limit'ine göre daraltmak için kullanılır — dosya
    | üretilirken belleğin ortasında ölmek yerine kullanıcı önden uyarılır.
    |
    | Sunucunun belleği bollaştığında ya da liste sütunları daraldığında bu
    | değerler ölçülüp güncellenebilir.
    */
    'pdf_memory_baseline_mb' => (int) env('EXPORT_PDF_MEMORY_BASELINE_MB', 60),
    'pdf_memory_per_row_kb'  => (int) env('EXPORT_PDF_MEMORY_PER_ROW_KB', 55),

    /*
    |--------------------------------------------------------------------------
    | Geçici dosya dizini
    |--------------------------------------------------------------------------
    | Hem OpenSpout hem mPDF ara dosyalarını buraya yazar. Sistemin /tmp dizini
    | paylaşımlı hosting'de yazılamıyor ya da açık_basedir dışında kalabiliyor.
    */
    'temp_path' => storage_path('app/export-temp'),

    /*
    |--------------------------------------------------------------------------
    | Okuma parçası
    |--------------------------------------------------------------------------
    | Satırlar veritabanından bu büyüklükte parçalar hâlinde çekilir; sonuç
    | kümesinin tamamı hiçbir zaman belleğe alınmaz.
    */
    'chunk_size' => 500,

    /*
    |--------------------------------------------------------------------------
    | Dışa aktarılabilir listeler
    |--------------------------------------------------------------------------
    | Adres satırındaki anahtar => tanım sınıfı. Tek dışa aktarma ucu bu haritayı
    | kullanır; yeni bir liste eklemek için buraya bir satır yeter.
    */
    'lists' => [
        'users'            => App\Exports\UserExport::class,
        'blog-categories'  => App\Exports\BlogCategoryExport::class,
        'blog-posts'       => App\Exports\BlogPostExport::class,
        'blog-comments'    => App\Exports\BlogCommentExport::class,
        'pages'            => App\Exports\PageExport::class,
        'sliders'          => App\Exports\SliderExport::class,
        'popups'           => App\Exports\PopupExport::class,
        'faqs'             => App\Exports\FaqExport::class,
        'gallery-categories' => App\Exports\GalleryCategoryExport::class,
        'gallery-items'      => App\Exports\GalleryItemExport::class,
        'contact-messages'   => App\Exports\ContactMessageExport::class,
        'subscribers'        => App\Exports\SubscriberExport::class,
        'audit-logs'         => App\Exports\AuditLogExport::class,
        'mail-logs'          => App\Exports\MailLogExport::class,
        'redirects'          => App\Exports\RedirectExport::class,
        'notifications'      => App\Exports\NotificationExport::class,
        'files'              => App\Exports\UploadedFileExport::class,
        'campaigns'          => App\Exports\CampaignExport::class,
        'languages'          => App\Exports\LanguageExport::class,
        'mail-templates'     => App\Exports\MailTemplateExport::class,
    ],

];
