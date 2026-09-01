<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\SocialProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Sahte bir sağlayıcı: gerçek RSA anahtarıyla kimlik jetonu imzalar ve o
 * anahtarı JWKS olarak yayımlar.
 *
 * İmza adımı sahte değil — yalnız anahtarın sahibi biziz. Doğrulayıcı bozulup
 * imzaya bakmaz hâle gelse sınavlar bunu göremezdi; bu kurulum tam olarak onu
 * görmek için var: `forgeToken()` başka bir anahtarla imzalıyor ve reddedilmesi
 * bekleniyor.
 */
trait IssuesSocialIdTokens
{
    private string $socialKid = 'sinav-anahtari';

    /** @var \OpenSSLAsymmetricKey|null */
    private $socialPrivateKey = null;

    /** @var array<string, mixed> */
    private array $socialJwk = [];

    protected function bootSocialProvider(string $clientId = 'sinav-istemci-kimligi'): void
    {
        $this->socialPrivateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $details = openssl_pkey_get_details($this->socialPrivateKey);

        $this->socialJwk = [
            'kty' => 'RSA',
            'kid' => $this->socialKid,
            'use' => 'sig',
            'alg' => 'RS256',
            'n'   => $this->base64Url($details['rsa']['n']),
            'e'   => $this->base64Url($details['rsa']['e']),
        ];

        config()->set('services.google.client_ids', $clientId);
        config()->set('services.apple.client_ids', $clientId);

        Cache::forget('social.jwks.google');
        Cache::forget('social.jwks.apple');

        Http::fake([
            'www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [$this->socialJwk]], 200),
            'appleid.apple.com/auth/keys'        => Http::response(['keys' => [$this->socialJwk]], 200),
        ]);
    }

    /**
     * Geçerli bir kimlik jetonu.
     *
     * @param array<string, mixed> $overrides
     */
    protected function idToken(SocialProvider $provider, array $overrides = []): string
    {
        $claims = array_merge([
            'iss'            => $provider->issuers()[0],
            'aud'            => 'sinav-istemci-kimligi',
            'sub'            => 'saglayici-kullanici-1',
            'email'          => 'sosyal@example.com',
            'email_verified' => true,
            'name'           => 'Ada Lovelace',
            'iat'            => time(),
            'exp'            => time() + 3600,
        ], $overrides);

        return $this->sign($claims, $this->socialPrivateKey);
    }

    /**
     * Başka bir anahtarla imzalanmış jeton — imza doğrulaması çalışıyorsa
     * reddedilmeli.
     *
     * @param array<string, mixed> $overrides
     */
    protected function forgedToken(SocialProvider $provider, array $overrides = []): string
    {
        $other = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $claims = array_merge([
            'iss'            => $provider->issuers()[0],
            'aud'            => 'sinav-istemci-kimligi',
            'sub'            => 'saldirgan',
            'email'          => 'kurban@example.com',
            'email_verified' => true,
            'iat'            => time(),
            'exp'            => time() + 3600,
        ], $overrides);

        return $this->sign($claims, $other);
    }

    /**
     * İmzasız jeton — "alg: none" saldırısı.
     *
     * @param array<string, mixed> $overrides
     */
    protected function unsignedToken(SocialProvider $provider, array $overrides = []): string
    {
        $claims = array_merge([
            'iss'            => $provider->issuers()[0],
            'aud'            => 'sinav-istemci-kimligi',
            'sub'            => 'saldirgan',
            'email'          => 'kurban@example.com',
            'email_verified' => true,
            'exp'            => time() + 3600,
        ], $overrides);

        $header = $this->base64Url((string) json_encode(['alg' => 'none', 'typ' => 'JWT', 'kid' => $this->socialKid]));
        $payload = $this->base64Url((string) json_encode($claims));

        // İmza yerine boş yerine bir şey konuyor: biçim denetimi üç parça
        // istiyor.
        return $header . '.' . $payload . '.' . $this->base64Url('imzasiz');
    }

    /**
     * @param array<string, mixed> $claims
     * @param \OpenSSLAsymmetricKey|null $key
     */
    private function sign(array $claims, $key): string
    {
        $header = $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->socialKid]));
        $payload = $this->base64Url((string) json_encode($claims));

        $signature = '';
        openssl_sign($header . '.' . $payload, $signature, $key, OPENSSL_ALGO_SHA256);

        return $header . '.' . $payload . '.' . $this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
