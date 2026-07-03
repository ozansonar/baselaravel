@extends('layouts.app')

@section('title', 'Sipariş Takip #' . $order->order_number . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Siparişinizin durumunu takip edin.')
@section('robots', 'noindex, nofollow')

@section('content')
{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <div class="breadcrumb-custom animate-fade-up">
            <a href="{{ route('home') }}"><i class="fa-solid fa-house"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Sipariş Takip</span>
        </div>
        <h1 class="page-title animate-fade-up delay-1">Sipariş Takip</h1>
        <p class="page-subtitle animate-fade-up delay-2">Sipariş #{{ $order->order_number }}</p>
    </div>
</header>

{{-- Order Tracking Section --}}
<section class="checkout-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                {{-- Order Status Header --}}
                <div class="success-header animate-fade-up">
                    <div class="success-icon">
                        @if($order->status === \App\Enums\OrderStatus::Delivered)
                            <i class="fa-solid fa-circle-check"></i>
                        @elseif($order->status === \App\Enums\OrderStatus::Cancelled)
                            <i class="fa-solid fa-circle-xmark"></i>
                        @elseif($order->status === \App\Enums\OrderStatus::Shipped)
                            <i class="fa-solid fa-truck"></i>
                        @elseif($order->status === \App\Enums\OrderStatus::Processing)
                            <i class="fa-solid fa-box-open"></i>
                        @else
                            <i class="fa-solid fa-clock"></i>
                        @endif
                    </div>
                    <h2 class="success-title">
                        @if($order->status === \App\Enums\OrderStatus::Pending)
                            Siparişiniz Alındı
                        @elseif($order->status === \App\Enums\OrderStatus::Processing)
                            Siparişiniz Hazırlanıyor
                        @elseif($order->status === \App\Enums\OrderStatus::Shipped)
                            Siparişiniz Kargoda
                        @elseif($order->status === \App\Enums\OrderStatus::Delivered)
                            Siparişiniz Teslim Edildi
                        @elseif($order->status === \App\Enums\OrderStatus::Cancelled)
                            Siparişiniz İptal Edildi
                        @endif
                    </h2>
                    <p class="success-subtitle">
                        Sipariş Numarası: <strong>#{{ $order->order_number }}</strong>
                    </p>
                    <p class="success-info">
                        Sipariş Tarihi: {{ $order->created_at->format('d.m.Y H:i') }}
                    </p>
                </div>

                {{-- Order Progress Steps --}}
                @if($order->status !== \App\Enums\OrderStatus::Cancelled)
                <div class="form-card animate-fade-up delay-1">
                    <div class="checkout-steps">
                        @php
                            $statusOrder = ['pending', 'processing', 'shipped', 'delivered'];
                            $currentIndex = array_search($order->status->value, $statusOrder);
                        @endphp
                        <div class="checkout-step {{ $currentIndex >= 0 ? 'completed' : '' }}">
                            <div class="checkout-step-number">
                                @if($currentIndex >= 0)
                                    <i class="fa-solid fa-check"></i>
                                @else
                                    1
                                @endif
                            </div>
                            <span class="checkout-step-text">Sipariş Alındı</span>
                        </div>
                        <div class="checkout-step-connector"></div>
                        <div class="checkout-step {{ $currentIndex >= 1 ? 'completed' : '' }} {{ $currentIndex === 0 ? 'active' : '' }}">
                            <div class="checkout-step-number">
                                @if($currentIndex >= 1)
                                    <i class="fa-solid fa-check"></i>
                                @else
                                    2
                                @endif
                            </div>
                            <span class="checkout-step-text">Hazırlanıyor</span>
                        </div>
                        <div class="checkout-step-connector"></div>
                        <div class="checkout-step {{ $currentIndex >= 2 ? 'completed' : '' }} {{ $currentIndex === 1 ? 'active' : '' }}">
                            <div class="checkout-step-number">
                                @if($currentIndex >= 2)
                                    <i class="fa-solid fa-check"></i>
                                @else
                                    3
                                @endif
                            </div>
                            <span class="checkout-step-text">Kargoda</span>
                        </div>
                        <div class="checkout-step-connector"></div>
                        <div class="checkout-step {{ $currentIndex >= 3 ? 'completed' : '' }} {{ $currentIndex === 2 ? 'active' : '' }}">
                            <div class="checkout-step-number">
                                @if($currentIndex >= 3)
                                    <i class="fa-solid fa-check"></i>
                                @else
                                    4
                                @endif
                            </div>
                            <span class="checkout-step-text">Teslim Edildi</span>
                        </div>
                    </div>
                </div>
                @endif

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
                    <a href="{{ route('home') }}" class="btn-checkout-back">
                        <i class="fa-solid fa-leaf"></i> Ana Sayfaya Dön
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
