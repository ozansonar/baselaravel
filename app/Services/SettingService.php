<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

final class SettingService
{
    /**
     * API'nin yayınladığı ayarların önbellek anahtarı.
     *
     * Model tarafındaki `Setting::clearSettingsCache()` bunu da düşürüyor;
     * ayrı bırakılsaydı panelden değiştirilen bir ayar mobilde bir saat daha
     * eski hâliyle görünürdü.
     */
    public const PUBLIC_CACHE_KEY = 'api.settings.public';

    /**
     * Get a single setting value.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        return Setting::getValue($key, $default);
    }

    /**
     * Get all settings as key-value pairs.
     *
     * @return array<string, string|null>
     */
    public function all(): array
    {
        // Trigger static cache load via getValue, then return cached array
        Setting::getValue('__noop__');

        return Setting::getCachedSettings();
    }

    /**
     * Set a single setting value.
     */
    public function set(string $key, ?string $value, string $group = 'general', string $type = 'text'): void
    {
        Setting::setValue($key, $value, $group, $type);
    }

    /**
     * Dışarı açılabilen ayarlar, grubuna göre kümelenmiş.
     *
     * settings tablosu her şeyi bir arada tutuyor: site adının yanında SMTP
     * parolası, reCAPTCHA gizli anahtarı ve Telegram jetonu da orada. API'nin
     * tabloyu olduğu gibi basması bu yüzden söz konusu değil.
     *
     * Süzgeç üç katmanlı ve üçü de beyaz/kara liste olarak config/api.php'de:
     *
     *  1. yalnız izin verilen gruplar,
     *  2. o grupların içinden adı ayrıca elenenler düşer,
     *  3. tipi `password` olan ya da adında "secret/token/password/..." geçen
     *     hiçbir satır —grubu ne olursa olsun— çıkmaz.
     *
     * Üçüncü katman fazlalık gibi duruyor ama asıl koruma o: yarın 'general'
     * grubuna bir gizli anahtar eklendiğinde bu ucu kimse hatırlamayacak.
     *
     * @return array<string, array<string, string|null>>
     */
    public function publicValues(): array
    {
        return Cache::remember(self::PUBLIC_CACHE_KEY, 3600, function (): array {
            /** @var array<int, string> $groups */
            $groups = (array) config('api.public_settings.groups', []);
            /** @var array<int, string> $except */
            $except = (array) config('api.public_settings.except', []);
            /** @var array<int, string> $forbidden */
            $forbidden = (array) config('api.public_settings.forbidden_patterns', []);

            if ($groups === []) {
                return [];
            }

            return Setting::query()
                ->whereIn('group', $groups)
                ->where('type', '!=', SettingType::Password->value)
                ->orderBy('group')
                ->orderBy('key')
                ->get()
                ->reject(fn (Setting $setting): bool => in_array($setting->key, $except, true))
                ->reject(fn (Setting $setting): bool => array_any(
                    $forbidden,
                    fn (string $pattern): bool => str_contains(strtolower($setting->key), $pattern),
                ))
                ->groupBy('group')
                ->map(fn (Collection $rows): array => $rows
                    ->mapWithKeys(fn (Setting $setting): array => [
                        $setting->key => $this->publicValue($setting),
                    ])
                    ->all())
                ->all();
        });
    }

    /**
     * Görsel ayarları yol değil adres olarak çıkıyor.
     *
     * Değer veritabanında "brand/logo-ab12.webp" gibi duruyor; sayfanın kendisi
     * aynı alan adında olduğu için ön yüzde bu yeterli. Mobil uygulamada bir
     * "sayfa" yok — göreli yol hiçbir şeye çözülmez.
     */
    private function publicValue(Setting $setting): ?string
    {
        if ($setting->type !== SettingType::Image->value || $setting->value === null || $setting->value === '') {
            return $setting->value;
        }

        return url(upload_url($setting->value));
    }

    public function clearCache(): void
    {
        Setting::clearSettingsCache();
    }
}
