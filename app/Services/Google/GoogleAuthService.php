<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Models\GoogleOauthToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Service Account JWT auth — google/apiclient paketine bağımlılık olmadan.
 * Google OAuth2 token endpoint'inden access_token alır, 50 dk cache'ler
 * (Google 1 saatlik token verir, biz tampon ile 50 dk kullanırız).
 *
 * Konfig:
 *   - .env GOOGLE_SERVICE_ACCOUNT_PATH veya storage_path('app/google/service-account.json')
 *   - JSON yoksa isConfigured() false döner — service çağrıldığında
 *     hata fırlatır ama cron sessizce atlar.
 */
final class GoogleAuthService
{
    private const string TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const int    CACHE_TTL = 3000; // 50 dk (Google 3600 sn = 60 dk verir)

    /**
     * @return array{client_email: string, private_key: string, project_id: string}|null
     */
    public function loadServiceAccountJson(): ?array
    {
        $path = $this->serviceAccountPath();

        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || ! isset($data['client_email'], $data['private_key'], $data['project_id'])) {
            return null;
        }

        return [
            'client_email' => (string) $data['client_email'],
            'private_key'  => (string) $data['private_key'],
            'project_id'   => (string) $data['project_id'],
        ];
    }

    public function isConfigured(): bool
    {
        // True if EITHER auth path is set up — callers (SearchConsoleService,
        // IndexingApiService) just need to know "can we make calls?".
        return $this->loadServiceAccountJson() !== null
            || $this->oauthTokenForOwner() !== null;
    }

    public function serviceAccountEmail(): ?string
    {
        return $this->loadServiceAccountJson()['client_email'] ?? null;
    }

    /**
     * Which auth flow is currently active for outbound calls?
     *   - 'oauth'           → user-flow OAuth (preferred when available)
     *   - 'service_account' → JSON-key service account (legacy fallback)
     *   - null              → not configured
     */
    public function activeMode(): ?string
    {
        if ($this->oauthTokenForOwner() !== null) {
            return 'oauth';
        }
        if ($this->loadServiceAccountJson() !== null) {
            return 'service_account';
        }

        return null;
    }

    /**
     * Display label for whichever account is connected.
     * Falls back to the service-account email when no OAuth user is bound.
     */
    public function connectedEmail(): ?string
    {
        $oauth = $this->oauthTokenForOwner();
        if ($oauth !== null) {
            return $oauth->google_email;
        }

        return $this->serviceAccountEmail();
    }

    /**
     * Single entry point used by GSC + Indexing services. Tries OAuth first;
     * falls back to the legacy Service Account JWT when no OAuth user is bound.
     *
     * The OAuth path auto-refreshes when the token is about to expire. The
     * Service Account path piggybacks on the existing 50-minute Cache TTL.
     */
    public function preferredAccessToken(string $scope): string
    {
        $oauth = $this->oauthTokenForOwner();
        if ($oauth !== null) {
            return app(GoogleOAuthService::class)->validAccessToken($oauth);
        }

        return $this->accessToken($scope);
    }

    /**
     * Resolve the active OAuth token. Strategy: prefer the most recently
     * refreshed (or created) token; we only allow one row per user but in
     * practice "the connected admin" is whoever bound last.
     */
    private function oauthTokenForOwner(): ?GoogleOauthToken
    {
        return GoogleOauthToken::query()
            ->orderByDesc('last_refreshed_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Get a valid access_token for the given OAuth scope.
     * Cache key includes scope so different services don't share tokens.
     */
    public function accessToken(string $scope): string
    {
        $cacheKey = 'google.token.' . md5($scope);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $sa = $this->loadServiceAccountJson();
        if ($sa === null) {
            throw new RuntimeException('Google Service Account JSON bulunamadı: ' . $this->serviceAccountPath());
        }

        $jwt = $this->buildJwt($sa['client_email'], $sa['private_key'], $scope);

        $response = Http::asForm()
            ->timeout(20)
            ->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google OAuth token alınamadı: HTTP ' . $response->status() . ' — ' . $response->body());
        }

        $token = (string) ($response->json('access_token') ?? '');
        if ($token === '') {
            throw new RuntimeException('Google OAuth token cevabı boş');
        }

        Cache::put($cacheKey, $token, self::CACHE_TTL);

        return $token;
    }

    /**
     * Build RS256 signed JWT for Google OAuth2 service account auth.
     * Spec: https://developers.google.com/identity/protocols/oauth2/service-account
     */
    private function buildJwt(string $clientEmail, string $privateKey, string $scope): string
    {
        $now = time();

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'iss'   => $clientEmail,
            'scope' => $scope,
            'aud'   => self::TOKEN_URL,
            'exp'   => $now + 3600,
            'iat'   => $now,
        ];

        $segments = [
            $this->base64UrlEncode((string) json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];

        $signingInput = implode('.', $segments);

        $signature = '';
        $success   = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $success) {
            throw new RuntimeException('Google JWT imzalama başarısız (private_key geçersiz olabilir)');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function serviceAccountPath(): string
    {
        $configured = (string) env('GOOGLE_SERVICE_ACCOUNT_PATH', '');

        if ($configured !== '') {
            return str_starts_with($configured, '/')
                ? $configured
                : base_path($configured);
        }

        return storage_path('app/google/service-account.json');
    }
}
