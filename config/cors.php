<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| Tarayıcıdan gelen çapraz kaynak isteklerinin kuralları. Mobil uygulamalar
| CORS'a tabi değildir (Origin başlığı göndermezler); bu dosya harici web ön
| yüzleri içindir.
|
| Varsayılan '*' geliştirme kolaylığı için: kimlik bilgisi taşımayan, Bearer
| jetonla gelen bir API'de her kaynağa açık olmak bir açık değil. Ama
| CORS_SUPPORTS_CREDENTIALS=true yapılırsa (oturum çerezli SPA kurulumu)
| tarayıcı '*' ile çerez göndermeyi reddeder ve reddetmeliydi: o durumda
| CORS_ALLOWED_ORIGINS mutlaka doldurulur.
|
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*')),
)));

$credentials = (bool) env('CORS_SUPPORTS_CREDENTIALS', false);

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Çerez taşınan kurulumda joker kaynak tarayıcı tarafından reddedilir;
    // ayarlanmamışsa sessizce açık bırakmak yerine kendi adresimize kilitli
    // kalıyor — yanlış yapılandırma çalışan ama güvensiz bir API üretmemeli.
    'allowed_origins' => $credentials && $origins === ['*']
        ? array_filter([env('APP_URL')])
        : $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Sayfalama ve dil bilgisini tarayıcıdaki istemci de okuyabilsin.
    'exposed_headers' => ['Content-Language'],

    'max_age' => (int) env('CORS_MAX_AGE', 86400),

    'supports_credentials' => $credentials,

];
