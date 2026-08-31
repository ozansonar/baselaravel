<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Oturum çerezini kabul eden alan adları. Yalnızca kendi web ön yüzümüz
    | buraya yazılır: listedeki bir kaynaktan gelen istek jeton yerine oturumla
    | doğrulanır, yani ön yüzün jeton saklamasına gerek kalmaz.
    |
    | Mobil uygulama bu listeye GİRMEZ — o Bearer jetonla gelir ve durumsuzdur.
    | Listeye güvenilmeyen bir alan adı yazmak, o alandan gelen isteğe oturum
    | çerezini açmak demektir.
    |
    */

    'stateful' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', sprintf(
            '%s%s',
            'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
            Sanctum::currentApplicationUrlWithPort(),
        ))),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | Jetona bakmadan önce denenen guard'lar. 'web' burada duruyor ki stateful
    | alan adlarından gelen istekler mevcut oturumlarıyla geçebilsin; hiçbiri
    | tutmazsa Authorization başlığındaki jetona düşülür.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Jetonun ömrü (dakika). Varsayılan 30 gün: mobilde her açılışta yeniden
    | giriş istemeyecek kadar uzun, çalınan bir jetonun sonsuza dek geçerli
    | olmayacağı kadar kısa. null yazılırsa jeton hiç sona ermez — bilerek
    | seçilmediyse kullanılmamalı.
    |
    | Süresi dolan satırlar diskte kalır; `sanctum:prune-expired` zamanlanmış
    | görevi onları siler (routes/console.php).
    |
    */

    'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 60 * 24 * 30),

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Üretilen jetonların önüne eklenen etiket. Sızıntı taramaları (GitHub
    | secret scanning gibi) jetonu bu önekten tanır.
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
