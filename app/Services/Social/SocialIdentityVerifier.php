<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sağlayıcının verdiği kimlik jetonunu doğrular.
 *
 * Bu sınıfın tamamı güvenliğin kendisi. Jetonu çözüp içindeki e-postaya
 * güvenmek — imzayı doğrulamadan — herkesin herkesin hesabına girmesi
 * demektir: jeton istemciden geliyor ve istemci onu kendisi yazabilir.
 * Dolayısıyla dört şey birden doğrulanıyor ve biri bile eksikse jeton
 * reddediliyor:
 *
 *  1. **İmza.** Sağlayıcının yayımladığı açık anahtarla (JWKS), jetonun
 *     `kid` başlığında adı geçen anahtar.
 *  2. **`iss`.** Jetonu gerçekten o sağlayıcı mı düzenlemiş.
 *  3. **`aud`.** Bizim istemci kimliğimize mi düzenlenmiş. Bu olmadan
 *     saldırgan, kendi uygulamasına alınmış geçerli bir Google jetonuyla
 *     buraya girebilirdi — imza da `iss` de tutardı.
 *  4. **`exp`.** Süresi geçmiş jeton kabul edilmiyor.
 *
 * Anahtarlar önbellekte: her girişte JWKS indirmek, Google'ın yavaşladığı gün
 * girişin de durması demek olurdu.
 */
final class SocialIdentityVerifier
{
    /**
     * Sağlayıcılar anahtarlarını nadiren döndürüyor; altı saat hem taze hem
     * ucuz. Bilinmeyen bir `kid` görülürse süre beklenmeden yenileniyor.
     */
    private const JWKS_TTL = 21600;

    /** Saatler arası küçük kaymalara tolerans (saniye). */
    private const LEEWAY = 60;

    /**
     * Doğrulanmış kimlik, jeton geçersizse null.
     */
    public function verify(SocialProvider $provider, string $idToken): ?SocialIdentity
    {
        if (! $provider->isConfigured()) {
            Log::warning('Sosyal giriş denendi ama sağlayıcı yapılandırılmamış', [
                'provider' => $provider->value,
            ]);

            return null;
        }

        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            return null;
        }

        [$rawHeader, $rawClaims, $rawSignature] = $parts;

        $header = $this->decodeSegment($rawHeader);
        $claims = $this->decodeSegment($rawClaims);

        if ($header === null || $claims === null) {
            return null;
        }

        // Sağlayıcılar RS256 kullanıyor. Başlıktaki algoritmaya körü körüne
        // uymak, "alg: none" ile imzasız jeton kabul etmenin klasik yolu.
        if (($header['alg'] ?? '') !== 'RS256') {
            return null;
        }

        $kid = (string) ($header['kid'] ?? '');

        if ($kid === '') {
            return null;
        }

        $publicKey = $this->publicKey($provider, $kid);

        if ($publicKey === null) {
            return null;
        }

        $signature = $this->decodeBase64Url($rawSignature);

        if ($signature === null) {
            return null;
        }

        $verified = openssl_verify(
            $rawHeader . '.' . $rawClaims,
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256,
        );

        if ($verified !== 1) {
            return null;
        }

        return $this->identityFrom($provider, $claims);
    }

    /**
     * İmza tuttuktan sonra iddiaların kendisi.
     *
     * @param array<string, mixed> $claims
     */
    private function identityFrom(SocialProvider $provider, array $claims): ?SocialIdentity
    {
        $now = time();

        if (! in_array((string) ($claims['iss'] ?? ''), $provider->issuers(), true)) {
            return null;
        }

        // aud tek dizge ya da dizi olabiliyor; ikisi de OIDC'de geçerli.
        $audience = $claims['aud'] ?? '';
        $audiences = is_array($audience) ? $audience : [$audience];

        if (array_intersect(array_map('strval', $audiences), $provider->audiences()) === []) {
            return null;
        }

        if ((int) ($claims['exp'] ?? 0) + self::LEEWAY < $now) {
            return null;
        }

        if (isset($claims['nbf']) && (int) $claims['nbf'] - self::LEEWAY > $now) {
            return null;
        }

        $subject = (string) ($claims['sub'] ?? '');

        if ($subject === '') {
            return null;
        }

        // email_verified hem boolean hem "true" dizgesi olarak geliyor
        // (Apple dizge gönderiyor).
        $verifiedClaim = $claims['email_verified'] ?? false;
        $emailVerified = $verifiedClaim === true || $verifiedClaim === 'true';

        return new SocialIdentity(
            provider: $provider,
            subject: $subject,
            email: is_string($claims['email'] ?? null) ? (string) $claims['email'] : null,
            emailVerified: $emailVerified,
            name: is_string($claims['name'] ?? null) ? (string) $claims['name'] : null,
        );
    }

    /**
     * `kid`e karşılık gelen açık anahtar, PEM olarak.
     *
     * Bilinmeyen bir `kid` önbelleğin bayatlaması demek olabilir — sağlayıcı
     * anahtar döndürmüştür. O yüzden bir kez zorla tazeleniyor; hâlâ yoksa
     * jeton gerçekten tanınmıyor.
     */
    private function publicKey(SocialProvider $provider, string $kid): ?string
    {
        $keys = $this->jwks($provider, refresh: false);

        if (! isset($keys[$kid])) {
            $keys = $this->jwks($provider, refresh: true);
        }

        return $keys[$kid] ?? null;
    }

    /**
     * Sağlayıcının açık anahtarları: kid => PEM.
     *
     * @return array<string, string>
     */
    private function jwks(SocialProvider $provider, bool $refresh): array
    {
        $cacheKey = 'social.jwks.' . $provider->value;

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        /** @var array<string, string> $keys */
        $keys = Cache::remember($cacheKey, self::JWKS_TTL, function () use ($provider): array {
            try {
                $response = Http::timeout(10)->get($provider->jwksUrl());
            } catch (Throwable $e) {
                Log::warning('Sosyal giriş anahtarları alınamadı', [
                    'provider' => $provider->value,
                    'error'    => $e->getMessage(),
                ]);

                return [];
            }

            if (! $response->successful()) {
                return [];
            }

            $keys = [];

            foreach ((array) $response->json('keys', []) as $key) {
                if (! is_array($key) || ($key['kty'] ?? '') !== 'RSA') {
                    continue;
                }

                $kid = (string) ($key['kid'] ?? '');
                $pem = $this->pemFrom((string) ($key['n'] ?? ''), (string) ($key['e'] ?? ''));

                if ($kid !== '' && $pem !== null) {
                    $keys[$kid] = $pem;
                }
            }

            return $keys;
        });

        // Boş cevap önbelleğe çakılıp kalmasın: sağlayıcı bir dakika
        // erişilemez olduğunda giriş altı saat kapalı kalırdı.
        if ($keys === []) {
            Cache::forget($cacheKey);
        }

        return $keys;
    }

    /**
     * JWK'nin modulus/exponent çifti → PEM açık anahtar.
     *
     * PHP'nin JWK okuyan bir işlevi yok, o yüzden SPKI yapısı elle kuruluyor
     * (RFC 5280). Alternatif bir OAuth kütüphanesi çekmekti; bu proje altı
     * çalışma zamanı bağımlılığıyla ayakta ve yapı otuz satır.
     */
    private function pemFrom(string $modulus, string $exponent): ?string
    {
        $n = $this->decodeBase64Url($modulus);
        $e = $this->decodeBase64Url($exponent);

        if ($n === null || $e === null || $n === '' || $e === '') {
            return null;
        }

        $rsaPublicKey = $this->derSequence($this->derInteger($n) . $this->derInteger($e));

        // 1.2.840.113549.1.1.1 — rsaEncryption
        $algorithm = $this->derSequence("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01" . "\x05\x00");

        $spki = $this->derSequence($algorithm . $this->derBitString($rsaPublicKey));

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private function derSequence(string $contents): string
    {
        return "\x30" . $this->derLength(strlen($contents)) . $contents;
    }

    private function derBitString(string $contents): string
    {
        // Baştaki sıfır: "kullanılmayan bit yok".
        return "\x03" . $this->derLength(strlen($contents) + 1) . "\x00" . $contents;
    }

    private function derInteger(string $bytes): string
    {
        // DER tamsayıları işaretli: en yüksek bit 1 ise sayı negatif okunur,
        // önüne sıfır bayt konuyor.
        if ($bytes !== '' && ord($bytes[0]) > 0x7F) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . $this->derLength(strlen($bytes)) . $bytes;
    }

    private function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeSegment(string $segment): ?array
    {
        $json = $this->decodeBase64Url($segment);

        if ($json === null) {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function decodeBase64Url(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
