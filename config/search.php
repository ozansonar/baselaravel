<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Site Araması
|--------------------------------------------------------------------------
|
| Ziyaretçinin tek kutudan bütün içerikte arama yapması. Kapsam panelden
| yönetilen içerik: blog yazıları, sayfalar, sıkça sorulan sorular ve galeri.
|
| Arama LIKE tabanlı — tam metin dizini (FULLTEXT) kullanılmıyor. Gerekçe:
| MySQL'in FULLTEXT'i InnoDB'de sürüme bağlı davranıyor, SQLite'ta hiç yok ve
| bu proje ikisinde de aynı sonucu vermek zorunda. İçerik hacmi (yüzlerce
| satır) LIKE'ın rahatça kaldıracağı ölçekte; on binlere çıkıldığında doğru
| adım Meilisearch/Scout olur, kırılgan bir FULLTEXT değil.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Aranan türler
    |--------------------------------------------------------------------------
    |
    | Listeden çıkarılan tür ne sonuçlarda ne de süzgeç çubuğunda görünür.
    | Değerler App\Enums\SearchType karşılıkları.
    |
    */

    'types' => ['blog', 'page', 'faq', 'gallery'],

    /*
    |--------------------------------------------------------------------------
    | Terim sınırları
    |--------------------------------------------------------------------------
    |
    | `min` altındaki terim aranmıyor: tek harf pratikte bütün siteyi
    | döndürür ve ziyaretçiye hiçbir şey anlatmaz. `max` ise sınırsız bir LIKE
    | kalıbının her istekte bütün tabloları taramasını engelliyor — istemcideki
    | maxSize[] ile birebir aynı olmak zorunda.
    |
    */

    'min_length' => 2,
    'max_length' => 100,

    /*
    |--------------------------------------------------------------------------
    | Sayfalama
    |--------------------------------------------------------------------------
    */

    'per_page' => 10,

    // API tarafı config/api.php'deki ortak sayfalama ayarlarını kullanıyor;
    // burada ikinci bir tavan tanımlamak iki kaynağın zamanla ayrışması demek.

    /*
    |--------------------------------------------------------------------------
    | Özet uzunluğu
    |--------------------------------------------------------------------------
    |
    | Sonuç kartındaki metnin kırpılacağı karakter sayısı.
    |
    */

    'snippet_length' => 180,

];
