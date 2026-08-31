<?php

declare(strict_types=1);

$proxies = trim((string) env('TRUSTED_PROXIES', ''));

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Which upstream addresses are allowed to speak for the visitor. Behind a
    | reverse proxy or a CDN the connection Laravel sees comes from the proxy,
    | not from the visitor, so unless the proxy is listed here every request
    | looks like it came from one single address.
    |
    | Three things break when it is not set, and none of them raise an error:
    |
    |   1. Rate limiting. throttle:login, throttle:contact and throttle:register
    |      all key on the IP, so one shared bucket means a single visitor's
    |      failed logins lock everyone out while a real attacker never slows
    |      down.
    |   2. Analytics and audit logs record the proxy instead of the visitor.
    |   3. $request->secure() stays false, so SecurityHeaders never emits HSTS.
    |
    | TRUSTED_PROXIES accepts a comma-separated list of addresses or CIDR
    | ranges. Leave it empty when the application is reached directly — that is
    | the safe default, since trusting a proxy means trusting whatever
    | X-Forwarded-For it sends.
    |
    |   TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12
    |
    | The wildcard '*' trusts every upstream hop. It is the only workable value
    | on shared hosting behind Cloudflare, where the proxy address is neither
    | fixed nor knowable — but it is only safe when the origin cannot be
    | reached except through that proxy. If the server answers on its own IP as
    | well, an attacker skips the proxy and forges the header directly.
    |
    */

    'proxies' => match (true) {
        $proxies === ''  => null,
        $proxies === '*' => '*',
        default          => array_values(array_filter(array_map('trim', explode(',', $proxies)))),
    },

];
