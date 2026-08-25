<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed Redirect Hosts
    |--------------------------------------------------------------------------
    |
    | Redirect targets may always be a relative path on this site. An absolute
    | URL is only accepted when its host is listed here — the host from
    | APP_URL is allowed automatically. Use this when content genuinely moved
    | to another domain you control.
    |
    | REDIRECT_ALLOWED_HOSTS=eski-alan-adi.com,cdn.alan-adi.com
    |
    */

    'allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('REDIRECT_ALLOWED_HOSTS', '')),
    ))),

];
