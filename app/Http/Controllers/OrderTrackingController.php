<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\View\View;

final class OrderTrackingController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * Show order tracking page (public, no auth required).
     */
    public function show(string $orderNumber, string $trackingToken): View
    {
        $order = $this->orderService->findByTracking($orderNumber, $trackingToken);

        if (!$order) {
            abort(404);
        }

        return view('order.tracking', compact('order'));
    }
}
