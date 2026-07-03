<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Models\ProductReview;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ProductReviewService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = ProductReview::with('product')->withTrashed()->recent();

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'trashed') {
                $query->onlyTrashed();
            } else {
                $status = ReviewStatus::tryFrom($filters['status']);
                if ($status !== null) {
                    $query->whereNull('deleted_at')->where('status', $status);
                }
            }
        } else {
            $query->whereNull('deleted_at');
        }

        if (!empty($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ProductReview
    {
        return ProductReview::with('product')->findOrFail($id);
    }

    public function store(array $data): ProductReview
    {
        $data['status'] = ReviewStatus::Pending->value;
        $data['ip_address'] = request()->ip();

        return ProductReview::create($data);
    }

    public function update(ProductReview $review, array $data): void
    {
        if (isset($data['created_at'])) {
            $review->created_at = $data['created_at'];
            unset($data['created_at']);
        }

        $review->fill($data);
        $review->save();
    }

    public function approve(ProductReview $review): void
    {
        $review->update(['status' => ReviewStatus::Approved]);
    }

    public function reject(ProductReview $review): void
    {
        $review->update(['status' => ReviewStatus::Rejected]);
    }

    public function delete(ProductReview $review): void
    {
        $review->delete();
    }

    public function restore(ProductReview $review): void
    {
        $review->restore();
    }

    public function pendingCount(): int
    {
        return ProductReview::pending()->count();
    }

    /**
     * @return array<string, int>
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.reviews.stats', 300, function (): array {
            $counts = ProductReview::withTrashed()->selectRaw("
                SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN deleted_at IS NULL AND status = ? THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN deleted_at IS NULL AND status = ? THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN deleted_at IS NULL AND status = ? THEN 1 ELSE 0 END) as rejected
            ", [
                ReviewStatus::Approved->value,
                ReviewStatus::Pending->value,
                ReviewStatus::Rejected->value,
            ])->first();

            return [
                'total'    => (int) $counts->total,
                'approved' => (int) $counts->approved,
                'pending'  => (int) $counts->pending,
                'rejected' => (int) $counts->rejected,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = ProductReview::selectRaw("
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected
        ", [
            ReviewStatus::Approved->value,
            ReviewStatus::Pending->value,
            ReviewStatus::Rejected->value,
        ])->first();

        return [
            'approved' => (int) $counts->approved,
            'pending'  => (int) $counts->pending,
            'rejected' => (int) $counts->rejected,
            'trashed'  => ProductReview::onlyTrashed()->count(),
        ];
    }

    /**
     * @return array{average: float, total: int, distribution: array<int, int>}
     */
    public function getProductStats(int $productId): array
    {
        $distribution = ProductReview::where('product_id', $productId)
            ->approved()
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $total = (int) array_sum($distribution);
        $weightedSum = 0;
        $percentages = [];

        for ($i = 5; $i >= 1; $i--) {
            $count = $distribution[$i] ?? 0;
            $weightedSum += $i * $count;
            $percentages[$i] = $total > 0 ? round(($count / $total) * 100) : 0;
        }

        return [
            'average' => $total > 0 ? round($weightedSum / $total, 1) : 0.0,
            'total' => $total,
            'distribution' => $percentages,
        ];
    }

}
