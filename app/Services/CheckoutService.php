<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\NewOrderAdminNotificationMail;
use App\Mail\OrderConfirmationMail;
use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationCenter;
use App\Services\TelegramNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderService $orderService,
        private readonly CampaignService $campaignService,
        private readonly MailService $mailService,
    ) {}

    /**
     * Validate cart items against current stock and prices.
     * Returns validated items with current data.
     *
     * @return array{valid: bool, items: array<int, array<string, mixed>>, errors: array<string>}
     */
    public function validateCart(): array
    {
        $cartItems = $this->cartService->getItems();
        $errors = [];

        if (empty($cartItems)) {
            return ['valid' => false, 'items' => [], 'errors' => ['Sepetiniz boş.']];
        }

        $productIds = array_keys($cartItems);
        $products = Product::whereIn('id', $productIds)
            ->active()
            ->select(['id', 'name', 'price', 'stock', 'unit', 'status'])
            ->get()
            ->keyBy('id');

        $validItems = [];

        foreach ($cartItems as $productId => $item) {
            $product = $products->get($productId);

            if (!$product) {
                $errors[] = "{$item['name']} artık mevcut değil.";
                continue;
            }

            if (!$product->inStock()) {
                $errors[] = "{$item['name']} stokta yok.";
                continue;
            }

            if ($item['quantity'] > $product->stock) {
                $errors[] = "{$item['name']} için stok yetersiz. Mevcut stok: {$product->stock}";
                $item['quantity'] = $product->stock;
            }

            $validItems[$productId] = [
                'product_id' => $productId,
                'product_name' => $product->name,
                'product_price' => (float) $product->currentPrice(),
                'quantity' => $item['quantity'],
                'unit' => $product->unit ?? 'Adet',
                'total' => round((float) $product->currentPrice() * $item['quantity'], 2),
            ];
        }

        return [
            'valid' => !empty($validItems),
            'items' => $validItems,
            'errors' => $errors,
        ];
    }

    /**
     * Apply coupon code and return discount info.
     *
     * @return array{valid: bool, message: string, discount: float, code: string}
     */
    public function applyCoupon(string $code, float $subtotal): array
    {
        $result = $this->campaignService->applyCoupon($code, $subtotal);

        return [
            'valid' => $result['valid'],
            'message' => $result['message'],
            'discount' => $result['discount'],
            'code' => $code,
        ];
    }

    /**
     * Calculate order totals with dynamic shipping from settings.
     *
     * @return array{subtotal: float, discount: float, shipping: float, total: float}
     */
    public function calculateTotals(float $subtotal, float $discount = 0.0): array
    {
        $freeLimit = (float) Setting::getValue('shipping_free_limit', '500');
        $shippingFee = (float) Setting::getValue('shipping_fee', '39.90');

        $shipping = $subtotal >= $freeLimit ? 0.0 : $shippingFee;
        $total = max(0, $subtotal - $discount + $shipping);

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'shipping' => round($shipping, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Get shipping settings for frontend use.
     *
     * @return array{free_limit: float, fee: float, min_order: float}
     */
    public function getShippingConfig(): array
    {
        return [
            'free_limit' => (float) Setting::getValue('shipping_free_limit', '500'),
            'fee' => (float) Setting::getValue('shipping_fee', '39.90'),
            'min_order' => (float) Setting::getValue('min_order_amount', '100'),
        ];
    }

    /**
     * Place the order.
     *
     * @param array{
     *     address_id?: int,
     *     shipping_name: string,
     *     shipping_phone: string,
     *     shipping_city: string,
     *     shipping_district: string,
     *     shipping_zip_code?: string,
     *     shipping_address: string,
     *     notes?: string,
     *     coupon_code?: string,
     * } $shippingData
     */
    public function placeOrder(?User $user, array $shippingData, ?string $guestEmail = null, ?string $guestName = null): Order
    {
        $validation = $this->validateCart();

        if (!$validation['valid']) {
            throw new \RuntimeException('Sepet doğrulama hatası: ' . implode(', ', $validation['errors']));
        }

        $items = array_values($validation['items']);
        $subtotal = array_sum(array_column($items, 'total'));

        $minOrder = (float) Setting::getValue('min_order_amount', '100');
        if ($subtotal < $minOrder) {
            throw new \RuntimeException('Minimum sipariş tutarı ' . number_format($minOrder, 0, ',', '.') . '₺\'dir.');
        }

        // Apply coupon if present
        $discount = 0.0;
        $campaignCode = null;

        if (!empty($shippingData['coupon_code'])) {
            $couponResult = $this->applyCoupon($shippingData['coupon_code'], $subtotal);
            if ($couponResult['valid']) {
                $discount = $couponResult['discount'];
                $campaignCode = $shippingData['coupon_code'];
            }
        }

        $totals = $this->calculateTotals($subtotal, $discount);

        $order = DB::transaction(function () use ($user, $items, $totals, $shippingData, $campaignCode, $guestEmail, $guestName): Order {
            // Decrease stock atomically
            foreach ($items as $item) {
                Product::where('id', $item['product_id'])
                    ->decrement('stock', $item['quantity']);
            }

            // Increment campaign usage
            if ($campaignCode) {
                $campaign = $this->campaignService->findByCode($campaignCode);
                $campaign?->increment('used_count');
            }

            return $this->orderService->create([
                'user_id' => $user?->id,
                'guest_email' => $guestEmail,
                'guest_name' => $guestName,
                'items' => $items,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'shipping_cost' => $totals['shipping'],
                'total' => $totals['total'],
                'shipping_name' => $shippingData['shipping_name'],
                'shipping_phone' => $shippingData['shipping_phone'],
                'shipping_city' => $shippingData['shipping_city'],
                'shipping_district' => $shippingData['shipping_district'],
                'shipping_zip_code' => $shippingData['shipping_zip_code'] ?? null,
                'shipping_address' => $shippingData['shipping_address'],
                'campaign_code' => $campaignCode,
                'notes' => $shippingData['notes'] ?? null,
            ]);
        });

        // Clear cart (outside transaction - non-critical)
        $this->cartService->clear();

        // Send order confirmation email to customer
        $customerEmail = $user?->email ?? $guestEmail;
        if ($customerEmail) {
            try {
                $this->mailService->queue($customerEmail, new OrderConfirmationMail($order));
            } catch (\Throwable $e) {
                Log::warning('Sipariş onay maili kuyruğa eklenemedi', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        // Send admin notification email with WhatsApp link
        $this->sendAdminNotification($order);

        return $order;
    }

    /**
     * Send new order notification — 3 kanal:
     *   1. E-posta (admin_notification_email / contact_email)
     *   2. Telegram (notifyAdminInfo — anlık mobil bildirim)
     *   3. In-app bell (NotificationCenter — admin paneli sağ üst)
     *
     * Hata olursa sipariş ASLA bloke olmaz — her kanal kendi try/catch'inde.
     */
    private function sendAdminNotification(Order $order): void
    {
        $orderUrl = rescue(static fn (): ?string => route('admin.orders.show', $order), null, false);
        $customerName = $order->shipping_name ?: ($order->guest_name ?? 'Misafir');
        $totalFmt = number_format((float) $order->total, 2, ',', '.');
        $itemCount = (int) $order->items()->count();

        // 1) E-posta (mevcut)
        $adminEmail = Setting::getValue('admin_notification_email')
            ?? Setting::getValue('contact_email');
        if ($adminEmail) {
            try {
                $this->mailService->queue($adminEmail, new NewOrderAdminNotificationMail($order));
            } catch (\Throwable $e) {
                Log::warning('Admin sipariş bildirim maili kuyruğa eklenemedi', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        // 2) Telegram — anlık özet bildirim (mobil)
        try {
            TelegramNotifier::notifyAdminInfo(
                title: "Yeni Sipariş #{$order->order_number}",
                context: [
                    'müşteri' => $customerName,
                    'şehir'   => $order->shipping_city ?: '—',
                    'tutar'   => "₺{$totalFmt}",
                    'kalem'   => "{$itemCount} ürün",
                ],
                url: $orderUrl,
                emoji: '🛒',
            );
        } catch (\Throwable $e) {
            Log::warning('Sipariş Telegram bildirimi başarısız', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }

        // 3) In-app bell
        try {
            NotificationCenter::send(
                type: 'order_created',
                title: "Yeni sipariş #{$order->order_number} — ₺{$totalFmt}",
                message: "{$customerName} · {$order->shipping_city} · {$itemCount} ürün",
                level: AdminNotification::LEVEL_SUCCESS,
                icon: 'bi-cart-check-fill',
                actionUrl: $orderUrl,
            );
        } catch (\Throwable $e) {
            Log::warning('Sipariş in-app bildirimi başarısız', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
