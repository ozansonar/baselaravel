@extends('layouts.app')

@section('title', 'Sipariş Tamamla | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Siparişinizi tamamlayın. Teslimat adresinizi seçin ve ödeme yapın.')
@section('robots', 'noindex, nofollow')

@section('content')
{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <div class="breadcrumb-custom animate-fade-up">
            <a href="{{ route('home') }}"><i class="fa-solid fa-house"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <a href="{{ route('cart.index') }}">Sepetim</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Ödeme</span>
        </div>
        <h1 class="page-title animate-fade-up delay-1">Güvenli Ödeme</h1>
        <p class="page-subtitle animate-fade-up delay-2">Siparişinizi tamamlamak için bilgilerinizi girin</p>

        {{-- Checkout Steps --}}
        <div class="checkout-steps animate-fade-up delay-2">
            <div class="checkout-step completed">
                <div class="checkout-step-number"><i class="fa-solid fa-check"></i></div>
                <span class="checkout-step-text">Sepet</span>
            </div>
            <div class="checkout-step-connector"></div>
            <div class="checkout-step active">
                <div class="checkout-step-number">2</div>
                <span class="checkout-step-text">Ödeme</span>
            </div>
            <div class="checkout-step-connector"></div>
            <div class="checkout-step">
                <div class="checkout-step-number">3</div>
                <span class="checkout-step-text">Onay</span>
            </div>
        </div>
    </div>
</header>

{{-- Checkout Section --}}
<section class="checkout-section">
    <div class="container">
        <a href="{{ route('cart.index') }}" class="checkout-btn-back">
            <i class="fa-solid fa-arrow-left"></i>
            Sepete Dön
        </a>

        <form method="POST" action="{{ route('checkout.store') }}" id="checkoutForm" novalidate>
            @csrf

            <div class="row g-4">
                {{-- Left: Forms --}}
                <div class="col-lg-8">
                    {{-- Guest Contact Info --}}
                    @if($isGuest)
                    <div class="form-card animate-fade-up">
                        <h3 class="form-card-title">
                            <i class="fa-solid fa-user"></i>
                            İletişim Bilgileri
                        </h3>
                        <p class="text-muted small mb-3">
                            Zaten hesabınız var mı?
                            <a href="{{ route('login', ['redirect' => url()->current()]) }}" class="text-success fw-bold">Giriş yapın</a>
                        </p>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label for="guest_name" class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control"
                                       id="guest_name" name="guest_name"
                                       value="{{ old('guest_name') }}" minlength="3" maxlength="100"
                                       required aria-required="true" data-validate="name"
                                       autocomplete="name">
                            </div>
                            <div class="col-md-6">
                                <label for="guest_email" class="form-label">E-posta <span class="text-danger">*</span></label>
                                <input type="email" class="form-control"
                                       id="guest_email" name="guest_email"
                                       value="{{ old('guest_email') }}" placeholder="ornek@email.com" maxlength="255"
                                       required aria-required="true" data-validate="email"
                                       autocomplete="email">
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Saved Addresses --}}
                    @if(!$isGuest && $addresses->count() > 0)
                    <div class="form-card animate-fade-up">
                        <h3 class="form-card-title">
                            <i class="fa-solid fa-bookmark"></i>
                            Kayıtlı Adresler
                        </h3>
                        <div class="row g-3">
                            @foreach($addresses as $address)
                            <div class="col-md-6">
                                @php
                                    $addressData = [
                                        'full_name' => $address->full_name,
                                        'phone' => $address->phone,
                                        'city' => $address->city,
                                        'district' => $address->district,
                                        'zip_code' => $address->zip_code,
                                        'address_line' => $address->address_line,
                                    ];
                                @endphp
                                <div class="checkout-address-card {{ $address->is_default ? 'active' : '' }}"
                                     data-address="{{ e(json_encode($addressData)) }}">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <strong>{{ $address->title }}</strong>
                                        @if($address->is_default)
                                            <span class="badge bg-success">Varsayılan</span>
                                        @endif
                                    </div>
                                    <p class="mb-1 small">{{ $address->full_name }}</p>
                                    <p class="mb-0 small text-muted">{{ $address->fullAddress() }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Shipping Address --}}
                    <div class="form-card animate-fade-up delay-1">
                        <h3 class="form-card-title">
                            <i class="fa-solid fa-truck"></i>
                            Teslimat Adresi
                        </h3>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label for="shipping_name" class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control"
                                       id="shipping_name" name="shipping_name"
                                       value="{{ old('shipping_name', $defaultAddress?->full_name ?? auth()->user()?->name) }}"
                                       minlength="3" maxlength="100"
                                       required aria-required="true" data-validate="name"
                                       autocomplete="shipping name">
                            </div>

                            <div class="col-md-6">
                                <label for="shipping_phone" class="form-label">Telefon <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control"
                                       id="shipping_phone" name="shipping_phone"
                                       value="{{ old('shipping_phone', $defaultAddress?->phone ?? auth()->user()?->phone) }}"
                                       placeholder="0555 123 45 67" maxlength="14"
                                       required aria-required="true" data-validate="phone"
                                       autocomplete="shipping tel">
                            </div>

                            <div class="col-md-6">
                                <label for="shipping_city" class="form-label">İl <span class="text-danger">*</span></label>
                                <select class="form-select"
                                        id="shipping_city" name="shipping_city"
                                        required aria-required="true" data-validate="city">
                                    <option value="">İl seçiniz</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city }}" {{ old('shipping_city', $defaultAddress?->city) === $city ? 'selected' : '' }}>
                                            {{ $city }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="shipping_district" class="form-label">İlçe <span class="text-danger">*</span></label>
                                <select class="form-select"
                                        id="shipping_district" name="shipping_district"
                                        required aria-required="true" data-validate="district"
                                        data-old="{{ old('shipping_district', $defaultAddress?->district) }}">
                                    <option value="">Önce il seçiniz</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="shipping_zip_code" class="form-label">Posta Kodu</label>
                                <input type="text" class="form-control"
                                       id="shipping_zip_code" name="shipping_zip_code"
                                       value="{{ old('shipping_zip_code', $defaultAddress?->zip_code) }}"
                                       maxlength="5" data-validate="zipcode"
                                       autocomplete="shipping postal-code">
                            </div>

                            <div class="col-12">
                                <label for="shipping_address" class="form-label">Adres <span class="text-danger">*</span></label>
                                <textarea class="form-control"
                                          id="shipping_address" name="shipping_address" rows="3"
                                          placeholder="Mahalle, sokak, bina no, daire no" required aria-required="true"
                                          autocomplete="shipping street-address">{{ old('shipping_address', $defaultAddress?->address_line) }}</textarea>
                            </div>
                        </div>
                    </div>

                    {{-- Order Notes --}}
                    <div class="form-card animate-fade-up delay-2">
                        <h3 class="form-card-title">
                            <i class="fa-solid fa-note-sticky"></i>
                            Sipariş Notu (Opsiyonel)
                        </h3>
                        <textarea class="form-control" name="notes" rows="3"
                                  placeholder="Siparişinizle ilgili eklemek istediğiniz bir not var mı? (opsiyonel)">{{ old('notes') }}</textarea>
                    </div>
                </div>

                {{-- Right: Summary --}}
                <div class="col-lg-4">
                    <div class="order-summary animate-fade-up delay-1">
                        <h3 class="summary-title"><i class="fa-solid fa-receipt me-2"></i>Sipariş Özeti</h3>

                        <div class="summary-items">
                            @foreach($items as $item)
                            <div class="summary-item">
                                <div class="summary-item-image">
                                    <i class="fa-solid fa-box"></i>
                                </div>
                                <div class="summary-item-details">
                                    <div class="summary-item-name">{{ $item['product_name'] }}</div>
                                    <div class="summary-item-qty">{{ $item['quantity'] }} adet x {{ number_format($item['product_price'], 2, ',', '.') }} ₺</div>
                                </div>
                                <div class="summary-item-price">{{ number_format($item['total'], 2, ',', '.') }} ₺</div>
                            </div>
                            @endforeach
                        </div>

                        {{-- Coupon --}}
                        <div class="summary-coupon">
                            <h6 class="summary-coupon-title"><i class="fa-solid fa-tag me-2"></i>Kupon Kodu</h6>
                            <div class="input-group">
                                <input type="text" class="form-control" id="couponInput"
                                       placeholder="Kupon kodunu girin">
                                <button type="button" class="btn btn-outline-success" id="applyCouponBtn">
                                    Uygula
                                </button>
                            </div>
                            <input type="hidden" name="coupon_code" id="couponCodeHidden" value="{{ old('coupon_code') }}">
                            <div id="couponMessage" class="small mt-2"></div>
                        </div>

                        <div class="summary-divider"></div>

                        <div class="summary-row">
                            <span>Ara Toplam</span>
                            <strong id="checkoutSubtotal">{{ number_format($totals['subtotal'], 2, ',', '.') }} ₺</strong>
                        </div>

                        <div class="summary-row d-none" id="discountRow">
                            <span>İndirim</span>
                            <strong class="text-danger" id="checkoutDiscount">-0,00 ₺</strong>
                        </div>

                        <div class="summary-row">
                            <span>Kargo</span>
                            @if($totals['shipping'] > 0)
                                <strong id="checkoutShipping">{{ number_format($totals['shipping'], 2, ',', '.') }} ₺</strong>
                            @else
                                <strong id="checkoutShipping" class="text-success">Ücretsiz</strong>
                            @endif
                        </div>

                        <div class="summary-divider"></div>

                        <div class="summary-total">
                            <span>Toplam</span>
                            <strong id="checkoutTotal">{{ number_format($totals['total'], 2, ',', '.') }} ₺</strong>
                        </div>

                        <div class="checkout-info-box">
                            <div class="checkout-info-icon">
                                <i class="fa-solid fa-circle-info"></i>
                            </div>
                            <div class="checkout-info-text">
                                <strong>Online ödeme alınmamaktadır.</strong>
                                Siparişiniz yalnızca kayıt altına alınacak, kartınızdan herhangi bir ücret çekilmeyecektir. Siparişiniz oluşturulduktan sonra sizinle telefon üzerinden iletişime geçerek teslimat ve ödeme detayları hakkında bilgi vereceğiz.
                            </div>
                        </div>

                        <x-recaptcha :hideError="true" />

                        <button type="submit" class="btn-checkout" id="placeOrderBtn">
                            <i class="fa-solid fa-check"></i> Siparişi Onayla
                        </button>

                        <a href="{{ route('cart.index') }}" class="btn-checkout-back">
                            <i class="fa-solid fa-arrow-left"></i> Sepete Dön
                        </a>

                        <div class="security-badge">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span>Bilgileriniz güvenle korunmaktadır</span>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script
    src="{{ versioned_asset('js/checkout-validation.js') }}"
    data-coupon-url="{{ route('checkout.apply-coupon') }}"
    data-districts-url="{{ url('/api/iller') }}"
></script>
{{-- Server-side validation errors → show via global modal --}}
@if($errors->any())
<script>
document.addEventListener('DOMContentLoaded', function () {
    var messages = @json($errors->all());
    var html = '<ul class="text-start mb-0 list-unstyled">';
    messages.forEach(function (msg) {
        html += '<li class="mb-1"><i class="fa-solid fa-circle-exclamation text-danger me-1"></i> ' + msg + '</li>';
    });
    html += '</ul>';
    window.showResultModal('error', html, 'Eksik veya Hatalı Bilgiler');
});
</script>
@endif
@endpush
