<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Models\GoogleOauthToken;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * OAuth 2.0 user-flow against Google for Search Console + Indexing API.
 *
 * Why OAuth instead of the existing Service Account flow?
 *   - GSC silently rejects service-account emails on certain property types
 *     (Domain properties, non-Workspace personal accounts) → "user added"
 *     never takes effect, every API call returns 401.
 *   - With OAuth, the admin grants consent under their own Google identity,
 *     which is already an Owner on the GSC property → zero extra plumbing.
 *
 * This service coexists with `GoogleAuthService` (Service Account JWT). When
 * both are configured, OAuth wins via `GoogleAuthService::preferredAccessToken()`.
 *
 * Configuration (admin → Settings → Google entegrasyonları, OR .env):
 *   - GOOGLE_OAUTH_CLIENT_ID     → settings.google_oauth_client_id (DB fallback)
 *   - GOOGLE_OAUTH_CLIENT_SECRET → settings.google_oauth_client_secret (DB fallback)
 *
 * Scopes requested: webmasters.readonly + indexing — covers every read/write
 * the existing services need without over-asking.
 */
final class GoogleOAuthService
{
    private const string AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const string TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const string REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    private const string USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';

    /** @var list<string> */
    public const array DEFAULT_SCOPES = [
        'https://www.googleapis.com/auth/webmasters.readonly',
        'https://www.googleapis.com/auth/indexing',
        'https://www.googleapis.com/auth/userinfo.email',
        'openid',
    ];

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    /**
     * Build the Google consent URL the admin gets redirected to.
     *
     * `state` is a CSRF guard — the caller stores the same value in the
     * session, then we compare it on callback. Without it an attacker could
     * trick a logged-in admin into binding the attacker's Google account.
     *
     * `access_type=offline` + `prompt=consent` ensure we receive a
     * `refresh_token` on every consent (Google omits it when re-consenting
     * unless `prompt=consent` is forced).
     */
    public function authorizationUrl(string $state, string $redirectUri): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google OAuth Client ID veya Client Secret tanımlı değil. Önce Admin → Ayarlar → Google Entegrasyonu altında bu iki alanı doldurun.');
        }

        $params = [
            'client_id'     => $this->clientId(),
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => implode(' ', self::DEFAULT_SCOPES),
            'access_type'   => 'offline',
            'include_granted_scopes' => 'true',
            'prompt'        => 'consent',
            'state'         => $state,
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange an authorization code for access + refresh tokens, then upsert
     * the user's `GoogleOauthToken` row. Returns the persisted model.
     *
     * @throws RuntimeException on Google API failure or parse error.
     */
    public function exchangeCodeForToken(string $code, string $redirectUri, int $userId): GoogleOauthToken
    {
        $response = Http::asForm()
            ->timeout(20)
            ->post(self::TOKEN_URL, [
                'code'          => $code,
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'redirect_uri'  => $redirectUri,
                'grant_type'    => 'authorization_code',
            ]);

        if (! $response->successful()) {
            $err = (string) ($response->json('error_description') ?? $response->json('error') ?? $response->body());
            throw new RuntimeException("Google token alınamadı (HTTP {$response->status()}): {$err}");
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Google token yanıtı çözülemedi.');
        }

        $accessToken  = (string) ($payload['access_token']  ?? '');
        $refreshToken = (string) ($payload['refresh_token'] ?? '');
        $expiresIn    = (int)    ($payload['expires_in']    ?? 0);
        $tokenType    = (string) ($payload['token_type']    ?? 'Bearer');
        $scopes       = (string) ($payload['scope']         ?? implode(' ', self::DEFAULT_SCOPES));

        if ($accessToken === '') {
            throw new RuntimeException('Google yanıtı access_token içermiyor.');
        }

        if ($refreshToken === '') {
            // Google sends refresh_token only on the FIRST consent for a given
            // (app, user) pair. If the user previously authorized this app then
            // disconnected without revoking from their Google Account, the
            // second consent silently omits refresh_token. Fix: user revokes the
            // app at https://myaccount.google.com/permissions, then retries.
            throw new RuntimeException(
                'Google refresh_token göndermedi. Bu genellikle bu hesabın bu uygulamaya '
                . 'daha önce izin vermiş olmasından kaynaklanır. Çözüm: '
                . 'https://myaccount.google.com/permissions adresinden bu uygulamanın '
                . 'erişimini kaldırın, sonra tekrar "Google ile Bağlan" butonuna basın.'
            );
        }

        $email = $this->fetchUserEmail($accessToken);

        // Reconnect path: a soft-deleted row for this user blocks plain
        // updateOrCreate (it ignores trashed rows). Manually find-with-trashed
        // and restore so we keep audit trail without violating the unique key.
        $existing = GoogleOauthToken::withTrashed()->where('user_id', $userId)->first();

        $payload = [
            'google_email'      => $email,
            'access_token'      => $accessToken,
            'refresh_token'     => $refreshToken,
            'token_type'        => $tokenType,
            'scopes'            => $scopes,
            'expires_at'        => now()->addSeconds(max(60, $expiresIn))->copy(),
            'last_refreshed_at' => now(),
        ];

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->fill($payload)->save();

            return $existing->refresh();
        }

        return GoogleOauthToken::create(array_merge($payload, ['user_id' => $userId]));
    }

    /**
     * Refresh the access token using the stored refresh token.
     * Updates the row in place and returns the new access token.
     *
     * @throws RuntimeException if Google rejects the refresh (typically: token
     *         was revoked by user, app deleted, or 7-day test-mode expiry hit).
     */
    public function refreshAccessToken(GoogleOauthToken $token): string
    {
        $response = Http::asForm()
            ->timeout(20)
            ->post(self::TOKEN_URL, [
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'refresh_token' => $token->refresh_token,
                'grant_type'    => 'refresh_token',
            ]);

        if (! $response->successful()) {
            $err = (string) ($response->json('error_description') ?? $response->json('error') ?? $response->body());
            throw new RuntimeException("Google token yenileme başarısız (HTTP {$response->status()}): {$err}. Bağlantıyı kesip yeniden bağlanın.");
        }

        $payload = $response->json();
        $accessToken = (string) ($payload['access_token'] ?? '');
        $expiresIn   = (int)    ($payload['expires_in']   ?? 3600);

        if ($accessToken === '') {
            throw new RuntimeException('Google yenileme yanıtı access_token içermiyor.');
        }

        $token->update([
            'access_token'      => $accessToken,
            'expires_at'        => now()->addSeconds(max(60, $expiresIn))->copy(),
            'last_refreshed_at' => now(),
        ]);

        return $accessToken;
    }

    /**
     * Return a usable access token, refreshing transparently if it's about to
     * expire. This is the main entry point used by GoogleAuthService.
     */
    public function validAccessToken(GoogleOauthToken $token): string
    {
        if ($token->isExpiringSoon()) {
            return $this->refreshAccessToken($token);
        }

        return $token->access_token;
    }

    /**
     * Revoke the user's grant on Google's side AND soft-delete the local row.
     * Best-effort: even if the remote revoke fails (network/expired token),
     * the local record is removed so the integration shows "disconnected".
     */
    public function disconnect(GoogleOauthToken $token): void
    {
        try {
            Http::asForm()
                ->timeout(10)
                ->post(self::REVOKE_URL, ['token' => $token->refresh_token]);
        } catch (\Throwable) {
            // Swallow — local cleanup must always succeed.
        }

        $token->delete();
    }

    /**
     * Fetch the connected user's primary email — used as a display label
     * in the admin integration card.
     */
    private function fetchUserEmail(string $accessToken): string
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get(self::USERINFO_URL);

            if ($response->successful()) {
                return Str::limit((string) ($response->json('email') ?? 'unknown'), 180, '');
            }
        } catch (\Throwable) {
            // ignore — fall through to placeholder
        }

        return 'unknown';
    }

    private function clientId(): string
    {
        $env = (string) env('GOOGLE_OAUTH_CLIENT_ID', '');
        if ($env !== '') {
            return $env;
        }

        return trim((string) Setting::getValue('google_oauth_client_id', ''));
    }

    private function clientSecret(): string
    {
        $env = (string) env('GOOGLE_OAUTH_CLIENT_SECRET', '');
        if ($env !== '') {
            return $env;
        }

        return trim((string) Setting::getValue('google_oauth_client_secret', ''));
    }
}
