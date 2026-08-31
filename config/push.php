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

    'fcm' => [
        'key'      => env('FCM_SERVER_KEY', ''),
        'endpoint' => env('FCM_ENDPOINT', 'https://fcm.googleapis.com/fcm/send'),
    ],

    /*
    | Kullanılmayan jetonun saklanma süresi (gün). Uygulaması silinmiş bir
    | telefonun jetonu sonsuza kadar durursa her gönderim, çoğu boşa giden
    | yüzlerce isteğe dönüşür.
    */
    'prune_after_days' => (int) env('PUSH_PRUNE_AFTER_DAYS', 180),

];
