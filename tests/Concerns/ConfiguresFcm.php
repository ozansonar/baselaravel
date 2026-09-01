<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * FCM HTTP v1 kurulumunu sahte bir servis hesabıyla ayağa kaldırır.
 *
 * v1 OAuth2 istiyor: imzalı bir JWT, jeton ucunda erişim jetonuyla takas
 * ediliyor. Yani "yapılandırılmış" demek artık bir dizge ayarlamak değil,
 * okunabilir bir anahtar dosyası olmak demek — üç ayrı sınav sınıfı aynı
 * kurulumu kurduğu için burada.
 *
 * Anahtar gerçekten üretiliyor, yalnız karşı taraf sahte: imza adımı
 * atlanmıyor ki bozulduğunda sınavlar sessizce "gönderilmedi" demesin.
 */
trait ConfiguresFcm
{
    private string $fcmCredentialsPath = '';

    protected function configureFcm(string $projectId = 'deneme-projesi'): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($key, $pem);

        $this->fcmCredentialsPath = storage_path('app/fcm-test-' . bin2hex(random_bytes(6)) . '.json');

        file_put_contents($this->fcmCredentialsPath, (string) json_encode([
            'type'         => 'service_account',
            'project_id'   => $projectId,
            'client_email' => 'push@' . $projectId . '.iam.gserviceaccount.com',
            'private_key'  => $pem,
        ]));

        config()->set('push.driver', 'fcm');
        config()->set('push.fcm.credentials', $this->fcmCredentialsPath);
        config()->set('push.fcm.project_id', '');

        // Erişim jetonu önbellekte tutuluyor; sınavlar arasında taşınmamalı.
        Cache::forget('push.fcm.access_token');
    }

    /**
     * Google'ın iki ucu: jeton ve gönderim.
     *
     * @param array<string, mixed> $sendBody
     */
    protected function fakeFcm(int $sendStatus = 200, array $sendBody = ['name' => 'projects/x/messages/1']): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'sahte-erisim-jetonu',
                'expires_in'   => 3600,
            ], 200),
            'fcm.googleapis.com/v1/*' => Http::response($sendBody, $sendStatus),
        ]);
    }

    protected function forgetFcmCredentials(): void
    {
        if ($this->fcmCredentialsPath !== '' && is_file($this->fcmCredentialsPath)) {
            @unlink($this->fcmCredentialsPath);
        }

        $this->fcmCredentialsPath = '';
    }
}
