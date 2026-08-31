<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\GalleryCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Services\GalleryCategoryService;
use Illuminate\Http\JsonResponse;

final class GalleryCategoryController extends Controller
{
    public function __construct(
        private readonly GalleryCategoryService $categories,
    ) {}

    /**
     * GET /api/v1/gallery/categories
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            GalleryCategoryResource::collection($this->categories->allActive()),
        );
    }
}
