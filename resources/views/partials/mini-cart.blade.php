@php
    $miniCartCount = app(\App\Services\CartService::class)->count();
@endphp

<div class="mini-cart-wrapper">
    <a href="{{ route('cart.index') }}" class="btn-custom position-relative" id="miniCartBtn">
        <i class="fa-solid fa-basket-shopping me-1"></i> Sepetim
        <span class="mini-cart-badge {{ $miniCartCount > 0 ? '' : 'd-none' }}" id="cartBadge">{{ $miniCartCount }}</span>
    </a>
</div>
