<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\TranslatableSettings;
use App\Services\MailService;
use App\Services\LanguageService;
use App\Services\SettingService;
use App\Services\SystemStatusService;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly UploadService $uploadService,
        private readonly MailService $mailService,
        private readonly SystemStatusService $systemStatus,
        private readonly LanguageService $languages,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Setting::class);

        // Yalnız "bütün diller" satırları: formun asıl alanları bunlar.
        // Dile ait satırlar aynı anahtarı taşıyor, birlikte alınsalardı
        // keyBy() hangisinin kalacağına sıralamaya bakarak karar verirdi.
        $allSettings = Setting::query()->whereNull('locale')->get()->keyBy('key');

        // Çeviriler: [anahtar][dil] => değer
        $translations = Setting::query()
            ->whereNotNull('locale')
            ->get()
            ->groupBy('key')
            ->map(fn ($rows) => $rows->pluck('value', 'locale')->all())
            ->all();

        // Sistem durumunun hesabı ekranda değil serviste: ekran yalnızca
        // sonucu çiziyor.
        $system = $this->systemStatus->snapshot();

        return view('admin.settings.index', [
            'settings'     => $allSettings,
            'translations' => $translations,
            // Çeviri alanı çizilecek diller: varsayılan dışındaki etkin
            // diller. Varsayılanın kendi kutusu zaten asıl alan.
            'otherLanguages' => $this->languages->active()
                ->reject(fn ($language): bool => (bool) $language->is_default)
                ->values(),
            'translatableKeys' => TranslatableSettings::keys(),
            'systemInfo' => $system,
            'verdict'    => $this->systemStatus->verdict(
                $system['limits'],
                $system['db']['connected'],
                $system['debug'] && $system['environment'] === 'production',
                $system['storage_writable'],
            ),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        $request->validate([
            'settings'   => ['nullable', 'array'],
            'settings.*' => ['nullable', 'string', 'max:10000'],
            'settings_translations'     => ['nullable', 'array'],
            'settings_translations.*'   => ['nullable', 'array'],
            'settings_translations.*.*' => ['nullable', 'string', 'max:10000'],
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
                // Görseller çevrilmiyor: her zaman "bütün diller" satırında.
                $logoValue = Setting::whereNull('locale')->where('key', 'mail_logo')->value('value');
                if ($logoValue) {
                    $this->uploadService->deleteImage($logoValue);
                    Setting::whereNull('locale')->where('key', 'mail_logo')->update(['value' => null]);
                }
            }
            unset($settings['mail_logo_remove']);

            foreach ($settings as $key => $value) {
                if (in_array($key, $mailKeys, true)) {
                    Setting::updateOrCreate(
                        ['key' => $key, 'locale' => null],
                        ['value' => $value, 'group' => 'mail', 'type' => $key === 'mail_password' ? 'password' : 'text'],
                    );
                } elseif (in_array($key, $recaptchaKeys, true)) {
                    Setting::updateOrCreate(
                        ['key' => $key, 'locale' => null],
                        ['value' => $value, 'group' => 'recaptcha', 'type' => $key === 'recaptcha_secret_key' ? 'password' : 'text'],
                    );
                } elseif (in_array($key, $telegramKeys, true)) {
                    $telegramType = match ($key) {
                        'telegram_bot_token' => 'password',
                        'telegram_enabled'   => 'boolean',
                        default              => 'text',
                    };
                    Setting::updateOrCreate(
                        ['key' => $key, 'locale' => null],
                        ['value' => $value, 'group' => 'telegram', 'type' => $telegramType],
                    );
                } elseif (in_array($key, $maintenanceKeys, true)) {
                    Setting::updateOrCreate(
                        ['key' => $key, 'locale' => null],
                        ['value' => $value, 'group' => 'maintenance', 'type' => 'text'],
                    );
                } elseif (in_array($key, $regionalKeys, true)) {
                    Setting::updateOrCreate(
                        ['key' => $key, 'locale' => null],
                        ['value' => $value, 'group' => 'regional', 'type' => 'text'],
                    );
                } else {
                    Setting::updateOrCreate(
                        ['key' => $key, 'locale' => null],
                        ['value' => $value, 'group' => 'general', 'type' => 'text'],
                    );
                }
            }

            // Çeviriler ayrı bir alandan geliyor: settings_translations[dil][anahtar]
            //
            // Boş bırakılan çeviri satırı **siliniyor**, boş dizgeyle
            // kaydedilmiyor: "çevirmedim" ile "boş bıraktım" aynı şey değil ve
            // ikincisi ziyaretçiye boş bir alt bilgi gösterirdi.
            $translatable = TranslatableSettings::keys();
            $activeCodes = $this->languages->active()->pluck('code')->all();

            foreach ((array) $request->input('settings_translations', []) as $locale => $values) {
                if (! in_array($locale, $activeCodes, true)) {
                    continue;
                }

                foreach ((array) $values as $key => $value) {
                    if (! in_array($key, $translatable, true)) {
                        continue;
                    }

                    $value = is_string($value) ? trim($value) : null;

                    if ($value === null || $value === '') {
                        // forceDelete: yumuşak silinen satır (key, locale)
                        // benzersizlik yerini tutmaya devam ediyor. Yönetici
                        // çeviriyi silip geri eklemek istediğinde
                        // updateOrCreate silinmiş satırı göremez, INSERT dener
                        // ve benzersizlik ihlaline çarpardı. Silinmiş bir
                        // çevirinin saklanacak bir tarafı da yok: asıl değer
                        // duruyor.
                        Setting::query()->where('key', $key)->where('locale', $locale)->forceDelete();

                        continue;
                    }

                    Setting::updateOrCreate(
                        ['key' => $key, 'locale' => $locale],
                        [
                            // Grup ve tip asıl satırdan kopyalanıyor: çeviri
                            // yalnız değeri eziyor, ayarın kimliğini değil.
                            'value' => $value,
                            'group' => Setting::query()->whereNull('locale')->where('key', $key)->value('group') ?? 'general',
                            'type'  => 'text',
                        ],
                    );
                }
            }

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $key => $file) {
                    // Görseller çevrilmiyor: her zaman "bütün diller" satırı.
                    $setting = Setting::whereNull('locale')->where('key', $key)->first();
                    $isMailFile = str_starts_with($key, 'mail_');

                    [$maxWidth, $maxHeight, $sizes, $preserveFormat] = match ($key) {
                        'mail_logo' => [400, 400, [], true],
                        'site_logo' => [400, 400, [], false],
                        'site_favicon' => [64, 64, [], false],
                        // PWA ikonu PNG kalmalı ve büyük kalmalı: manifest'in
                        // ikonları bundan üretiliyor ve 512 pikselin altına
                        // düşerse Chrome kurulumu reddediyor.
                        'pwa_icon' => [1024, 1024, [], true],
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
                            'key'    => $key,
                            'locale' => null,
                            'value'  => $path,
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
