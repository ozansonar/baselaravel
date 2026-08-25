<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Services\LanguageService;
use Illuminate\Support\Facades\Cache;

/**
 * Front-end content is cached per language.
 *
 * Without the language in the key, the first visitor's language would be served
 * to everyone until the cache expired.
 */
trait LocalizedCache
{
    protected function localeCacheKey(string $key, ?string $locale = null): string
    {
        return $key . '.' . ($locale ?? app()->getLocale());
    }

    /**
     * Drop a cached key for every language, used after content changes.
     */
    protected function forgetLocalized(string $key): void
    {
        Cache::forget($key);

        foreach (app(LanguageService::class)->activeCodes() as $locale) {
            Cache::forget($this->localeCacheKey($key, $locale));
        }
    }
}
