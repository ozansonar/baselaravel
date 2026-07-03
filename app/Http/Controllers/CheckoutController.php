<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly CartService $cartService,
    ) {}

    /**
     * Show checkout page.
     */
    public function index(): View|RedirectResponse
    {
        $isGuest = !auth()->check();

        if ($isGuest && Setting::getValue('guest_checkout_enabled', '1') !== '1') {
            return redirect()->route('login')
                ->with('warning', 'Sipariş vermek için giriş yapmanız gerekmektedir.');
        }

        if ($this->cartService->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('warning', 'Sepetiniz boş. Sipariş vermek için ürün ekleyin.');
        }

        $validation = $this->checkoutService->validateCart();

        if (!$validation['valid']) {
            return redirect()->route('cart.index')
                ->with('warning', 'Sepetinizdeki bazı ürünler artık mevcut değil.');
        }

        $items = $validation['items'];
        $subtotal = array_sum(array_column($items, 'total'));

        $minOrder = (float) Setting::getValue('min_order_amount', '100');
        if ($subtotal < $minOrder) {
            return redirect()->route('cart.index')
                ->with('warning', 'Minimum sipariş tutarı ' . number_format($minOrder, 0, ',', '.') . '₺\'dir.');
        }

        $totals = $this->checkoutService->calculateTotals($subtotal);

        $addresses = collect();
        $defaultAddress = null;

        if (!$isGuest) {
            /** @var User $user */
            $user = auth()->user();
            $addresses = $user->addresses()->orderByDesc('is_default')->orderByDesc('created_at')->get();
            $defaultAddress = $user->defaultAddress();
        }

        $cities = LocationService::getCities();
        $shippingConfig = $this->checkoutService->getShippingConfig();

        return view('checkout.index', compact('items', 'subtotal', 'addresses', 'defaultAddress', 'totals', 'isGuest', 'cities', 'shippingConfig'));
    }

    /**
     * Apply coupon code (AJAX).
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50'],
        ]);

        $subtotal = $this->cartService->subtotal();
        $result = $this->checkoutService->applyCoupon($request->string('code')->value(), $subtotal);

        if ($result['valid']) {
            $totals = $this->checkoutService->calculateTotals($subtotal, $result['discount']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'discount' => number_format($result['discount'], 2, ',', '.'),
                'shipping' => number_format($totals['shipping'], 2, ',', '.'),
                'shipping_raw' => $totals['shipping'],
                'total' => number_format($totals['total'], 2, ',', '.'),
                'code' => $result['code'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ], 422);
    }

    /**
     * Place order.
     */
    public function store(CheckoutRequest $request): RedirectResponse
    {
        $isGuest = !auth()->check();

        if ($isGuest && Setting::getValue('guest_checkout_enabled', '1') !== '1') {
            return redirect()->route('login')
                ->with('warning', 'Sipariş vermek için giriş yapmanız gerekmektedir.');
        }

        if ($this->cartService->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('warning', 'Sepetiniz boş.');
        }

        $validated = $request->validated();
        $user = auth()->user();
        $guestEmail = $validated['guest_email'] ?? null;
        $guestName = $validated['guest_name'] ?? null;

        try {
            $order = $this->checkoutService->placeOrder(
                $user,
                $validated,
                $guestEmail,
                $guestName,
            );

            if ($isGuest) {
                session()->put('guest_order_number', $order->order_number);
            }

            return redirect()->route('checkout.success', $order->order_number)
                ->with('success', 'Siparişiniz başarıyla oluşturuldu!');
        } catch (\RuntimeException $e) {
            return redirect()->route('cart.index')
                ->with('danger', 'Sipariş oluşturulurken bir hata oluştu: ' . $e->getMessage());
        }
    }

    /**
     * Show order success page.
     */
    public function success(string $orderNumber): View|RedirectResponse
    {
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            $order = $user->orders()
                ->where('order_number', $orderNumber)
                ->with('items')
                ->firstOrFail();
        } else {
            $sessionOrderNumber = session()->get('guest_order_number');

            if ($sessionOrderNumber !== $orderNumber) {
                return redirect()->route('home')
                    ->with('warning', 'Sipariş bulunamadı.');
            }

            $order = \App\Models\Order::where('order_number', $orderNumber)
                ->whereNull('user_id')
                ->with('items')
                ->firstOrFail();

            session()->forget('guest_order_number');
        }

        $isGuest = $order->isGuest();

        return view('checkout.success', compact('order', 'isGuest'));
    }
}
