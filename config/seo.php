<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Karakter sınırları
    |--------------------------------------------------------------------------
    | Üç yer aynı sayıyı okumak zorunda: sunucu doğrulaması (FormRequest),
    | formdaki sayaç ve denetleyici. Eskiden üçü ayrışıyordu — blog `max:60`,
    | sayfa `max:70`, sayaç ise ikisinde de `/60` gösteriyordu. Sayı tek yerde
    | durunca o ayrışma bir daha doğmuyor.
    |
    | Değerler arama sonucunun gerçek davranışından geliyor: başlık yaklaşık 60
    | karakterden, açıklama 160'tan sonra kırpılıyor. Alt sınırlar ise "alan
    | doldurulmuş ama işe yaramıyor" durumunu yakalıyor.
    */
    'title' => [
        'min' => (int) env('SEO_TITLE_MIN', 30),
        'max' => (int) env('SEO_TITLE_MAX', 60),
    ],

    'description' => [
        'min' => (int) env('SEO_DESCRIPTION_MIN', 70),
        'max' => (int) env('SEO_DESCRIPTION_MAX', 160),
    ],

    'slug' => [
        'max' => (int) env('SEO_SLUG_MAX', 75),
    ],

    /*
    |--------------------------------------------------------------------------
    | İnce içerik eşiği
    |--------------------------------------------------------------------------
    | Gövde bu kelime sayısının altındaysa "ince içerik" uyarısı çıkıyor. Sayı
    | bir kural değil bir işaret: kısa ama iyi bir sayfa olabilir, o yüzden
    | seviyesi bilgi.
    */
    'thin_content_words' => (int) env('SEO_THIN_CONTENT_WORDS', 150),

    /*
    |--------------------------------------------------------------------------
    | Bağlantı metni denetimi
    |--------------------------------------------------------------------------
    | Nereye gittiğini söylemeyen bağlantı metinleri. Liste çeviri dosyasından
    | değil buradan geliyor: denetim içeriğin dilinde çalışıyor ama bu liste
    | dilden bağımsız — İngilizce bir sayfada Türkçe kalıp da geçebilir.
    */
    'generic_link_texts' => [
        'buraya', 'buraya tıklayın', 'tıkla', 'tıklayın', 'tıklayınız',
        'devamı', 'devamını oku', 'daha fazla', 'link', 'bağlantı',
        'click here', 'click', 'here', 'read more', 'more', 'link here',
    ],

    /*
    |--------------------------------------------------------------------------
    | Kurallar
    |--------------------------------------------------------------------------
    | Denetim bu sırayla koşuyor. Yeni bir kural eklemek: SeoCheck arayüzünü
    | uygulayan bir sınıf yazıp buraya bir satır. Motor gerisini biliyor.
    */
    'checks' => [
        App\Services\Seo\Checks\MetaTitleCheck::class,
        App\Services\Seo\Checks\MetaDescriptionCheck::class,
        App\Services\Seo\Checks\HeadingStructureCheck::class,
        App\Services\Seo\Checks\ImageAltCheck::class,
        App\Services\Seo\Checks\CoverImageCheck::class,
        App\Services\Seo\Checks\LinkTextCheck::class,
        App\Services\Seo\Checks\InternalLinkCheck::class,
        App\Services\Seo\Checks\SlugCheck::class,
        App\Services\Seo\Checks\ContentLengthCheck::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Toplu denetim ekranı
    |--------------------------------------------------------------------------
    | /admin/seo bütün içerikleri denetliyor; gövdeleri okumak pahalı olduğu
    | için sonuç kısa süre önbellekte tutuluyor. Bir içerik kaydedildiğinde
    | kendi satırı zaten düşüyor.
    */
    'audit_cache_ttl' => (int) env('SEO_AUDIT_CACHE_TTL', 900),

];
