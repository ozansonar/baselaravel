<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Sosyal giriş — Google ve Apple.
    |
    | Akış mobilin akışı: uygulama sağlayıcının SDK'sıyla bir kimlik jetonu
    | alıyor, sunucu onu sağlayıcının açık anahtarıyla doğruluyor. Yani
    | istemci sırrı (client secret) gerekmiyor; gereken tek şey jetonun kime
    | düzenlendiğini bilmek.
    |
    | `client_ids` virgülle ayrılmış liste: aynı ürünün iOS, Android ve web
    | istemcileri ayrı kimlik taşıyor ve üçü de aynı hesaba giriyor.
    |
    |   Google  → Google Cloud Console → Kimlik bilgileri → OAuth istemci
    |             kimlikleri (iOS / Android / Web ayrı ayrı)
    |   Apple   → Bundle ID (mobil) ve/veya Services ID (web)
    |
    | Boş bırakılan sağlayıcı kapalı sayılıyor: `aud` doğrulaması yapılamayan
    | bir jeton kabul edilemez — başka bir uygulamaya alınmış geçerli bir
    | Google jetonu buraya girerdi.
    */
    'google' => [
        'client_ids' => env('GOOGLE_CLIENT_IDS', ''),
    ],

    'apple' => [
        'client_ids' => env('APPLE_CLIENT_IDS', ''),
    ],

    'recaptcha' => [
        'site_key'   => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

];
