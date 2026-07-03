<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only(['status', 'search', 'date_filter', 'amount_filter']);

        return view('admin.orders.index', [
            'orders'       => $this->orderService->paginate($perPage, $filters),
            'statusCounts' => $this->orderService->statusCounts(),
            'stats'        => $this->orderService->getAdminStats(),
            'perPage'      => $perPage,
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        $order = $this->orderService->findById($order->id);

        return view('admin.orders.show', [
            'order'         => $order,
            'orderStatuses' => OrderStatus::cases(),
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('updateStatus', $order);

        $status = OrderStatus::from($request->validated('status'));
        $this->orderService->updateStatus($order, $status);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Sipariş durumu güncellendi.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        $this->authorize('delete', $order);

        $this->orderService->delete($order);

        return redirect()
            ->route('admin.orders.index')
            ->with('success', 'Sipariş silindi.');
    }
}
