<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductReviewRequest;
use App\Services\ProductReviewService;
use Illuminate\Http\JsonResponse;

final class ProductReviewController extends Controller
{
    public function __construct(
        private readonly ProductReviewService $reviewService,
    ) {}

    public function store(StoreProductReviewRequest $request): JsonResponse
    {
        $this->reviewService->store($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Değerlendirmeniz başarıyla gönderildi. Yönetici onayından sonra yayınlanacaktır.',
        ]);
    }
}
