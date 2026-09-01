<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Saklanan sürüm sayısı
    |--------------------------------------------------------------------------
    | Bir içeriğin **bir dilindeki** son kaç kaydı saklanır. Bu sayının üstüne
    | çıkan en eski sürümler kalıcı olarak silinir.
    |
    | Sabit sayı, zaman aralığı değil: disk kullanımı böyle tahmin edilebilir
    | oluyor. Günde yirmi kez kaydedilen bir sayfa da, ayda bir kaydedilen bir
    | sayfa da aynı yeri kaplıyor — ikincisinde geçmiş aylara uzanıyor,
    | birincisinde son güne.
    */
    'keep' => (int) env('REVISIONS_KEEP', 20),

    /*
    |--------------------------------------------------------------------------
    | Sürümlenen modeller
    |--------------------------------------------------------------------------
    | Model sınıfı => o modelde geçmişi tutulan alanlar.
    |
    | Kapsam bilinçli olarak dar: en çok düzenlenen ve kaybı en pahalı olan iki
    | içerik türü. Galeri, SSS, slider ve popup dışarıda — onlarda düzenleme
    | genelde tek alanlık ve yanlışlıkla silinen bir paragrafın karşılığı yok.
    | Gerçek ihtiyaç görülürse buraya bir satır eklemek yetiyor.
    |
    | Listedeki alanlar aynı zamanda **tetikleyici**: yalnız bunlardan biri
    | değiştiğinde yeni sürüm yazılıyor. `views` gibi sayaçlar bilerek dışarıda
    | — her ziyaretçi bir sürüm doğursaydı geçmiş kendi gürültüsünde boğulurdu.
    */
    'models' => [
        App\Models\Page::class => [
            'title',
            'slug',
            'content',
            'excerpt',
            'image',
            'status',
            'meta_title',
            'meta_description',
            'published_at',
        ],
        App\Models\BlogPost::class => [
            'title',
            'slug',
            'excerpt',
            'body',
            'image',
            'blog_category_id',
            'status',
            'meta_title',
            'meta_description',
            'published_at',
        ],
    ],

];
