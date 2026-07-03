<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * TikTok OAuth akışı.
 *
 * Akış (docs/tiktok.md Bölüm 2.11):
 *   1. /admin/tiktok/oauth/connect  → TikTok authorize sayfasına yönlendir
 *   2. Kullanıcı TikTok'ta izin verir
 *   3. TikTok bizim callback URL'ine code ile redirect eder
 *   4. /admin/tiktok/oauth/callback → code → access_token + refresh_token
 *   5. Setting tablosuna yaz, Settings sayfasına geri dön
 */
final class TiktokOAuthController extends Controller
{
    private const STATE_SESSION_KEY = 'tiktok_oauth_state';
    private const AUTH_BASE_URL = 'https://www.tiktok.com/v2/auth/authorize/';

    /**
     * Adım 1: TikTok authorize sayfasına yönlendir.
     */
    public function connect(Request $request): RedirectResponse
    {
        $clientKey = trim((string) Setting::getValue('tiktok_client_key', ''));
        if ($clientKey === '') {
            return redirect()
                ->route('admin.settings.index')
                ->with('error', 'TikTok Client Key tanımlı değil. Önce Ayarlar → TikTok\'a girip kaydet.');
        }

        // CSRF guard
        $state = Str::random(40);
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        $params = http_build_query([
            'client_key'    => $clientKey,
            'scope'         => 'user.info.basic,video.publish,video.upload,photo.publish',
            'response_type' => 'code',
            'redirect_uri'  => $this->redirectUri(),
            'state'         => $state,
        ]);

        return redirect()->away(self::AUTH_BASE_URL . '?' . $params);
    }

    /**
     * Adım 2: TikTok callback — code → token.
     */
    public function callback(Request $request): RedirectResponse
    {
        // CSRF kontrolü (state eşleşmeli)
        $stateFromSession = $request->session()->pull(self::STATE_SESSION_KEY);
        $stateFromRequest = (string) $request->query('state');

        if ($stateFromSession === null || ! hash_equals($stateFromSession, $stateFromRequest)) {
            return redirect()
                ->route('admin.settings.index')
                ->with('error', 'TikTok OAuth state uyuşmuyor (güvenlik). Tekrar bağlamayı dene.');
        }

        // TikTok hatası callback'te döndüyse
        if ($request->has('error')) {
            $err = (string) $request->query('error');
            $desc = (string) $request->query('error_description', '');
            return redirect()
                ->route('admin.settings.index')
                ->with('error', "TikTok yetkilendirme reddedildi: {$err} {$desc}");
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()
                ->route('admin.settings.index')
                ->with('error', 'TikTok\'tan code dönmedi.');
        }

        $clientKey    = trim((string) Setting::getValue('tiktok_client_key', ''));
        $clientSecret = trim((string) Setting::getValue('tiktok_client_secret', ''));

        if ($clientKey === '' || $clientSecret === '') {
            return redirect()
                ->route('admin.settings.index')
                ->with('error', 'TikTok Client Key/Secret tanımlı değil.');
        }

        // Token exchange
        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post('https://open.tiktokapis.com/v2/oauth/token/', [
                    'client_key'    => $clientKey,
                    'client_secret' => $clientSecret,
                    'code'          => $code,
                    'grant_type'    => 'authorization_code',
                    'redirect_uri'  => $this->redirectUri(),
                ]);

            $data = $response->json();
            if (! $response->successful() || ! isset($data['access_token'])) {
                Log::warning('TikTok OAuth token exchange fail', [
                    'status' => $response->status(),
                    'body'   => $data,
                ]);
                return redirect()
                    ->route('admin.settings.index')
                    ->with('error', 'TikTok token alınamadı: ' . ($data['error_description'] ?? $data['error'] ?? 'unknown'));
            }

            // Setting'e yaz
            Setting::setValue('tiktok_access_token', (string) $data['access_token'], 'social', 'text');
            Setting::setValue('tiktok_refresh_token', (string) ($data['refresh_token'] ?? ''), 'social', 'text');
            Setting::setValue('tiktok_open_id', (string) ($data['open_id'] ?? ''), 'social', 'text');
            Setting::setValue('tiktok_expires_at',
                now()->addSeconds((int) ($data['expires_in'] ?? 86400))->toDateTimeString(),
                'social', 'text');

            // Kullanıcı adını çek (görüntüleme amaçlı)
            try {
                $userResponse = Http::withToken((string) $data['access_token'])
                    ->timeout(10)
                    ->get('https://open.tiktokapis.com/v2/user/info/', [
                        'fields' => 'open_id,union_id,avatar_url,display_name,username',
                    ]);
                if ($userResponse->successful()) {
                    $userData = $userResponse->json('data.user', []);
                    Setting::setValue('tiktok_username',
                        (string) ($userData['username'] ?? $userData['display_name'] ?? ''),
                        'social', 'text');
                }
            } catch (\Throwable $e) {
                // Kullanıcı adı opsiyonel, sessizce geç
                Log::info('TikTok user.info çekilemedi', ['err' => $e->getMessage()]);
            }

            return redirect()
                ->route('admin.settings.index')
                ->with('success', 'TikTok hesabı başarıyla bağlandı.');
        } catch (\Throwable $e) {
            Log::error('TikTok OAuth callback exception', ['err' => $e->getMessage()]);
            return redirect()
                ->route('admin.settings.index')
                ->with('error', 'TikTok bağlantı hatası: ' . $e->getMessage());
        }
    }

    private function redirectUri(): string
    {
        return route('admin.tiktok.oauth.callback');
    }
}
