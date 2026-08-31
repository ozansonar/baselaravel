<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SliderResource;
use App\Http\Responses\ApiResponse;
use App\Services\SliderService;
use Illuminate\Http\JsonResponse;

final class SliderController extends Controller
{
    public function __construct(
        private readonly SliderService $sliders,
    ) {}

    /**
     * GET /api/v1/sliders
     *
     * Sayfalanmıyor: bir görsel şeridi doğası gereği birkaç kareden ibaret.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(SliderResource::collection($this->sliders->allActive()));
    }
}
