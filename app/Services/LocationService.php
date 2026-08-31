<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

final class LocationService
{
    private const CACHE_KEY = 'turkey_cities_data';
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * @return array<int, array{name: string, districts: array<int, string>}>
     */
    public static function getAllData(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, static function (): array {
            $path = database_path('data/turkey_cities.json');
            $json = file_get_contents($path);

            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        });
    }

    /**
     * @return array<int, string>
     */
    public static function getCities(): array
    {
        return array_map(
            static fn (array $city): string => $city['name'],
            self::getAllData()
        );
    }

    /**
     * @return array<int, string>
     */
    public static function getDistricts(string $cityName): array
    {
        $data = self::getAllData();

        foreach ($data as $city) {
            if ($city['name'] === $cityName) {
                return $city['districts'];
            }
        }

        return [];
    }

    public static function isValidCity(string $cityName): bool
    {
        return in_array($cityName, self::getCities(), true);
    }

    public static function isValidDistrict(string $cityName, string $districtName): bool
    {
        return in_array($districtName, self::getDistricts($cityName), true);
    }
}
