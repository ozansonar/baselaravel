<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class OrderService
{
    public function __construct(
        private readonly MailService $mailService,
    ) {}
    public function paginate(int $perPage = 15, ?array $filters = null): LengthAwarePaginator
    {
        $query = Order::with('user')->withCount('items');

        if ($filters) {
            if (isset($filters['status']) && $filters['status'] !== '') {
                $status = OrderStatus::tryFrom($filters['status']);
                if ($status) {
                    $query->byStatus($status);
                }
            }

            if (isset($filters['search']) && $filters['search'] !== '') {
                $query->where(function ($q) use ($filters): void {
                    $q->where('order_number', 'like', "%{$filters['search']}%")
                      ->orWhere('shipping_name', 'like', "%{$filters['search']}%")
                      ->orWhere('shipping_phone', 'like', "%{$filters['search']}%");
                });
            }

            if (isset($filters['date_filter']) && $filters['date_filter'] !== '') {
                $query = match ($filters['date_filter']) {
                    'today'   => $query->whereDate('created_at', today()),
                    'week'    => $query->where('created_at', '>=', now()->startOfWeek()),
                    'month'   => $query->where('created_at', '>=', now()->startOfMonth()),
                    'quarter' => $query->where('created_at', '>=', now()->subMonths(3)),
                    default   => $query,
                };
            }

            if (isset($filters['amount_filter']) && $filters['amount_filter'] !== '') {
                $query = match ($filters['amount_filter']) {
                    '0-500'      => $query->where('total', '<=', 500),
                    '500-2000'   => $query->where('total', '>', 500)->where('total', '<=', 2000),
                    '2000-10000' => $query->where('total', '>', 2000)->where('total', '<=', 10000),
                    '10000+'     => $query->where('total', '>', 10000),
                    default      => $query,
                };
            }
        }

        return $query->recent()->paginate($perPage);
    }

    public function paginateByUser(int $userId, int $perPage = 10): LengthAwarePaginator
    {
        return Order::where('user_id', $userId)
            ->with('items')
            ->recent()
            ->paginate($perPage);
    }

    public function findById(int $id): Order
    {
        return Order::with(['user', 'items.product'])->findOrFail($id);
    }

    public function findByOrderNumber(string $orderNumber): Order
    {
        return Order::where('order_number', $orderNumber)
            ->with(['user', 'items.product'])
            ->firstOrFail();
    }

    public function findByTracking(string $orderNumber, string $trackingToken): ?Order
    {
        return Order::where('order_number', $orderNumber)
            ->where('tracking_token', $trackingToken)
            ->with('items')
            ->first();
    }

    /**
     * @param array{
     *     user_id?: int,
     *     items: array<array{product_id: int, product_name: string, product_price: float, quantity: int, unit: string}>,
     *     shipping_name: string,
     *     shipping_phone: string,
     *     shipping_city: string,
     *     shipping_district: string,
     *     shipping_address: string,
     *     campaign_code?: string,
     *     notes?: string,
     * } $data
     */
    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $items = $data['items'];
            unset($data['items']);

            // Calculate totals
            $subtotal = 0;
            foreach ($items as &$item) {
                $item['total'] = round($item['product_price'] * $item['quantity'], 2);
                $subtotal += $item['total'];
            }
            unset($item);

            $data['order_number'] = Order::generateOrderNumber();
            $data['tracking_token'] = Order::generateTrackingToken();
            $data['status'] = OrderStatus::Pending;
            $data['subtotal'] = $subtotal;
            $data['total'] = $subtotal - ($data['discount_amount'] ?? 0) + ($data['shipping_cost'] ?? 0);
            $data['ip_address'] = request()->ip();

            $order = Order::create($data);

            // Bulk insert order items
            $orderItems = array_map(fn (array $item): array => [
                'order_id'      => $order->id,
                'product_id'    => $item['product_id'],
                'product_name'  => $item['product_name'],
                'product_price' => $item['product_price'],
                'quantity'      => $item['quantity'],
                'unit'          => $item['unit'] ?? 'adet',
                'total'         => $item['total'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ], $items);

            OrderItem::insert($orderItems);

            return $order->load('items');
        });
    }

    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        $order->update(['status' => $status]);
        $order->refresh();

        // Send status update email to order owner
        $order->loadMissing(['user', 'items']);
        $customerEmail = $order->getCustomerEmail();
        if ($customerEmail) {
            try {
                $this->mailService->queue($customerEmail, new OrderStatusUpdatedMail($order));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Sipariş durum güncelleme maili kuyruğa eklenemedi', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $order;
    }

    public function delete(Order $order): void
    {
        $order->delete();
    }

    public function statusCounts(): array
    {
        return Order::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * @return array{total_orders: int, total_revenue: string, pending_count: int, total_count: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.orders.stats', 300, function (): array {
            $counts = Order::query()
                ->selectRaw('COUNT(*) as total_orders')
                ->selectRaw('COALESCE(SUM(CASE WHEN status != ? THEN total ELSE 0 END), 0) as total_revenue', [OrderStatus::Cancelled->value])
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count', [OrderStatus::Pending->value])
                ->first();

            return [
                'total_orders'  => (int) $counts->total_orders,
                'total_revenue' => $counts->total_revenue,
                'pending_count' => (int) $counts->pending_count,
                'total_count'   => (int) $counts->total_orders,
            ];
        });
    }
}
