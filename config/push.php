<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mobil bildirimler
|--------------------------------------------------------------------------
| Jeton kaydı yapılandırma istemiyor ve kutudan çıkar çıkmaz çalışıyor;
| burada yapılandırılan şey yalnızca gönderim tarafı.
|
| Taşıyıcı tanımlanmadığında gönderim sessizce kaybolmuyor: log'a düşüyor ve
| çağıran tarafa "gönderilmedi" diye dönüyor. Mobil uygulama geliştirilirken
| jeton kaydı ilk gün gerekiyor, gerçek gönderim ise mağaza hesapları
| açıldıktan sonra — sunucunun ikincisini beklemesi için sebep yok.
*/

return [

    /*
    | 'null' → gönderim yapılmaz, yalnız kaydedilir (varsayılan)
    | 'fcm'  → Firebase Cloud Messaging
    */
    'driver' => env('PUSH_DRIVER', 'null'),

    'timeout' => (int) env('PUSH_TIMEOUT', 10),

    /*
    | Firebase Cloud Messaging — HTTP v1.
    |
    | Eski sürüm (sunucu anahtarı + /fcm/send) Google tarafından 2024 Haziran'da
    | kapatıldı; v1 OAuth2 istiyor. Gereken tek şey Firebase konsolundan
    | indirilen servis hesabı JSON'u:
    |
    |   Firebase Console → Proje ayarları → Hizmet hesapları
    |   → "Yeni özel anahtar oluştur"
    |
    | Dosya depoya girmemeli; storage/app/ altına konup .env'de yolu verilir.
    | Proje kimliği JSON'un içinde zaten var, FCM_PROJECT_ID yalnız onu
    | ezmek istenirse gerekiyor.
    */
    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS', ''),
        'project_id'  => env('FCM_PROJECT_ID', ''),
    ],

    /*
    | Kullanılmayan jetonun saklanma süresi (gün). Uygulaması silinmiş bir
    | telefonun jetonu sonsuza kadar durursa her gönderim, çoğu boşa giden
    | yüzlerce isteğe dönüşür.
    */
    'prune_after_days' => (int) env('PUSH_PRUNE_AFTER_DAYS', 180),

];
