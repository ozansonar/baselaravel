<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogAiFillRequest;
use App\Models\BlogCategory;
use App\Services\BlogAiService;
use Illuminate\Http\JsonResponse;

final class BlogAiController extends Controller
{
    public function __construct(
        private readonly BlogAiService $blogAiService,
    ) {}

    public function fill(BlogAiFillRequest $request): JsonResponse
    {
        @set_time_limit(300);

        $categoryName = null;
        $categoryId = $request->input('blog_category_id');

        if ($categoryId !== null && $categoryId !== '') {
            $category = BlogCategory::find((int) $categoryId);
            $categoryName = $category?->name;
        }

        $result = $this->blogAiService->generate(
            (string) $request->input('title'),
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
