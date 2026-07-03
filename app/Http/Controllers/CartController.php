<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    /**
     * Show cart page.
     */
    public function index(): View
    {
        $changes = $this->cartService->syncPrices();
        $items = $this->cartService->getItems();
        $subtotal = $this->cartService->subtotal();
        $count = $this->cartService->count();

        return view('cart.index', compact('items', 'subtotal', 'count', 'changes'));
    }

    /**
     * Add product to cart (AJAX).
     */
    public function add(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = Product::active()->find($request->integer('product_id'));

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Ürün bulunamadı veya satışta değil.',
            ], 404);
        }

        if (!$product->inStock()) {
            return response()->json([
                'success' => false,
                'message' => 'Bu ürün şu anda stokta yok.',
            ], 422);
        }

        $quantity = $request->integer('quantity', 1);

        $this->cartService->add($product, $quantity);

        return response()->json([
            'success' => true,
            'message' => "{$product->name} sepete eklendi.",
            'cart_count' => $this->cartService->count(),
            'cart_subtotal' => number_format($this->cartService->subtotal(), 2, ',', '.'),
        ]);
    }

    /**
     * Update item quantity (AJAX).
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        $productId = $request->integer('product_id');
        $quantity = $request->integer('quantity');

        $this->cartService->update($productId, $quantity);

        $items = $this->cartService->getItems();
        $item = $items[$productId] ?? null;

        return response()->json([
            'success' => true,
            'message' => 'Sepet güncellendi.',
            'cart_count' => $this->cartService->count(),
            'cart_subtotal' => number_format($this->cartService->subtotal(), 2, ',', '.'),
            'item_total' => $item
                ? number_format((float) $item['price'] * $item['quantity'], 2, ',', '.')
                : '0,00',
            'removed' => $item === null,
        ]);
    }

    /**
     * Remove item from cart (AJAX).
     */
    public function remove(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $this->cartService->remove($request->integer('product_id'));

        return response()->json([
            'success' => true,
            'message' => 'Ürün sepetten kaldırıldı.',
            'cart_count' => $this->cartService->count(),
            'cart_subtotal' => number_format($this->cartService->subtotal(), 2, ',', '.'),
            'is_empty' => $this->cartService->isEmpty(),
        ]);
    }

    /**
     * Clear cart (AJAX).
     */
    public function clear(): JsonResponse
    {
        $this->cartService->clear();

        return response()->json([
            'success' => true,
            'message' => 'Sepet temizlendi.',
            'cart_count' => 0,
            'cart_subtotal' => '0,00',
        ]);
    }

    /**
     * Get mini cart data (AJAX - for navbar refresh).
     */
    public function miniCart(): JsonResponse
    {
        $items = $this->cartService->getItems();

        return response()->json([
            'cart_count' => $this->cartService->count(),
            'cart_subtotal' => number_format($this->cartService->subtotal(), 2, ',', '.'),
            'items' => array_values(array_map(fn (array $item): array => [
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'price' => number_format((float) $item['price'], 2, ',', '.'),
                'quantity' => $item['quantity'],
                'image' => $item['image']
                    ? \App\Services\UploadService::url($item['image'], 'thumb')
                    : \App\Services\UploadService::placeholder('thumb'),
                'slug' => $item['slug'],
            ], $items)),
        ]);
    }
}
