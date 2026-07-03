<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Session\SessionManager;

final class CartService
{
    private const SESSION_KEY = 'cart';

    public function __construct(
        private readonly SessionManager $session,
    ) {}

    /**
     * Get all cart items.
     *
     * @return array<int, array{product_id: int, name: string, price: string, image: string|null, unit: string, quantity: int, slug: string}>
     */
    public function getItems(): array
    {
        return $this->session->get(self::SESSION_KEY, []);
    }

    /**
     * Add product to cart or increment quantity.
     */
    public function add(Product $product, int $quantity = 1): void
    {
        $items = $this->getItems();
        $productId = $product->id;

        if (isset($items[$productId])) {
            $newQty = $items[$productId]['quantity'] + $quantity;
            $items[$productId]['quantity'] = min($newQty, $product->stock);
        } else {
            $items[$productId] = [
                'product_id' => $productId,
                'name' => $product->name,
                'price' => $product->currentPrice(),
                'image' => $product->image,
                'unit' => $product->unit ?? 'Adet',
                'quantity' => min($quantity, $product->stock),
                'slug' => $product->slug,
            ];
        }

        $this->session->put(self::SESSION_KEY, $items);
    }

    /**
     * Update item quantity.
     */
    public function update(int $productId, int $quantity): void
    {
        $items = $this->getItems();

        if (!isset($items[$productId])) {
            return;
        }

        if ($quantity <= 0) {
            $this->remove($productId);
            return;
        }

        $maxStock = Product::active()->where('id', $productId)->value('stock') ?? $quantity;

        $items[$productId]['quantity'] = min($quantity, $maxStock);
        $this->session->put(self::SESSION_KEY, $items);
    }

    /**
     * Remove item from cart.
     */
    public function remove(int $productId): void
    {
        $items = $this->getItems();

        unset($items[$productId]);

        $this->session->put(self::SESSION_KEY, $items);
    }

    /**
     * Clear entire cart.
     */
    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * Get total item count.
     */
    public function count(): int
    {
        $items = $this->getItems();

        return array_sum(array_column($items, 'quantity'));
    }

    /**
     * Get subtotal (before discount/shipping).
     */
    public function subtotal(): float
    {
        $items = $this->getItems();
        $total = 0.0;

        foreach ($items as $item) {
            $total += (float) $item['price'] * $item['quantity'];
        }

        return round($total, 2);
    }

    /**
     * Sync cart prices with current database prices.
     * Returns list of changes for user notification.
     *
     * @return array<string>
     */
    public function syncPrices(): array
    {
        $items = $this->getItems();
        $changes = [];

        if (empty($items)) {
            return $changes;
        }

        $productIds = array_keys($items);
        $products = Product::whereIn('id', $productIds)->active()->get()->keyBy('id');

        foreach ($items as $productId => $item) {
            $product = $products->get($productId);

            // Product no longer available
            if (!$product || !$product->inStock()) {
                $changes[] = "{$item['name']} artık mevcut değil ve sepetten kaldırıldı.";
                unset($items[$productId]);
                continue;
            }

            // Price changed
            $currentPrice = $product->currentPrice();
            if ($item['price'] !== $currentPrice) {
                $items[$productId]['price'] = $currentPrice;
                $changes[] = "{$item['name']} fiyatı güncellendi.";
            }

            // Stock reduced below cart quantity
            if ($item['quantity'] > $product->stock) {
                $items[$productId]['quantity'] = $product->stock;
                $changes[] = "{$item['name']} stok durumuna göre miktar güncellendi.";
            }
        }

        $this->session->put(self::SESSION_KEY, $items);

        return $changes;
    }

    /**
     * Check if a product is in the cart.
     */
    public function has(int $productId): bool
    {
        $items = $this->getItems();

        return isset($items[$productId]);
    }

    /**
     * Check if cart is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->getItems());
    }
}
