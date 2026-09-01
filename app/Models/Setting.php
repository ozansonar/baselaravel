<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CacheKeys;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key',
        'locale',
        'value',
        'group',
        'type',
    ];

    // ── Scopes ──

    // ── Static Helpers ──

    /**
     * Bütün ayarlar, anahtar ve dile göre: [anahtar][dil|''] => değer.
     *
     * Diller tek bir önbellek girdisinde duruyor, dil başına ayrı girdide
     * değil: tablo küçük ve çözümleme PHP tarafında bir dizi okuması. Dil
     * başına önbellek, dil sayısı kadar sorgu ve dil sayısı kadar
     * geçersizleştirme demek olurdu.
     *
     * @var array<string, array<string, string|null>>|null
     */
    private static ?array $cachedSettings = null;

    /**
     * Bir ayarın bu dildeki değeri.
     *
     * Önce isteğin dili, sonra "bütün diller" satırı (locale = null). Ayarların
     * çoğunun yalnız o satırı var; yalnız TranslatableSettings listesindekiler
     * dile ait ikinci bir satır taşıyabiliyor.
     *
     * @param string|null $locale null ise isteğin dili
     */
    public static function getValue(string $key, ?string $default = null, ?string $locale = null): ?string
    {
        $all = static::allByLocale();

        if (! isset($all[$key])) {
            return $default;
        }

        $locale ??= app()->getLocale();
        $row = $all[$key];

        // Boş bir çeviri "çevrilmedi" demek: yönetici alanı boş bıraktığında
        // ziyaretçi boş bir alt bilgi değil, asıl değeri görmeli.
        if (isset($row[$locale]) && $row[$locale] !== null && $row[$locale] !== '') {
            return $row[$locale];
        }

        return $row[''] ?? $default;
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    public static function allByLocale(): array
    {
        if (static::$cachedSettings !== null) {
            return static::$cachedSettings;
        }

        /** @var array<string, array<string, string|null>> $all */
        $all = Cache::remember(CacheKeys::SETTINGS_ALL, 86400, function (): array {
            $map = [];

            foreach (static::query()->get(['key', 'locale', 'value']) as $setting) {
                $map[$setting->key][(string) $setting->locale] = $setting->value;
            }

            return $map;
        });

        return static::$cachedSettings = $all;
    }

    public static function setValue(string $key, ?string $value, string $group = 'general', string $type = 'text', ?string $locale = null): void
    {
        static::updateOrCreate(
            ['key' => $key, 'locale' => $locale],
            ['value' => $value, 'group' => $group, 'type' => $type],
        );

        static::clearSettingsCache();
    }

    /**
     * Düz "anahtar => değer" görünümü — isteğin dilinde çözülmüş hâliyle.
     *
     * @return array<string, string|null>
     */
    public static function getCachedSettings(): array
    {
        $locale = app()->getLocale();
        $flat = [];

        foreach (static::allByLocale() as $key => $byLocale) {
            $flat[$key] = ($byLocale[$locale] ?? '') !== '' && ($byLocale[$locale] ?? null) !== null
                ? $byLocale[$locale]
                : ($byLocale[''] ?? null);
        }

        return $flat;
    }

    public static function clearSettingsCache(): void
    {
        static::$cachedSettings = null;
        Cache::forget(CacheKeys::SETTINGS_ALL);

        // API'nin dışarı açtığı süzülmüş liste ayrı anahtarlarda duruyor
        // (grup ve tip bilgisi gerektiği için) ve dil başına bir girdi var.
        // Burada düşürülmezse panelden değişen bir ayar mobil tarafta bir saat
        // daha eskisiyle görünürdü.
        app(\App\Services\CachePurger::class)
            ->forgetPrefix(\App\Services\SettingService::PUBLIC_CACHE_PREFIX);

        // Çizilmiş parçalar da ayarlardan besleniyor (site adı, iletişim
        // bilgileri, alt bilgi metni); ayar değişince onlar da bayatlıyor.
        app(\App\Services\CachePurger::class)->forgetPrefix(CacheKeys::PREFIX_FRAGMENT);
    }
}
