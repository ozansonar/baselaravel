<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

final class SettingService
{
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
     * Get settings by group.
     *
     * @return Collection<int, Setting>
     */
    public function byGroup(string $group): Collection
    {
        return Setting::byGroup($group)->get();
    }

    /**
     * Set a single setting value.
     */
    public function set(string $key, ?string $value, string $group = 'general', string $type = 'text'): void
    {
        Setting::setValue($key, $value, $group, $type);
    }

    /**
     * Bulk update settings.
     *
     * @param array<string, string|null> $data
     */
    public function bulkUpdate(array $data): void
    {
        foreach ($data as $key => $value) {
            Setting::where('key', $key)->update(['value' => $value]);
        }

        Setting::clearSettingsCache();
    }

    public function clearCache(): void
    {
        Setting::clearSettingsCache();
    }
}
