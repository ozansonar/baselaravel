<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\MailService;
use App\Services\SettingService;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly UploadService $uploadService,
        private readonly MailService $mailService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Setting::class);

        $allSettings = Setting::all()->keyBy('key');

        $dbConnected = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $dbConnected = false;
        }

        return view('admin.settings.index', [
            'settings'             => $allSettings,
            'systemInfo'           => [
                'php_version'     => PHP_VERSION,
                'laravel_version' => app()->version(),
                'db_connected'    => $dbConnected,
                // Web request'in gerçek PHP ayarları (CLI'dan farklı olabilir)
                'php_upload_max_filesize' => (string) ini_get('upload_max_filesize'),
                'php_post_max_size'       => (string) ini_get('post_max_size'),
                'php_memory_limit'        => (string) ini_get('memory_limit'),
                'php_max_execution_time'  => (string) ini_get('max_execution_time'),
                'php_max_input_time'      => (string) ini_get('max_input_time'),
                'php_sapi'                => PHP_SAPI,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        $request->validate([
            'settings'   => ['nullable', 'array'],
            'settings.*' => ['nullable', 'string', 'max:10000'],
            // Gönderim limitleri sayı olmak zorunda: metin girilirse tavan
            // sıfıra düşer ve kampanyalar sessizce durur.
            'settings.mail_hourly_limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'settings.mail_batch_max'    => ['nullable', 'integer', 'min:0', 'max:100000'],
            'settings.mail_max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'files'      => ['nullable', 'array'],
            'files.*'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg,ico', 'max:1024'],
        ], [
            'settings.mail_hourly_limit.*' => 'Saatlik mail limiti 0 ile 100000 arasında bir sayı olmalı.',
            'settings.mail_batch_max.*'    => 'Tur başına mail sayısı 0 ile 100000 arasında bir sayı olmalı.',
            'settings.mail_max_attempts.*' => 'Yeniden deneme sayısı 1 ile 10 arasında olmalı.',
        ]);

        DB::transaction(function () use ($request): void {
            $settings = $request->input('settings', []);

            $mailKeys = [
                'mail_host', 'mail_port', 'mail_username', 'mail_password',
                'mail_encryption', 'mail_from_address', 'mail_from_name',
                'mail_mailer', 'mail_mode', 'mail_test_addresses',
                'mail_theme_primary_color', 'mail_theme_primary_dark_color',
                'mail_theme_bg_color', 'mail_theme_card_bg_color',
                'mail_theme_text_color', 'mail_theme_muted_color',
                'mail_theme_footer_text', 'mail_theme_social_links',
                // Toplu gönderim hızının tavanı; kampanya başına değil, gönderen
                // hesabın kendi kotası olduğu için buradan yönetiliyor.
                'mail_hourly_limit', 'mail_batch_max', 'mail_max_attempts',
            ];

            $recaptchaKeys = ['recaptcha_enabled', 'recaptcha_site_key', 'recaptcha_secret_key'];
            $telegramKeys = [
                'telegram_enabled', 'telegram_bot_token', 'telegram_chat_id',
                'telegram_notify_level',
            ];
            $maintenanceKeys = ['maintenance_mode', 'maintenance_message', 'maintenance_allowed_ips'];
            // Language is not a setting: the default lives on the languages table.
            $regionalKeys = ['app_timezone'];

            // Skip empty password-type fields (keep existing value)
            $passwordKeys = [
                'mail_password', 'recaptcha_secret_key', 'telegram_bot_token',
            ];
            foreach ($passwordKeys as $pwKey) {
                if (array_key_exists($pwKey, $settings) && empty($settings[$pwKey])) {
                    unset($settings[$pwKey]);
                }
            }

            // Handle mail logo removal
            if (($settings['mail_logo_remove'] ?? '0') === '1') {
                $logoValue = Setting::where('key', 'mail_logo')->value('value');
                if ($logoValue) {
                    $this->uploadService->deleteImage($logoValue);
                    Setting::where('key', 'mail_logo')->update(['value' => null]);
                }
            }
            unset($settings['mail_logo_remove']);

            foreach ($settings as $key => $value) {
                if (in_array($key, $mailKeys, true)) {
                    Setting::updateOrCreate(
                        ['key' => $key],
                        ['value' => $value, 'group' => 'mail', 'type' => $key === 'mail_password' ? 'password' : 'text'],
                    );
                } elseif (in_array($key, $recaptchaKeys, true)) {
                    Setting::updateOrCreate(
                        ['key' => $key],
                        ['value' => $value, 'group' => 'recaptcha', 'type' => $key === 'recaptcha_secret_key' ? 'password' : 'text'],
                    );
                } elseif (in_array($key, $telegramKeys, true)) {
                    $telegramType = match ($key) {
                        'telegram_bot_token' => 'password',
                        'telegram_enabled'   => 'boolean',
                        default              => 'text',
                    };
                    Setting::updateOrCreate(
                        ['key' => $key],
                        ['value' => $value, 'group' => 'telegram', 'type' => $telegramType],
                    );
                } elseif (in_array($key, $maintenanceKeys, true)) {
                    Setting::updateOrCreate(
                        ['key' => $key],
                        ['value' => $value, 'group' => 'maintenance', 'type' => 'text'],
                    );
                } elseif (in_array($key, $regionalKeys, true)) {
                    Setting::updateOrCreate(
                        ['key' => $key],
                        ['value' => $value, 'group' => 'regional', 'type' => 'text'],
                    );
                } else {
                    Setting::updateOrCreate(
                        ['key' => $key],
                        ['value' => $value, 'group' => 'general', 'type' => 'text'],
                    );
                }
            }

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $key => $file) {
                    $setting = Setting::where('key', $key)->first();
                    $isMailFile = str_starts_with($key, 'mail_');

                    [$maxWidth, $maxHeight, $sizes, $preserveFormat] = match ($key) {
                        'mail_logo' => [400, 400, [], true],
                        'site_logo' => [400, 400, [], false],
                        'site_favicon' => [64, 64, [], false],
                        default => [null, null, null, false],
                    };

                    $path = $this->uploadService->replaceImage(
                        $file,
                        'settings',
                        $key,
                        $setting?->value,
                        $sizes,
                        $maxWidth,
                        $maxHeight,
                        $preserveFormat,
                    );

                    if ($setting) {
                        $setting->update(['value' => $path]);
                    } else {
                        Setting::create([
                            'key'   => $key,
                            'value' => $path,
                            'group' => $isMailFile ? 'mail' : 'general',
                            'type'  => 'image',
                        ]);
                    }
                }
            }
        });

        $this->settingService->clearCache();

        return redirect()
            ->back()
            ->with('success', 'Ayarlar başarıyla güncellendi.');
    }

    public function clearCache(): JsonResponse
    {
        $this->authorize('update', Setting::class);

        try {
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');

            return response()->json([
                'success' => true,
                'message' => 'Tüm önbellekler başarıyla temizlendi.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Önbellek temizlenemedi: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function testTelegram(): JsonResponse
    {
        $this->authorize('update', Setting::class);

        try {
            $result = \App\Services\TelegramNotifier::testConnection();

            return response()->json([
                'success' => $result['ok'],
                'message' => $result['ok']
                    ? '✓ Telegram bağlantısı başarılı: ' . $result['message']
                    : 'Telegram bağlantı hatası: ' . $result['message'],
            ], $result['ok'] ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Telegram bağlantı hatası: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function testEmail(Request $request): JsonResponse
    {
        $this->authorize('update', Setting::class);

        $request->validate([
            'email'   => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:500'],
            'message' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            $siteName = Setting::getValue('site_name', config('app.name'));

            $subject = $request->input('subject', $siteName . ' — E-posta Bilgilendirmesi');
            $body = $request->input('message',
                'Merhaba, bu e-posta ' . $siteName . ' platformu üzerinden gönderilmiştir. '
                . 'E-posta yapılandırmanız başarıyla tamamlanmıştır. '
                . 'Herhangi bir sorunuz olursa bizimle iletişime geçebilirsiniz.',
            );

            $this->mailService->purgeMailer();
            $this->mailService->send(
                $request->input('email'),
                new \App\Mail\TestMail($subject, $body),
            );

            return response()->json([
                'success' => true,
                'message' => 'Test e-postası başarıyla gönderildi.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'E-posta gönderilemedi: ' . $e->getMessage(),
            ], 422);
        }
    }
}
