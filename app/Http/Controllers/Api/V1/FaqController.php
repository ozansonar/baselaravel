<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FaqResource;
use App\Http\Responses\ApiResponse;
use App\Services\FaqService;
use Illuminate\Http\JsonResponse;

final class FaqController extends Controller
{
    public function __construct(
        private readonly FaqService $faqs,
    ) {}

    /**
     * GET /api/v1/faqs
     *
     * Sayfalanmıyor: SSS ekranı akordeon olarak tek seferde çiziliyor.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(FaqResource::collection($this->faqs->allActive()));
    }
}
