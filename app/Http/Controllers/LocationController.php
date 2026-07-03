<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LocationService;
use Illuminate\Http\JsonResponse;

final class LocationController extends Controller
{
    public function cities(): JsonResponse
    {
        $cities = LocationService::getCities();

        return response()->json($cities);
    }

    public function districts(string $city): JsonResponse
    {
        if (!LocationService::isValidCity($city)) {
            return response()->json([], 404);
        }

        $districts = LocationService::getDistricts($city);

        return response()->json($districts);
    }
}
