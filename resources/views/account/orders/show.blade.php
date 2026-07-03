@extends('layouts.app')

@section('title', 'Sipariş #' . $order->order_number . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Sipariş detaylarınızı görüntüleyin.')
@section('robots', 'noindex, nofollow')

@section('content')
{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <div class="breadcrumb-custom">
            <a href="{{ route('home') }}"><i class="fa-solid fa-house"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <a href="{{ route('account.dashboard') }}">Hesabım</a>
            <i class="fa-solid fa-chevron-right"></i>
            <a href="{{ route('account.orders') }}">Siparişlerim</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>#{{ $order->order_number }}</span>
        </div>
        <h1 class="page-title">Hesabım</h1>
        <p class="page-subtitle">Hesap bilgilerinizi ve siparişlerinizi yönetin</p>
    </div>
</header>

{{-- Dashboard Section --}}
<section class="dashboard-section">
    <div class="container">
        <div class="row">
            {{-- Sidebar --}}
            <div class="col-lg-3">
                @include('account.partials.sidebar')
            </div>

            {{-- Content --}}
            <div class="col-lg-9">
                <div class="dashboard-content">
                    <div class="content-header">
                        <h3><i class="fa-solid fa-receipt"></i> Sipariş #{{ $order->order_number }}</h3>
                        <a href="{{ route('account.orders') }}" class="btn-view">
                            <i class="fa-solid fa-arrow-left me-1"></i> Geri
                        </a>
                    </div>

                    {{-- Order Info Stats --}}
                    <div class="stats-cards mb-4">
                        <div class="stat-card">
                            <i class="fa-solid fa-calendar"></i>
                            <div class="label">Tarih</div>
                            <time class="number" datetime="{{ $order->created_at->format('Y-m-d') }}">{{ $order->created_at->format('d.m.Y') }}</time>
                        </div>
                        <div class="stat-card">
                            <i class="fa-solid fa-circle-info"></i>
                            <div class="label">Durum</div>
                            <div class="mt-1">
                                <span class="order-status {{ $order->status->value }}">
                                    {{ $order->status->label() }}
                                </span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <i class="fa-solid fa-boxes-stacked"></i>
                            <div class="label">Ürün Sayısı</div>
                            <div class="number">{{ $order->items->count() }}</div>
                        </div>
                        <div class="stat-card">
                            <i class="fa-solid fa-turkish-lira-sign"></i>
                            <div class="label">Toplam</div>
                            <div class="number">{{ number_format((float) $order->total, 0, ',', '.') }} ₺</div>
                        </div>
                    </div>

                    {{-- Order Items --}}
                    <h4 class="text-green-dark mb-3"><i class="fa-solid fa-basket-shopping me-2"></i>Sipariş Kalemleri</h4>
                    <div class="table-responsive mb-4">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Ürün</th>
                                    <th>Miktar</th>
                                    <th>Birim Fiyat</th>
                                    <th>Toplam</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->items as $item)
                                <tr>
                                    <td>{{ $item->product_name }}</td>
                                    <td>{{ $item->quantity }} {{ $item->unit }}</td>
                                    <td>{{ number_format((float) $item->product_price, 2, ',', '.') }} ₺</td>
                                    <td>{{ number_format((float) $item->total, 2, ',', '.') }} ₺</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-4">
                        {{-- Shipping Address --}}
                        <div class="col-md-6">
                            <div class="address-card">
                                <h5><i class="fa-solid fa-truck me-2"></i>Teslimat Adresi</h5>
                                <p>
                                    <strong>{{ $order->shipping_name }}</strong><br>
                                    {{ $order->shipping_phone }}<br>
                                    {{ $order->shipping_address }}<br>
                                    {{ $order->shipping_district }}, {{ $order->shipping_city }}
                                    @if($order->shipping_zip_code)
                                        {{ $order->shipping_zip_code }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        {{-- Order Summary --}}
                        <div class="col-md-6">
                            <div class="address-card">
                                <h5><i class="fa-solid fa-calculator me-2"></i>Sipariş Özeti</h5>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Ara Toplam</span>
                                    <span>{{ number_format((float) $order->subtotal, 2, ',', '.') }} ₺</span>
                                </div>
                                @if($order->discount_amount > 0)
                                <div class="d-flex justify-content-between mb-2 text-danger">
                                    <span>İndirim
                                        @if($order->campaign_code)
                                            <small>({{ $order->campaign_code }})</small>
                                        @endif
                                    </span>
                                    <span>-{{ number_format((float) $order->discount_amount, 2, ',', '.') }} ₺</span>
                                </div>
                                @endif
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Kargo</span>
                                    <span>
                                        @if($order->shipping_cost > 0)
                                            {{ number_format((float) $order->shipping_cost, 2, ',', '.') }} ₺
                                        @else
                                            Ücretsiz
                                        @endif
                                    </span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>Genel Toplam</span>
                                    <span>{{ number_format((float) $order->total, 2, ',', '.') }} ₺</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Notes --}}
                    @if($order->notes)
                    <div class="address-card mt-4">
                        <h5><i class="fa-solid fa-note-sticky me-2"></i>Sipariş Notu</h5>
                        <p class="mb-0">{{ $order->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
