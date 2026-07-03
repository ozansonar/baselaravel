<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductAiFillRequest;
use App\Models\Category;
use App\Services\ProductAiService;
use Illuminate\Http\JsonResponse;

final class ProductAiController extends Controller
{
    public function __construct(
        private readonly ProductAiService $productAiService,
    ) {}

    public function fill(ProductAiFillRequest $request): JsonResponse
    {
        // Allow longer execution for AI call + retries
        @set_time_limit(300);

        $categoryName = null;
        $categoryId = $request->input('category_id');

        if ($categoryId !== null && $categoryId !== '') {
            $category = Category::find((int) $categoryId);
            $categoryName = $category?->name;
        }

        $result = $this->productAiService->generate(
            (string) $request->input('name'),
            $categoryName,
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
