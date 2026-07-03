<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoogleOauthToken;
use App\Services\Google\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Three-leg OAuth 2.0 dance for the "Google ile Bağlan" button.
 *
 *   start      → redirect admin to Google consent
 *   callback   → exchange auth code for refresh + access token, persist row
 *   disconnect → revoke remote grant + delete local row
 *
 * Coexists with the Service Account JSON flow living in GoogleIntegrationController.
 */
final class GoogleOAuthController extends Controller
{
    public function __construct(
        private readonly GoogleOAuthService $oauth,
    ) {}

    /**
     * Generate a CSRF state token, stash it in the session, and bounce the
     * admin to Google's consent screen. Without the state check an attacker
     * could trick the admin into binding a different Google account.
     */
    public function start(Request $request): RedirectResponse
    {
        if (! $this->oauth->isConfigured()) {
            return redirect()
                ->route('admin.google-integration.index')
                ->with('error', 'Google OAuth Client ID veya Secret tanımlı değil. Önce Admin → Ayarlar → Google Entegrasyonu altında bu iki alanı doldurun, sonra tekrar deneyin.');
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        $url = $this->oauth->authorizationUrl($state, $this->redirectUri());

        return redirect()->away($url);
    }

    /**
     * Google redirects the admin back here after consent. We:
     *   1. Validate state (CSRF) — must match what we stashed at `start()`
     *   2. Exchange the authorization code for refresh + access tokens
     *   3. Upsert the GoogleOauthToken row keyed on the current admin's user_id
     *   4. Flush any cached service-account access tokens so the next API
     *      call picks up the new OAuth token immediately
     */
    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');
        $code  = (string) $request->query('code', '');
        $error = (string) $request->query('error', '');

        $expected = (string) $request->session()->pull('google_oauth_state', '');

        if ($error !== '') {
            return redirect()
                ->route('admin.google-integration.index')
                ->with('error', "Google bağlantısı reddedildi: {$error}");
        }

        if ($state === '' || $expected === '' || ! hash_equals($expected, $state)) {
            return redirect()
                ->route('admin.google-integration.index')
                ->with('error', 'Güvenlik hatası: state token eşleşmedi. İşlemi baştan başlatın.');
        }

        if ($code === '') {
            return redirect()
                ->route('admin.google-integration.index')
                ->with('error', 'Google\'dan yetki kodu (code) alınamadı.');
        }

        $userId = $request->user()?->id;
        if ($userId === null) {
            return redirect()
                ->route('login')
                ->with('error', 'Bağlantı için admin olarak giriş yapmış olmanız gerekir.');
        }

        try {
            $token = $this->oauth->exchangeCodeForToken($code, $this->redirectUri(), $userId);
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.google-integration.index')
                ->with('error', 'Google bağlantı hatası: ' . $e->getMessage());
        }

        // Service-account tokens are cached per scope — they shouldn't poison
        // the new flow. Flush is cheap and only impacts a 50-min token cache.
        Cache::flush();

        return redirect()
            ->route('admin.google-integration.index')
            ->with('success', "Google başarıyla bağlandı: {$token->google_email}");
    }

    /**
     * Revoke the remote grant and delete the local token. After this the
     * integration falls back to Service Account JSON (if configured) or
     * shows "not connected".
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $userId = $request->user()?->id;
        if ($userId === null) {
            return redirect()->route('login');
        }

        $token = GoogleOauthToken::where('user_id', $userId)->first();

        if ($token === null) {
            return redirect()
                ->route('admin.google-integration.index')
                ->with('error', 'Bağlı bir Google hesabı bulunamadı.');
        }

        try {
            $this->oauth->disconnect($token);
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.google-integration.index')
                ->with('error', 'Bağlantı kesilirken hata: ' . $e->getMessage());
        }

        Cache::flush();

        return redirect()
            ->route('admin.google-integration.index')
            ->with('success', 'Google bağlantısı kesildi. Token Google tarafında da iptal edildi.');
    }

    /**
     * Single source of truth for the redirect URI. Must match EXACTLY what's
     * registered in Google Cloud Console → OAuth 2.0 Client → Authorized
     * redirect URIs. Even a trailing slash mismatch causes redirect_uri_mismatch.
     */
    private function redirectUri(): string
    {
        return route('admin.google-integration.oauth.callback');
    }
}
