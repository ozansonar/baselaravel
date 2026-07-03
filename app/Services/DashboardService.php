<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class DashboardService
{
    /**
     * @return array<string, int|string>
     */
    public function getStats(): array
    {
        return Cache::remember('admin.dashboard.stats', 300, function (): array {
            $orderStats = Order::query()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('COALESCE(SUM(CASE WHEN status != ? THEN total ELSE 0 END), 0) as revenue', [OrderStatus::Cancelled->value])
                ->first();

            return [
                'total_orders'    => (int) $orderStats->total,
                'total_revenue'   => $orderStats->revenue,
                'total_products'  => Product::count(),
                'pending_reviews' => ProductReview::where('status', 'pending')->count(),
                'unread_messages' => ContactMessage::unread()->count(),
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function orderStatusCounts(): array
    {
        return Cache::remember('admin.dashboard.order_status', 300, function (): array {
            $counts = Order::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            return [
                'pending'    => $counts[OrderStatus::Pending->value] ?? 0,
                'processing' => $counts[OrderStatus::Processing->value] ?? 0,
                'shipped'    => $counts[OrderStatus::Shipped->value] ?? 0,
                'delivered'  => $counts[OrderStatus::Delivered->value] ?? 0,
                'cancelled'  => $counts[OrderStatus::Cancelled->value] ?? 0,
            ];
        });
    }

    /**
     * @return Collection<int, Order>
     */
    public function recentOrders(int $limit = 5): Collection
    {
        return Order::with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    public function featuredProducts(int $limit = 5): Collection
    {
        return Product::active()
            ->featured()
            ->sorted()
            ->with(['category'])
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, ProductReview>
     */
    public function recentReviews(int $limit = 5): Collection
    {
        return ProductReview::with('product')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, ContactMessage>
     */
    public function recentMessages(int $limit = 5): Collection
    {
        return ContactMessage::latest()
            ->limit($limit)
            ->get();
    }
}
