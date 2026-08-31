<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BlogCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Services\BlogCategoryService;
use Illuminate\Http\JsonResponse;

final class BlogCategoryController extends Controller
{
    public function __construct(
        private readonly BlogCategoryService $categories,
    ) {}

    /**
     * GET /api/v1/blog/categories
     *
     * Sayfalanmıyor: kategori sayısı doğası gereği küçük ve istemci süzgeç
     * çubuğunu tek seferde kurmak istiyor.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            BlogCategoryResource::collection($this->categories->allActive()),
        );
    }
}
