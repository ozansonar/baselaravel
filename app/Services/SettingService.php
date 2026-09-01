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
    /**
     * Dil başına bir girdi.
     *
     * Değerler artık isteğin dilinde çözülüyor; tek bir anahtarda tutulsaydı
     * önbelleği ilk ısıtan dil bütün dillere servis edilirdi.
     */
    public const PUBLIC_CACHE_PREFIX = 'api.settings.public.';

    /**
     * Bir ayarın değeri — isteğin dilinde çözülmüş hâliyle.
     *
     * Ayarların çoğu dilden bağımsız; metin taşıyan bir avucu dile ait ikinci
     * bir satır tutabiliyor ve o varsa o kazanıyor.
     *
     * @see \App\Support\TranslatableSettings
     */
    public function get(string $key, ?string $default = null, ?string $locale = null): ?string
    {
        return Setting::getValue($key, $default, $locale);
    }

    /**
     * Bütün ayarlar, isteğin dilinde çözülmüş hâliyle.
     *
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return Setting::getCachedSettings();
    }

    /**
     * Set a single setting value.
     *
     * @param string|null $locale null → bütün diller; bir dil kodu → o dilin çevirisi
     */
    public function set(string $key, ?string $value, string $group = 'general', string $type = 'text', ?string $locale = null): void
    {
        Setting::setValue($key, $value, $group, $type, $locale);
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
        $locale = app()->getLocale();

        return Cache::remember(self::PUBLIC_CACHE_PREFIX . $locale, 3600, function () use ($locale): array {
            /** @var array<int, string> $groups */
            $groups = (array) config('api.public_settings.groups', []);
            /** @var array<int, string> $except */
            $except = (array) config('api.public_settings.except', []);
            /** @var array<int, string> $forbidden */
            $forbidden = (array) config('api.public_settings.forbidden_patterns', []);

            if ($groups === []) {
                return [];
            }

            // Yalnız "bütün diller" satırları listeleniyor: bir ayarın var
            // olup olmadığını, grubunu ve tipini onlar tanımlıyor. Dile ait
            // satır yalnız değeri eziyor ve o ezme publicValue() içinde
            // çözülüyor — burada listelenselerdi aynı anahtar iki kez çıkar,
            // hangisinin kazandığı sıralamaya kalırdı.
            return Setting::query()
                ->whereNull('locale')
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
                        $setting->key => $this->publicValue($setting, $locale),
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
    private function publicValue(Setting $setting, string $locale): ?string
    {
        // Değer dilin kendi satırından geliyorsa o kazanıyor; yoksa bu satırın
        // kendi değeri. Görsel ayarları çevrilmiyor ama aynı yoldan geçmeleri
        // zararsız.
        $value = Setting::getValue($setting->key, $setting->value, $locale);

        if ($setting->type !== SettingType::Image->value || $value === null || $value === '') {
            return $value;
        }

        return url(upload_url($value));
    }

    public function clearCache(): void
    {
        Setting::clearSettingsCache();
    }
}
