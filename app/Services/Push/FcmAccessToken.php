<?php

declare(strict_types=1);

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * FCM HTTP v1'in erişim jetonu.
 *
 * Eski API sunucu anahtarını doğrudan başlığa koyuyordu; v1 OAuth2 istiyor:
 * servis hesabıyla imzalanmış bir JWT, Google'ın jeton ucunda bir saatlik
 * erişim jetonuyla takas ediliyor.
 *
 * JWT elde imzalanıyor. Alternatif google/apiclient'ti; o paket elli küsur
 * bağımlılık getiriyor ve bu proje paylaşımlı hosting'e kuruluyor — altı
 * paketlik bir require listesini otuz satır uğruna ikiye katlamak doğru
 * takas değil. İmza openssl'in kendi işi, biçim RFC 7519'da sabit.
 *
 * Jeton önbellekte tutuluyor: her bildirim için Google'a gidip jeton almak,
 * yüz cihazlık bir gönderimi iki yüz isteğe çıkarırdı.
 */
final class FcmAccessToken
{
    /**
     * Google jetonu bir saat veriyor; beş dakika önce tazeleniyor ki uzun
     * süren bir gönderimin ortasında elimizde ölü jeton kalmasın.
     */
    private const CACHE_TTL = 3300;

    private const CACHE_KEY = 'push.fcm.access_token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /**
     * Kullanılabilir bir erişim jetonu, kurulum eksik ya da bozuksa null.
     */
    public function token(): ?string
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return null;
        }

        /** @var string|null $token */
        $token = Cache::get(self::CACHE_KEY);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = $this->mint($credentials);

        if ($token !== null) {
            Cache::put(self::CACHE_KEY, $token, self::CACHE_TTL);
        }

        return $token;
    }

    /**
     * Kurulum tamam mı? Panel sağlık ekranı da bunu soruyor.
     */
    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * Servis hesabının kimliği — Firebase konsolundan indirilen JSON.
     *
     * @return array{client_email: string, private_key: string, project_id: string}|null
     */
    public function credentials(): ?array
    {
        $raw = $this->rawCredentials();

        if ($raw === null) {
            return null;
        }

        try {
            /** @var array<string, mixed> $json */
            $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $email = (string) ($json['client_email'] ?? '');
        $key = (string) ($json['private_key'] ?? '');
        $project = (string) (config('push.fcm.project_id') ?: ($json['project_id'] ?? ''));

        if ($email === '' || $key === '' || $project === '') {
            return null;
        }

        return ['client_email' => $email, 'private_key' => $key, 'project_id' => $project];
    }

    /**
     * Servis hesabı JSON'unun ham metni.
     *
     * İki kaynak var ve panel önce geliyor: yönetici anahtarı panele
     * yapıştırdığında sunucudaki dosyaya dokunmaya gerek kalmıyor. Dosya yolu
     * .env'le kurulum yapmış olanlar için duruyor.
     */
    private function rawCredentials(): ?string
    {
        $json = trim((string) config('push.fcm.credentials_json'));

        if ($json !== '') {
            return $json;
        }

        $path = (string) config('push.fcm.credentials');

        if ($path === '') {
            return null;
        }

        // Göreli yol proje köküne göre çözülüyor: .env'e mutlak yol yazmak
        // dizin taşındığında sessizce kırılıyor.
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    /**
     * Bildirimin gideceği adres — proje kimliği JSON'dan ya da .env'den.
     */
    public function endpoint(): ?string
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return null;
        }

        return 'https://fcm.googleapis.com/v1/projects/' . $credentials['project_id'] . '/messages:send';
    }

    /**
     * Önbelleği düşürür — kimlik dosyası değiştiğinde.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * İmzalı JWT'yi erişim jetonuyla takas eder.
     *
     * @param array{client_email: string, private_key: string, project_id: string} $credentials
     */
    private function mint(array $credentials): ?string
    {
        try {
            $assertion = $this->assertion($credentials);
        } catch (Throwable $e) {
            Log::warning('FCM jetonu imzalanamadı', ['error' => $e->getMessage()]);

            return null;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('push.timeout', 10))
                ->post(self::TOKEN_ENDPOINT, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $assertion,
                ]);
        } catch (Throwable $e) {
            Log::warning('FCM jeton ucuna ulaşılamadı', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('FCM erişim jetonu alınamadı', [
                'status' => $response->status(),
                // Gövde saldırgan verisi değil, Google'ın hata açıklaması;
                // "servis hesabı silinmiş" gibi şeyler burada yazıyor.
                'body'   => mb_substr((string) $response->body(), 0, 500),
            ]);

            return null;
        }

        $token = (string) ($response->json('access_token') ?? '');

        return $token !== '' ? $token : null;
    }

    /**
     * RS256 ile imzalanmış JWT (RFC 7519).
     *
     * @param array{client_email: string, private_key: string, project_id: string} $credentials
     *
     * @throws RuntimeException anahtar okunamazsa ya da imza başarısızsa
     */
    private function assertion(array $credentials): string
    {
        $now = time();

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss'   => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_ENDPOINT,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $payload = $this->base64Url($header) . '.' . $this->base64Url($claims);

        $key = openssl_pkey_get_private($credentials['private_key']);

        if ($key === false) {
            throw new RuntimeException('Servis hesabının özel anahtarı okunamadı.');
        }

        $signature = '';

        if (! openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('JWT imzalanamadı.');
        }

        return $payload . '.' . $this->encode($signature);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function base64Url(array $value): string
    {
        return $this->encode((string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * JWT'nin kendi base64'ü: dolgu yok, +/ yerine -_ (RFC 7515).
     */
    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
