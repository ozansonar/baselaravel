@extends('layouts.app')

@section('title', 'Sepetim | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Sepetinizdeki doğal köy ürünleri. Güvenli ödeme ve hızlı teslimat ile ' . \App\Models\Setting::getValue('site_name', config('app.name')) . '.')
@section('robots', 'noindex, nofollow')

@section('content')
{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <div class="breadcrumb-custom animate-fade-up">
            <a href="{{ route('home') }}"><i class="fa-solid fa-house"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Sepetim</span>
        </div>
        <h1 class="page-title animate-fade-up delay-1">Sepetim</h1>
        <p class="page-subtitle animate-fade-up delay-2" id="cartSubtitle">
            @if($count > 0)
                Sepetinizde {{ $count }} ürün bulunmaktadır
            @else
                Sepetiniz boş
            @endif
        </p>
    </div>
</header>

{{-- Cart Section --}}
<section class="cart-section">
    <div class="container">
        {{-- Price sync warnings --}}
        @if(!empty($changes))
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <strong><i class="fa-solid fa-triangle-exclamation me-1"></i> Sepetiniz güncellendi:</strong>
            <ul class="mb-0 mt-2">
                @foreach($changes as $change)
                <li>{{ $change }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
        @endif

        @if(!empty($items))
        <div class="row">
            {{-- Cart Items --}}
            <div class="col-lg-8">
                <div class="cart-items-container animate-fade-up">
                    {{-- Cart Header --}}
                    <div class="cart-header" id="cartHeader">
                        <h3><i class="fa-solid fa-shopping-cart me-2"></i>Sepetinizdeki Ürünler <span id="itemCount">({{ $count }} ürün)</span></h3>
                        <button class="clear-cart-btn" id="clearCartBtn" aria-label="Sepeti tamamen temizle">
                            <i class="fa-solid fa-trash-alt me-2"></i>Sepeti Temizle
                        </button>
                    </div>

                    {{-- Cart Items --}}
                    <div id="cartItemsContainer">
                        @foreach($items as $item)
                        <div class="cart-item" id="cartItem-{{ $item['product_id'] }}" data-product-id="{{ $item['product_id'] }}" data-price="{{ $item['price'] }}">
                            <div class="cart-item-image">
                                <a href="{{ route('products.show', $item['slug']) }}">
                                    <img src="{{ \App\Services\UploadService::url($item['image'], 'thumb') }}"
                                         alt="{{ $item['name'] }}" width="100" height="100" loading="lazy" decoding="async">
                                </a>
                            </div>
                            <div class="cart-item-details">
                                <div class="cart-item-category">{{ $item['unit'] }}</div>
                                <div class="cart-item-name">
                                    <a href="{{ route('products.show', $item['slug']) }}">{{ $item['name'] }}</a>
                                </div>
                                <div class="cart-item-unit">{{ number_format((float) $item['price'], 2, ',', '.') }} ₺ / {{ $item['unit'] }}</div>
                            </div>
                            <div class="cart-item-quantity">
                                <button class="cart-qty-btn cart-qty-minus" data-product-id="{{ $item['product_id'] }}" {{ $item['quantity'] <= 1 ? 'disabled' : '' }} aria-label="Miktarı azalt">
                                    <i class="fa-solid fa-minus"></i>
                                </button>
                                <span class="cart-qty-value" id="qtyValue-{{ $item['product_id'] }}">{{ $item['quantity'] }}</span>
                                <button class="cart-qty-btn cart-qty-plus" data-product-id="{{ $item['product_id'] }}" aria-label="Miktarı artır">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                            <div class="cart-item-price">
                                <div class="cart-unit-price" id="unitPrice-{{ $item['product_id'] }}">{{ $item['quantity'] }} x {{ number_format((float) $item['price'], 2, ',', '.') }} ₺</div>
                                <div class="cart-total-price" id="itemTotal-{{ $item['product_id'] }}">{{ number_format((float) $item['price'] * $item['quantity'], 2, ',', '.') }} ₺</div>
                            </div>
                            <button class="cart-remove-btn" data-product-id="{{ $item['product_id'] }}">
                                <i class="fa-solid fa-trash-alt"></i>
                            </button>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Continue Shopping --}}
                <div class="continue-shopping">
                    <a href="{{ route('products.all') }}">
                        <i class="fa-solid fa-arrow-left"></i>
                        Alışverişe Devam Et
                    </a>
                </div>
            </div>

            {{-- Order Summary --}}
            <div class="col-lg-4">
                <div class="order-summary animate-fade-up delay-1" id="orderSummary">
                    <h3 class="summary-title"><i class="fa-solid fa-receipt me-2"></i>Sipariş Özeti</h3>

                    {{-- Free Shipping Progress --}}
                    @php
                        $freeShippingThreshold = (float) \App\Models\Setting::getValue('shipping_free_limit', '500');
                        $shippingCost = (float) \App\Models\Setting::getValue('shipping_fee', '39.90');
                        $minOrderAmount = (float) \App\Models\Setting::getValue('min_order_amount', '100');
                        $remaining = max(0, $freeShippingThreshold - $subtotal);
                        $progress = min(($subtotal / $freeShippingThreshold) * 100, 100);
                        $isFreeShipping = $subtotal >= $freeShippingThreshold;
                        $shippingAmount = $isFreeShipping ? 0 : $shippingCost;
                        $total = $subtotal + $shippingAmount;
                    @endphp
                    <div class="free-shipping-progress" id="shippingProgress">
                        @if(!$isFreeShipping)
                        <div class="shipping-progress-text">
                            <span>Ücretsiz kargo için</span>
                            <strong id="remainingForFreeShipping">{{ number_format($remaining, 0, ',', '.') }}₺ kaldı</strong>
                        </div>
                        @else
                        <div class="shipping-progress-success">
                            <i class="fa-solid fa-check-circle"></i> Ücretsiz kargo kazandınız!
                        </div>
                        @endif
                        <div class="shipping-progress-bar">
                            <div class="shipping-progress-fill" id="progressBar" data-width="{{ $progress }}"></div>
                        </div>
                    </div>

                    <div class="summary-row">
                        <span>Ara Toplam</span>
                        <strong id="summarySubtotal">{{ number_format($subtotal, 2, ',', '.') }}₺</strong>
                    </div>
                    <div class="summary-row">
                        <span>Kargo</span>
                        <strong id="summaryShipping" @if($isFreeShipping) class="text-success" @endif>
                            {{ $isFreeShipping ? 'Ücretsiz' : number_format($shippingAmount, 2, ',', '.') . '₺' }}
                        </strong>
                    </div>
                    <div class="summary-row d-none" id="discountRow">
                        <span>İndirim</span>
                        <strong id="summaryDiscount" class="cart-discount-text">-0₺</strong>
                    </div>

                    <div class="summary-divider"></div>

                    {{-- Coupon Section --}}
                    <div class="coupon-section">
                        <label><i class="fa-solid fa-tag me-2"></i>Kupon Kodu</label>
                        <div class="coupon-input-group" id="couponInputGroup">
                            <input type="text" class="coupon-input" id="couponInput" placeholder="Kupon kodunuzu girin">
                            <button class="coupon-btn" id="applyCouponBtn">Uygula</button>
                        </div>
                        <div class="coupon-applied d-none" id="couponApplied">
                            <span><i class="fa-solid fa-check-circle me-1"></i> <span id="appliedCouponCode"></span></span>
                            <button id="removeCouponBtn"><i class="fa-solid fa-times"></i></button>
                        </div>
                    </div>

                    <div class="summary-total">
                        <span>Toplam</span>
                        <strong id="summaryTotal">{{ number_format($total, 2, ',', '.') }}₺</strong>
                    </div>

                    @php
                        $belowMinOrder = $subtotal < $minOrderAmount;
                    @endphp

                    @if($belowMinOrder)
                    <div class="alert alert-warning small mb-3" id="minOrderAlert">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Minimum sipariş tutarı <strong>{{ number_format($minOrderAmount, 0, ',', '.') }}₺</strong>'dir.
                        <strong>{{ number_format($minOrderAmount - $subtotal, 2, ',', '.') }}₺</strong> daha eklemeniz gerekmektedir.
                    </div>
                    @endif

                    @auth
                    <a href="{{ route('checkout.index') }}" class="btn-checkout {{ $belowMinOrder ? 'disabled' : '' }}" id="checkoutBtn"
                       @if($belowMinOrder) aria-disabled="true" @endif>
                        <i class="fa-solid fa-basket-shopping"></i>
                        Siparişi Tamamla
                    </a>
                    @else
                        @if(\App\Models\Setting::getValue('guest_checkout_enabled', '1') === '1')
                        <a href="{{ route('checkout.index') }}" class="btn-checkout {{ $belowMinOrder ? 'disabled' : '' }}" id="checkoutBtn"
                           @if($belowMinOrder) aria-disabled="true" @endif>
                            <i class="fa-solid fa-basket-shopping"></i>
                            Siparişi Tamamla
                        </a>
                        @else
                        <a href="{{ route('login') }}" class="btn-checkout" id="checkoutBtn">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            Giriş Yap ve Sipariş Ver
                        </a>
                        @endif
                    @endauth

                    @php
                        $whatsappNumber = \App\Models\Setting::getValue('social_whatsapp', '+905059424124');
                        $whatsappClean = preg_replace('/[^0-9]/', '', $whatsappNumber);
                    @endphp
                    <a class="btn-whatsapp" id="whatsappBtn"
                       href="https://wa.me/{{ $whatsappClean }}?text={{ urlencode('Merhaba, sipariş vermek istiyorum.') }}"
                       target="_blank" rel="noopener noreferrer"
                       data-phone="{{ $whatsappClean }}" data-subtotal="{{ $subtotal }}" data-shipping="{{ $shippingAmount }}">
                        <i class="fa-brands fa-whatsapp"></i>
                        WhatsApp ile Sipariş Ver
                    </a>

                    <div class="cart-features">
                        <div class="cart-feature-item">
                            <i class="fa-solid fa-truck"></i>
                            <span>Hızlı Teslimat</span>
                        </div>
                        <div class="cart-feature-item">
                            <i class="fa-solid fa-snowflake"></i>
                            <span>Soğuk Zincir</span>
                        </div>
                        <div class="cart-feature-item">
                            <i class="fa-solid fa-rotate-left"></i>
                            <span>Kolay İade</span>
                        </div>
                        <div class="cart-feature-item">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span>Güvenli Ödeme</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @else
        {{-- Empty Cart --}}
        <div class="cart-empty animate-fade-up">
            <div class="cart-empty-icon">
                <i class="fa-solid fa-basket-shopping"></i>
            </div>
            <h3>Sepetiniz Boş</h3>
            <p>Henüz sepetinize ürün eklemediniz. Hemen alışverişe başlayın!</p>
            <a href="{{ route('products.all') }}" class="btn-shop-now">
                <i class="fa-solid fa-store"></i>
                Alışverişe Başla
            </a>
        </div>
        @endif
    </div>
</section>
@endsection

@if(!empty($items))
@push('scripts')
<script>
    window.SHIPPING_CONFIG = {
        freeLimit: {{ $freeShippingThreshold }},
        fee: {{ $shippingCost }},
        minOrder: {{ $minOrderAmount }}
    };
    (function(){var pb=document.getElementById('progressBar');if(pb)pb.style.width=pb.dataset.width+'%';})();
</script>
<script src="{{ versioned_asset('js/cart.js') }}"></script>
@endpush
@endif
