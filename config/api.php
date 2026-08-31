<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API Katmanı
|--------------------------------------------------------------------------
|
| Web arayüzü ile mobil uygulamaların ortak tükettiği API'nin ayarları.
| Rotalar /api/v1 önekiyle yayınlanır (bootstrap/app.php).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Sürüm
    |--------------------------------------------------------------------------
    |
    | Adres önekindeki sürüm. Kırıcı bir değişiklik geldiğinde v2 açılır ve v1
    | bir süre yayında kalır — mobil uygulama mağazadan güncellenene kadar eski
    | sürümü konuşmaya devam eder.
    |
    */

    'version' => 'v1',

    'prefix' => 'api/v1',

    /*
    |--------------------------------------------------------------------------
    | Sayfalama
    |--------------------------------------------------------------------------
    |
    | Liste uçlarının varsayılan ve azami sayfa boyutu. İstemci ?per_page ile
    | değiştirebilir; tavan olmadan tek istekle bütün tablo çekilebilirdi.
    |
    */

    'pagination' => [
        'per_page' => 15,
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Jeton adı
    |--------------------------------------------------------------------------
    |
    | Giriş sırasında istemci kendi cihaz adını gönderebilir (device_name).
    | Göndermezse bu ad kullanılır. Ad jetonun kimliği değil etiketi: kullanıcı
    | "hangi cihazdan giriş yapmışım" sorusunu bununla yanıtlar.
    |
    */

    'token_name' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Hız sınırları (istek / dakika)
    |--------------------------------------------------------------------------
    |
    | `default` her API isteği için geçerli taban; ötekiler kaba kuvvete açık
    | uçlarda onun yerine geçer. Giriş e-posta+IP başına sayılıyor (tek bir
    | hesabı denemek), kayıt ve form yalnız IP başına.
    |
    | Sınıra takılan istek 429 ve `Retry-After` başlığıyla dönüyor — istemci ne
    | zaman yeniden deneyeceğini oradan öğreniyor.
    |
    */

    'rate_limits' => [
        'default'  => (int) env('API_RATE_LIMIT', 60),
        'login'    => (int) env('API_RATE_LIMIT_LOGIN', 5),
        'register' => (int) env('API_RATE_LIMIT_REGISTER', 3),
        'contact'  => (int) env('API_RATE_LIMIT_CONTACT', 3),
        // Şifre sıfırlama kodunu kıramaz kılan sınır bu. Yükseltmeden önce
        // App\Services\PasswordResetCodeService'teki gerekçeyi okuyun.
        'password'     => (int) env('API_RATE_LIMIT_PASSWORD', 5),
        'verification' => (int) env('API_RATE_LIMIT_VERIFICATION', 3),
        // Yorum alanları spam'in birinci hedefi ve API'de reCAPTCHA yok;
        // ön yüzdeki sınırdan (5/dk) bilerek daha sıkı.
        'comment'    => (int) env('API_RATE_LIMIT_COMMENT', 3),
        'newsletter' => (int) env('API_RATE_LIMIT_NEWSLETTER', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Önbellek
    |--------------------------------------------------------------------------
    |
    | Seyrek değişen uçlar (ayarlar, çeviriler, menüler, diller, SSS, slider)
    | ETag ile dönüyor. İstemci `If-None-Match` gönderdiğinde içerik değişmemişse
    | 304 alıyor — gövde hiç inmiyor. Çeviri sözlüğü yüz kilobayta yaklaşabildiği
    | için mobil veri açısından en ucuz kazanç bu.
    |
    | `max_age` saniye: bu süre boyunca istemci sormadan kendi kopyasını
    | kullanıyor. Kısa tutuluyor çünkü panelden yapılan bir düzeltmenin
    | uygulamaya yansıması dakikalar değil saniyeler almalı.
    |
    */

    'cache' => [
        'max_age' => (int) env('API_CACHE_MAX_AGE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Açılış ekranı
    |--------------------------------------------------------------------------
    |
    | /home ucunun her bölümde kaç kayıt döndüreceği. Ön yüzdeki ana sayfayla
    | aynı sayılarla başlıyor: dört yazı (ilki geniş kart, kalan üçü ızgara) ve
    | sekiz görsellik bir galeri şeridi.
    |
    */

    'home' => [
        'posts'           => (int) env('API_HOME_POSTS', 4),
        'gallery_photos'  => (int) env('API_HOME_GALLERY_PHOTOS', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dışarı açılan ayarlar
    |--------------------------------------------------------------------------
    |
    | settings tablosunda SMTP parolası, reCAPTCHA gizli anahtarı ve Telegram
    | jetonu da duruyor. /settings ucu bu yüzden tabloyu olduğu gibi basmaz;
    | yalnız aşağıdaki gruplar yayınlanır, o grupların içinden de denylist'e
    | takılanlar düşer.
    |
    | Beyaz liste bilinçli: yeni bir ayar grubu eklendiğinde uç onu kendiliğinden
    | yayınlamaz. Sızdırmayı unutmak, göstermeyi unutmaktan pahalı.
    |
    */

    'public_settings' => [

        'groups' => ['general', 'contact', 'social', 'seo', 'appearance'],

        // Grubu açık olsa da dışarı çıkmayacak anahtarlar.
        'except' => [
            // Yöneticinin kendi adresi — form bildirimleri buraya düşer,
            // ziyaretçinin bilmesi gereken adres contact_email.
            'admin_notification_email',
            // Ham HTML/JS; düzenin <head>'ine basılmak için var, bir istemcinin
            // gövdesine değil.
            'custom_head_code',
        ],

        // Anahtar adında bunlardan biri geçiyorsa hiçbir koşulda yayınlanmaz.
        // Gruba bakmaz: yarın 'general' grubuna bir "..._secret" eklense bile
        // kendiliğinden kapalı kalır.
        'forbidden_patterns' => ['password', 'secret', 'token', 'api_key', 'private'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dışarı açılan çeviri grupları
    |--------------------------------------------------------------------------
    |
    | /translations ucunun basabileceği lang/ grupları. Ön yüz metinleri 'site'
    | grubunda; 'validation' gibi yer tutuculu ve çoğul kurallı dosyalar dışarıda
    | bırakıldı — mobil istemci onları kendi katmanında çözer.
    |
    */

    'public_translation_groups' => ['site'],

];
