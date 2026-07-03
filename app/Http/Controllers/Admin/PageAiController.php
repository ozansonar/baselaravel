<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageAiFillRequest;
use App\Services\PageAiService;
use Illuminate\Http\JsonResponse;

final class PageAiController extends Controller
{
    public function __construct(
        private readonly PageAiService $pageAiService,
    ) {}

    public function fill(PageAiFillRequest $request): JsonResponse
    {
        @set_time_limit(300);

        $result = $this->pageAiService->generate(
            (string) $request->input('title'),
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
