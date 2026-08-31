<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LanguageResource;
use App\Http\Responses\ApiResponse;
use App\Services\LanguageService;
use Illuminate\Http\JsonResponse;

/**
 * Sitenin yayında olan dilleri.
 *
 * Mobil uygulamanın dil menüsü buradan doluyor: hangi kodları
 * `Accept-Language` / `?lang=` ile gönderebileceğini başka türlü bilemez.
 */
final class LanguageController extends Controller
{
    public function __construct(
        private readonly LanguageService $languages,
    ) {}

    /**
     * GET /api/v1/languages
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            LanguageResource::collection($this->languages->active()),
            extra: ['meta' => [
                'current' => app()->getLocale(),
                'default' => $this->languages->defaultCode(),
            ]],
        );
    }
}
