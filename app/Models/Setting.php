<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
    ];

    // ── Scopes ──

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeByGroup(\Illuminate\Database\Eloquent\Builder $query, string $group): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('group', $group);
    }

    // ── Static Helpers ──

    /** @var array<string, string|null>|null */
    private static ?array $cachedSettings = null;

    public static function getValue(string $key, ?string $default = null): ?string
    {
        if (static::$cachedSettings === null) {
            /** @var array<string, string|null> $all */
            $all = Cache::remember('settings.all', 86400, fn () =>
                static::pluck('value', 'key')->toArray(),
            );
            static::$cachedSettings = $all;
        }

        return static::$cachedSettings[$key] ?? $default;
    }

    public static function setValue(string $key, ?string $value, string $group = 'general', string $type = 'text'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group, 'type' => $type],
        );

        static::clearSettingsCache();
    }

    /**
     * @return array<string, string|null>
     */
    public static function getCachedSettings(): array
    {
        if (static::$cachedSettings === null) {
            static::getValue('__noop__');
        }

        return static::$cachedSettings ?? [];
    }

    public static function clearSettingsCache(): void
    {
        static::$cachedSettings = null;
        Cache::forget('settings.all');
    }
}
