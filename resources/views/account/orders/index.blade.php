@extends('layouts.app')

@section('title', 'Siparişlerim | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Sipariş geçmişinizi görüntüleyin.')
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
            <span>Siparişlerim</span>
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
                        <h3><i class="fa-solid fa-bag-shopping"></i> Siparişlerim</h3>
                    </div>

                    @if($orders->count() > 0)
                    <div class="table-responsive">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Sipariş No</th>
                                    <th>Tarih</th>
                                    <th>Ürünler</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $order)
                                <tr>
                                    <td>{{ $order->order_number }}</td>
                                    <td>{{ $order->created_at->translatedFormat('d F Y') }}</td>
                                    <td>{{ $order->items->count() }} ürün</td>
                                    <td>{{ number_format((float) $order->total, 0, ',', '.') }} ₺</td>
                                    <td>
                                        <span class="order-status {{ $order->status->value }}">
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('account.orders.show', $order->id) }}" class="btn-view">
                                            Detay
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 d-flex justify-content-center">
                        {{ $orders->links('vendor.pagination.custom') }}
                    </div>
                    @else
                    <div class="empty-state">
                        <i class="fa-solid fa-box-open"></i>
                        <h4>Henüz siparişiniz yok</h4>
                        <p>İlk siparişinizi vermek için ürünlerimize göz atın.</p>
                        <a href="{{ route('products.all') }}" class="btn-view">
                            <i class="fa-solid fa-basket-shopping me-2"></i>Alışverişe Başla
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
