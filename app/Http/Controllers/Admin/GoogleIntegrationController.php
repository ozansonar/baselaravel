<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadGoogleServiceAccountRequest;
use App\Models\GoogleApiLog;
use App\Models\Setting;
use App\Models\GoogleOauthToken;
use App\Services\Google\GoogleAuthService;
use App\Services\Google\GoogleOAuthService;
use App\Services\Google\SearchConsoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Throwable;

/**
 * Admin → Google Entegrasyonu.
 *
 * Service Account JSON dosyasının admin panelden yüklenmesi, silinmesi ve
 * feature flag'lerinin (gsc / indexing) açılıp kapanması.
 *
 * Dosya storage/app/google/service-account.json yoluna yazılır
 * (.gitignore'da → asla commit edilmez, web'den erişilemez).
 */
final class GoogleIntegrationController extends Controller
{
    public function __construct(
        private readonly GoogleAuthService $auth,
        private readonly SearchConsoleService $gsc,
        private readonly GoogleOAuthService $oauth,
    ) {}

    public function index(): View
    {
        $jsonPath             = $this->jsonPath();
        $jsonExists           = is_file($jsonPath);
        $jsonUploadedAt       = $jsonExists ? \Illuminate\Support\Carbon::createFromTimestamp(filemtime($jsonPath)) : null;
        $serviceAccountInfo   = $jsonExists ? $this->auth->loadServiceAccountJson() : null;

        $oauthConfigured = $this->oauth->isConfigured();
        $oauthToken      = GoogleOauthToken::query()->orderByDesc('id')->first();
        $activeMode      = $this->auth->activeMode();
        $configured      = $activeMode !== null;

        $gscEnabled      = Setting::getValue('google_gsc_enabled', '1') === '1';
        $indexingEnabled = Setting::getValue('google_indexing_enabled', '0') === '1';
        $propertyUrl     = $this->gsc->siteUrl();

        $lastSuccess = GoogleApiLog::query()
            ->service(GoogleApiLog::SERVICE_GSC)
            ->where('status', GoogleApiLog::STATUS_SUCCESS)
            ->latest()
            ->first(['created_at']);

        $recentFailures = GoogleApiLog::query()
            ->where('status', GoogleApiLog::STATUS_FAILED)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        return view('admin.google-integration.index', [
            'configured'         => $configured,
            'activeMode'         => $activeMode,
            'oauthConfigured'    => $oauthConfigured,
            'oauthToken'         => $oauthToken,
            'oauthClientId'      => trim((string) Setting::getValue('google_oauth_client_id', '')),
            'oauthSecretSet'     => trim((string) Setting::getValue('google_oauth_client_secret', '')) !== '',
            'serviceAccountInfo' => $serviceAccountInfo,
            'jsonExists'         => $jsonExists,
            'jsonUploadedAt'     => $jsonUploadedAt,
            'jsonPath'           => $jsonPath,
            'gscEnabled'         => $gscEnabled,
            'indexingEnabled'    => $indexingEnabled,
            'propertyUrl'        => $propertyUrl,
            'lastSuccess'        => $lastSuccess,
            'recentFailures'     => $recentFailures,
            'oauthRedirectUri'   => route('admin.google-integration.oauth.callback'),
        ]);
    }

    public function upload(UploadGoogleServiceAccountRequest $request): RedirectResponse
    {
        $file = $request->file('service_account_file');

        if ($file === null) {
            return back()->with('error', 'Dosya bulunamadı.');
        }

        $directory = dirname($this->jsonPath());

        try {
            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, mode: 0700, recursive: true);
            }

            // Eski JSON varsa üzerine yaz (admin yenileme yapabilir)
            $file->move($directory, basename($this->jsonPath()));

            // Permission 600 — sadece web kullanıcısı okuyabilir
            @chmod($this->jsonPath(), 0600);

            // Eski token cache'i temizle (yeni anahtar yeni token üretmeli)
            Cache::flush();

            return redirect()->route('admin.google-integration.index')
                ->with('success', 'Service Account JSON başarıyla yüklendi. Şimdi "Bağlantıyı Test Et" butonu ile doğrulayabilirsin.');
        } catch (Throwable $e) {
            return back()->with('error', 'Yükleme hatası: ' . $e->getMessage());
        }
    }

    public function delete(): RedirectResponse
    {
        $path = $this->jsonPath();

        if (is_file($path)) {
            @unlink($path);
            Cache::flush();

            return back()->with('success', 'Service Account JSON silindi. Entegrasyon devre dışı.');
        }

        return back()->with('error', 'JSON dosyası zaten yüklü değil.');
    }

    /**
     * Persist Google OAuth Client ID + Secret into the settings table.
     * These are required before the "Google ile Bağlan" button works.
     *
     * Empty `client_secret` is left untouched (password-style behavior — keeps
     * the existing value when the field is submitted blank).
     */
    public function saveOAuthCredentials(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'google_oauth_client_id'     => ['nullable', 'string', 'max:191'],
            'google_oauth_client_secret' => ['nullable', 'string', 'max:191'],
        ]);

        Setting::setValue(
            'google_oauth_client_id',
            isset($validated['google_oauth_client_id']) ? trim($validated['google_oauth_client_id']) : null,
            'integrations',
            'text',
        );

        if (! empty($validated['google_oauth_client_secret'])) {
            Setting::setValue(
                'google_oauth_client_secret',
                trim($validated['google_oauth_client_secret']),
                'integrations',
                'password',
            );
        }

        Setting::clearSettingsCache();

        return back()->with('success', 'Google OAuth bilgileri kaydedildi. Şimdi "Google ile Bağlan" butonuna basabilirsin.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'google_gsc_enabled'      => 'sometimes|in:0,1',
            'google_indexing_enabled' => 'sometimes|in:0,1',
        ]);

        Setting::setValue('google_gsc_enabled', (string) ($validated['google_gsc_enabled'] ?? '0'), 'integrations', 'boolean');
        Setting::setValue('google_indexing_enabled', (string) ($validated['google_indexing_enabled'] ?? '0'), 'integrations', 'boolean');

        return back()->with('success', 'Entegrasyon ayarları güncellendi.');
    }

    public function testConnection(): JsonResponse
    {
        if (! $this->auth->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Service Account JSON bulunamadı. Önce dosyayı yükle.',
            ], 422);
        }

        try {
            $this->gsc->fetchAndStoreQueries(days: 1, limit: 5);

            return response()->json([
                'success' => true,
                'message' => 'Google Search Console bağlantısı başarılı! Veriler çekildi.',
                'email'   => $this->auth->serviceAccountEmail(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bağlantı hatası: ' . $e->getMessage(),
            ], 422);
        }
    }

    private function jsonPath(): string
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
