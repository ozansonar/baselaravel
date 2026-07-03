<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AiReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AiReviewController extends Controller
{
    public function __construct(
        private readonly AiReviewService $aiReviewService,
    ) {}

    public function create(): View
    {
        $this->authorize('viewAny', \App\Models\ProductReview::class);

        $products = Product::active()
            ->orderBy('name')
            ->get(['id', 'name', 'category_id', 'created_at'])
            ->load('category:id,name');

        return view('admin.reviews.ai-generate', [
            'products' => $products,
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\ProductReview::class);

        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'count'      => ['required', 'integer', 'min:1', 'max:20'],
        ], [
            'product_id.required' => 'Lütfen bir ürün seçin.',
            'product_id.exists'   => 'Seçilen ürün bulunamadı.',
            'count.required'      => 'Yorum sayısı zorunludur.',
            'count.min'           => 'En az 1 yorum üretilebilir.',
            'count.max'           => 'En fazla 20 yorum üretilebilir.',
        ]);

        $result = $this->aiReviewService->generate(
            (int) $request->input('product_id'),
            (int) $request->input('count'),
        );

        $reviews = [];
        foreach ($result['reviews'] as $review) {
            $reviews[] = [
                'id'         => $review->id,
                'name'       => $review->name,
                'rating'     => $review->rating,
                'comment'    => $review->comment,
                'status'     => $review->status->value,
                'status_label' => $review->status->label(),
                'status_color' => $review->status->color(),
                'created_at' => $review->created_at->diffForHumans(),
                'approve_url' => route('admin.reviews.approve', $review->id),
                'reject_url'  => route('admin.reviews.reject', $review->id),
                'delete_url'  => route('admin.reviews.destroy', $review->id),
            ];
        }

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'reviews' => $reviews,
        ], $result['success'] ? 200 : 422);
    }
}
