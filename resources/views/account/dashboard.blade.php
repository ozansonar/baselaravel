@extends('layouts.app')

@section('title', 'Hesabım | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Hesap bilgileriniz, siparişleriniz ve adreslerinizi yönetin.')
@section('robots', 'noindex, nofollow')

@section('content')
{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <div class="breadcrumb-custom">
            <a href="{{ route('home') }}"><i class="fa-solid fa-house"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Hesabım</span>
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
                        <h3><i class="fa-solid fa-gauge"></i> Dashboard</h3>
                    </div>

                    {{-- Stats Cards --}}
                    <div class="stats-cards">
                        <div class="stat-card">
                            <i class="fa-solid fa-bag-shopping"></i>
                            <div class="number">{{ $orderCounts['total'] }}</div>
                            <div class="label">Toplam Sipariş</div>
                        </div>
                        <div class="stat-card">
                            <i class="fa-solid fa-truck"></i>
                            <div class="number">{{ $orderCounts['shipped'] }}</div>
                            <div class="label">Kargoda</div>
                        </div>
                        <div class="stat-card">
                            <i class="fa-solid fa-clock"></i>
                            <div class="number">{{ $orderCounts['pending'] }}</div>
                            <div class="label">Beklemede</div>
                        </div>
                        <div class="stat-card">
                            <i class="fa-solid fa-circle-check"></i>
                            <div class="number">{{ $orderCounts['delivered'] }}</div>
                            <div class="label">Teslim Edildi</div>
                        </div>
                    </div>

                    {{-- Recent Orders --}}
                    <h4 class="text-green-dark mb-4">Son Siparişler</h4>

                    @if($recentOrders->count() > 0)
                    <div class="table-responsive">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Sipariş No</th>
                                    <th>Tarih</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentOrders as $order)
                                <tr>
                                    <td>{{ $order->order_number }}</td>
                                    <td>{{ $order->created_at->translatedFormat('d F Y') }}</td>
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
                    @else
                    <div class="empty-state">
                        <i class="fa-solid fa-box-open"></i>
                        <h4>Henüz siparişiniz yok</h4>
                        <p>İlk siparişinizi vererek doğal lezzetlerimizi keşfedin.</p>
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
