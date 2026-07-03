<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Menu;
use Illuminate\Support\Facades\Cache;

final class MenuService
{
    private const CACHE_TTL = 3600;

    public function getByLocation(string $location): ?Menu
    {
        return Cache::remember(
            $this->cacheKey($location),
            self::CACHE_TTL,
            fn () => Menu::active()
                ->byLocation($location)
                ->with(['rootItems' => function ($query) {
                    $query->where('is_active', true)->with('activeChildren');
                }])
                ->first()
        );
    }

    public function clearCache(string $location): void
    {
        Cache::forget($this->cacheKey($location));
    }

    public function clearAllCaches(): void
    {
        foreach (['header', 'footer', 'custom'] as $location) {
            $this->clearCache($location);
        }
    }

    private function cacheKey(string $location): string
    {
        return "menu.{$location}";
    }
}
