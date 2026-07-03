<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Services\ProductReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ReviewController extends Controller
{
    public function __construct(
        private readonly ProductReviewService $reviewService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductReview::class);

        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only(['status', 'rating', 'search']);

        return view('admin.reviews.index', [
            'reviews'      => $this->reviewService->paginate($perPage, $filters),
            'stats'        => $this->reviewService->getAdminStats(),
            'statusCounts' => $this->reviewService->statusCounts(),
            'pendingCount' => $this->reviewService->pendingCount(),
            'perPage'      => $perPage,
        ]);
    }

    public function show(ProductReview $review): View
    {
        $this->authorize('view', $review);

        $review->load('product');

        return view('admin.reviews.show', [
            'review' => $review,
        ]);
    }

    public function update(Request $request, ProductReview $review): JsonResponse|RedirectResponse
    {
        $this->authorize('approve', $review);

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'rating'     => ['required', 'integer', 'min:1', 'max:5'],
            'comment'    => ['required', 'string', 'min:5', 'max:2000'],
            'created_at' => ['required', 'date'],
        ], [
            'name.required'       => 'İsim zorunludur.',
            'comment.required'    => 'Yorum metni zorunludur.',
            'rating.required'     => 'Puan zorunludur.',
            'created_at.required' => 'Tarih zorunludur.',
        ]);

        $this->reviewService->update($review, $validated);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Değerlendirme güncellendi.']);
        }

        return redirect()->route('admin.reviews.show', $review)->with('success', 'Değerlendirme güncellendi.');
    }

    public function approve(Request $request, ProductReview $review): JsonResponse|RedirectResponse
    {
        $this->authorize('approve', $review);

        $this->reviewService->approve($review);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Değerlendirme onaylandı.']);
        }

        return redirect()->route('admin.reviews.index')->with('success', 'Değerlendirme onaylandı.');
    }

    public function reject(Request $request, ProductReview $review): JsonResponse|RedirectResponse
    {
        $this->authorize('approve', $review);

        $this->reviewService->reject($review);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Değerlendirme reddedildi.']);
        }

        return redirect()->route('admin.reviews.index')->with('success', 'Değerlendirme reddedildi.');
    }

    public function destroy(Request $request, ProductReview $review): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $review);

        $this->reviewService->delete($review);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Değerlendirme silindi.']);
        }

        return redirect()->route('admin.reviews.index')->with('success', 'Değerlendirme silindi.');
    }

    public function restore(ProductReview $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $this->reviewService->restore($review);

        return redirect()->route('admin.reviews.index')->with('success', 'Değerlendirme geri yüklendi.');
    }
}
