@extends('layouts.app')

@section('title', 'Sipariş Onayı | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Siparişiniz başarıyla oluşturuldu.')
@section('robots', 'noindex, nofollow')

@section('content')
{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <div class="breadcrumb-custom animate-fade-up">
            <a href="{{ route('home') }}"><i class="fa-solid fa-house"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Sipariş Onayı</span>
        </div>
        <h1 class="page-title animate-fade-up delay-1">Sipariş Onayı</h1>
        <p class="page-subtitle animate-fade-up delay-2">Siparişiniz başarıyla oluşturuldu</p>

        {{-- Checkout Steps --}}
        <div class="checkout-steps animate-fade-up delay-2">
            <div class="checkout-step completed">
                <div class="checkout-step-number"><i class="fa-solid fa-check"></i></div>
                <span class="checkout-step-text">Sepet</span>
            </div>
            <div class="checkout-step-connector"></div>
            <div class="checkout-step completed">
                <div class="checkout-step-number"><i class="fa-solid fa-check"></i></div>
                <span class="checkout-step-text">Ödeme</span>
            </div>
            <div class="checkout-step-connector"></div>
            <div class="checkout-step active">
                <div class="checkout-step-number"><i class="fa-solid fa-check"></i></div>
                <span class="checkout-step-text">Onay</span>
            </div>
        </div>
    </div>
</header>

{{-- Success Section --}}
<section class="checkout-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                {{-- Success Header --}}
                <div class="success-header animate-fade-up">
                    <div class="success-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h2 class="success-title">Siparişiniz Oluşturuldu!</h2>
                    <p class="success-subtitle">
                        Sipariş numaranız: <strong>{{ $order->order_number }}</strong>
                    </p>
                    @if($isGuest)
                        <p class="success-info">
                            En kısa sürede sizinle iletişime geçeceğiz.
                            Sipariş durumu hakkında <strong>{{ $order->guest_email }}</strong> adresine e-posta ile bilgilendirileceksiniz.
                        </p>
                    @else
                        <p class="success-info">
                            Siparişiniz en kısa sürede hazırlanıp kargoya verilecektir.
                            Sipariş durumunuzu hesabınızdan takip edebilirsiniz.
                        </p>
                    @endif
                </div>

                {{-- Order Items --}}
                <div class="form-card animate-fade-up delay-1">
                    <h3 class="form-card-title">
                        <i class="fa-solid fa-receipt"></i>
                        Sipariş Detayları
                    </h3>
                    <div class="table-responsive">
                        <table class="success-table">
                            <thead>
                                <tr>
                                    <th>Ürün</th>
                                    <th class="text-center">Miktar</th>
                                    <th class="text-end">Fiyat</th>
                                    <th class="text-end">Toplam</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->items as $item)
                                <tr>
                                    <td>
                                        <span class="success-product-name">{{ $item->product_name }}</span>
                                    </td>
                                    <td class="text-center">{{ $item->quantity }} {{ $item->unit }}</td>
                                    <td class="text-end">{{ number_format((float) $item->product_price, 2, ',', '.') }} ₺</td>
                                    <td class="text-end">
                                        <strong class="text-success">{{ number_format((float) $item->total, 2, ',', '.') }} ₺</strong>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row g-4">
                    {{-- Shipping Address --}}
                    <div class="col-md-6">
                        <div class="form-card h-100 animate-fade-up delay-1">
                            <h3 class="form-card-title">
                                <i class="fa-solid fa-truck"></i>
                                Teslimat Adresi
                            </h3>
                            <div class="success-address">
                                <p class="success-address-name">{{ $order->shipping_name }}</p>
                                <p class="success-address-phone">{{ $order->shipping_phone }}</p>
                                <p class="success-address-detail">
                                    {{ $order->shipping_address }}<br>
                                    {{ $order->shipping_district }}, {{ $order->shipping_city }}
                                    @if($order->shipping_zip_code) {{ $order->shipping_zip_code }} @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Payment Summary --}}
                    <div class="col-md-6">
                        <div class="form-card h-100 animate-fade-up delay-2">
                            <h3 class="form-card-title">
                                <i class="fa-solid fa-calculator"></i>
                                Ödeme Özeti
                            </h3>
                            <div class="success-payment">
                                <div class="success-payment-row">
                                    <span>Ara Toplam</span>
                                    <span>{{ number_format((float) $order->subtotal, 2, ',', '.') }} ₺</span>
                                </div>
                                @if((float) $order->discount_amount > 0)
                                <div class="success-payment-row text-danger">
                                    <span>İndirim
                                        @if($order->campaign_code)
                                            <small>({{ $order->campaign_code }})</small>
                                        @endif
                                    </span>
                                    <span>-{{ number_format((float) $order->discount_amount, 2, ',', '.') }} ₺</span>
                                </div>
                                @endif
                                <div class="success-payment-row">
                                    <span>Kargo</span>
                                    <span>
                                        @if((float) $order->shipping_cost > 0)
                                            {{ number_format((float) $order->shipping_cost, 2, ',', '.') }} ₺
                                        @else
                                            <span class="text-success">Ücretsiz</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="success-payment-divider"></div>
                                <div class="success-payment-total">
                                    <span>Genel Toplam</span>
                                    <strong>{{ number_format((float) $order->total, 2, ',', '.') }} ₺</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($order->notes)
                <div class="form-card mt-4 animate-fade-up">
                    <h3 class="form-card-title">
                        <i class="fa-solid fa-note-sticky"></i>
                        Sipariş Notu
                    </h3>
                    <p class="mb-0">{{ $order->notes }}</p>
                </div>
                @endif

                {{-- Actions --}}
                <div class="success-actions animate-fade-up">
                    @if($isGuest)
                        <a href="{{ $order->getTrackingUrl() }}" class="btn-checkout">
                            <i class="fa-solid fa-eye"></i> Siparişi Görüntüle
                        </a>
                    @else
                        <a href="{{ route('account.orders.show', $order->id) }}" class="btn-checkout">
                            <i class="fa-solid fa-eye"></i> Siparişi Görüntüle
                        </a>
                    @endif
                    <a href="{{ route('home') }}" class="btn-checkout-back">
                        <i class="fa-solid fa-leaf"></i> Alışverişe Devam Et
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
