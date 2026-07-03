<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CityAiFillRequest;
use App\Services\AiCityContentService;
use Illuminate\Http\JsonResponse;

final class CityAiController extends Controller
{
    public function __construct(
        private readonly AiCityContentService $aiCityContentService,
    ) {}

    public function fill(CityAiFillRequest $request): JsonResponse
    {
        @set_time_limit(300);

        $result = $this->aiCityContentService->generate(
            (string) $request->input('city_name'),
            $request->input('region'),
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result['data'] ?? [],
        ]);
    }
}
