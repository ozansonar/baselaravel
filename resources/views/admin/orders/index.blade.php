@extends('layouts.admin')

@section('title', 'Sipariş Yönetimi')
@section('page_title', 'Sipariş Yönetimi')
@section('page_description', 'Tüm siparişleri takip edin, durumlarını güncelleyin ve yönetin')

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Siparişler</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Sipariş Yönetimi</h1>
            <p class="page-subtitle">Tüm siparişleri takip edin, durumlarını güncelleyin ve yönetin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <div class="dropdown">
                <button class="btn-glass dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Dışa Aktar
                </button>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-theme">
                    <li><a class="dropdown-item text-clr-secondary" href="#" onclick="exportOrders('csv')"><i class="bi bi-filetype-csv me-2 text-neon-blue"></i>CSV olarak indir</a></li>
                    <li><a class="dropdown-item text-clr-secondary" href="#" onclick="exportOrders('excel')"><i class="bi bi-file-earmark-excel me-2 text-neon-green"></i>Excel olarak indir</a></li>
                    <li><hr class="dropdown-divider dropdown-divider-theme"></li>
                    <li><a class="dropdown-item text-clr-secondary" href="#" onclick="exportOrders('pdf')"><i class="bi bi-filetype-pdf me-2 text-neon-red"></i>PDF olarak indir</a></li>
                </ul>
            </div>
        </div>
    </div>


    <!-- ==================== SECTION 1: STAT CARDS ==================== -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-cart-check"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Sipariş</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total_orders'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Gelir</span>
                    <h3 class="usr-stat-value" data-count="{{ (int) $stats['total_revenue'] }}" data-suffix=" ₺">0 ₺</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Bekleyen</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['pending_count'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple">
                    <i class="bi bi-arrow-return-left"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">İptal Edilen</span>
                    <h3 class="usr-stat-value" data-count="{{ $statusCounts[\App\Enums\OrderStatus::Cancelled->value] ?? 0 }}">0</h3>
                </div>
            </div>
        </div>
    </div>


    <!-- ==================== SECTION 2: STATUS TABS ==================== -->
    @php
        $currentStatus = request('status', '');
        $totalCount = array_sum($statusCounts);

        $statusTabs = [
            '' => ['label' => 'Tümü', 'icon' => '', 'color' => '', 'count' => $totalCount],
            'pending' => ['label' => 'Bekleyen', 'icon' => 'bi-clock', 'color' => 'text-neon-orange', 'count' => $statusCounts['pending'] ?? 0],
            'processing' => ['label' => 'Hazırlanıyor', 'icon' => 'bi-gear', 'color' => 'text-neon-blue', 'count' => $statusCounts['processing'] ?? 0],
            'shipped' => ['label' => 'Kargoda', 'icon' => 'bi-truck', 'color' => 'text-neon-purple', 'count' => $statusCounts['shipped'] ?? 0],
            'delivered' => ['label' => 'Teslim Edildi', 'icon' => 'bi-check-circle', 'color' => 'text-neon-green', 'count' => $statusCounts['delivered'] ?? 0],
            'cancelled' => ['label' => 'İptal', 'icon' => 'bi-x-circle', 'color' => 'text-neon-red', 'count' => $statusCounts['cancelled'] ?? 0],
        ];
    @endphp

    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        @foreach($statusTabs as $statusValue => $tab)
            <a href="{{ route('admin.orders.index', array_merge(request()->except(['status', 'page']), $statusValue ? ['status' => $statusValue] : [])) }}"
               class="cl-status-tab {{ $currentStatus === $statusValue ? 'active' : '' }}">
                @if($tab['icon'])
                    <i class="bi {{ $tab['icon'] }} {{ $tab['color'] }}"></i>
                @endif
                <span>{{ $tab['label'] }}</span>
                <span class="cl-tab-count">{{ $tab['count'] }}</span>
            </a>
        @endforeach
    </div>


    <!-- ==================== SECTION 3: FILTERS & TOOLBAR ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.orders.index') }}" class="cl-toolbar" id="filterForm">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" id="orderSearch" placeholder="Sipariş no, müşteri adı veya telefon ile ara..." value="{{ request('search') }}">
                </div>
                <div class="cl-filters">
                    <select class="cl-filter-select" name="date_filter" id="filterDate" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Tarihler</option>
                        <option value="today" {{ request('date_filter') === 'today' ? 'selected' : '' }}>Bugün</option>
                        <option value="week" {{ request('date_filter') === 'week' ? 'selected' : '' }}>Bu Hafta</option>
                        <option value="month" {{ request('date_filter') === 'month' ? 'selected' : '' }}>Bu Ay</option>
                        <option value="quarter" {{ request('date_filter') === 'quarter' ? 'selected' : '' }}>Son 3 Ay</option>
                    </select>
                    <select class="cl-filter-select" name="amount_filter" id="filterAmount" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Tutarlar</option>
                        <option value="0-500" {{ request('amount_filter') === '0-500' ? 'selected' : '' }}>0 - 500 TL</option>
                        <option value="500-2000" {{ request('amount_filter') === '500-2000' ? 'selected' : '' }}>500 - 2.000 TL</option>
                        <option value="2000-10000" {{ request('amount_filter') === '2000-10000' ? 'selected' : '' }}>2.000 - 10.000 TL</option>
                        <option value="10000+" {{ request('amount_filter') === '10000+' ? 'selected' : '' }}>10.000 TL +</option>
                    </select>
                </div>
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.orders.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <div class="cl-per-page">
                        <label>Göster:</label>
                        <select name="per_page" id="perPage" onchange="document.getElementById('filterForm').submit()">
                            @foreach([10, 25, 50, 100] as $pp)
                                <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cl-bulk-actions d-none" id="bulkActions">
                        <span class="cl-bulk-count"><span id="selectedCount">0</span> seçili</span>
                        <button type="button" class="usr-action-btn success" onclick="bulkStatusUpdate('processing')" title="Hazırlanıyor"><i class="bi bi-gear"></i></button>
                        <button type="button" class="usr-action-btn" onclick="bulkStatusUpdate('shipped')" title="Kargoya Ver"><i class="bi bi-truck"></i></button>
                        <button type="button" class="usr-action-btn danger" onclick="bulkStatusUpdate('cancelled')" title="İptal Et"><i class="bi bi-x-circle"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <!-- ==================== SECTION 4: ORDERS TABLE ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th class="cl-th-checkbox">
                                <input type="checkbox" class="usr-checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            </th>
                            <th>Sipariş</th>
                            <th>Müşteri</th>
                            <th class="d-none d-lg-table-cell">Ürünler</th>
                            <th>Tutar</th>
                            <th>Durum</th>
                            <th class="d-none d-xxl-table-cell">Tarih</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                        @forelse($orders as $order)
                            @php
                                $statusCssMap = [
                                    'pending' => 'ord-status-pending',
                                    'processing' => 'ord-status-processing',
                                    'shipped' => 'ord-status-shipped',
                                    'delivered' => 'ord-status-delivered',
                                    'cancelled' => 'ord-status-cancelled',
                                ];
                                $statusIconMap = [
                                    'pending' => 'bi-clock-fill',
                                    'processing' => 'bi-gear-fill',
                                    'shipped' => 'bi-truck',
                                    'delivered' => 'bi-check-circle-fill',
                                    'cancelled' => 'bi-x-circle-fill',
                                ];
                                $statusVal = $order->status->value;
                                $customerName = $order->user?->name ?? $order->guest_name ?? $order->shipping_name;
                                $customerInitials = collect(explode(' ', $customerName))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('+');
                            @endphp
                            <tr data-status="{{ $statusVal }}" data-amount="{{ (int) $order->total }}">
                                <td data-label="Seç"><input type="checkbox" class="usr-checkbox order-checkbox" value="{{ $order->id }}" onchange="updateBulk()"></td>
                                <td data-label="Sipariş">
                                    <span class="ord-id">#{{ $order->order_number }}</span>
                                </td>
                                <td data-label="Müşteri">
                                    <div class="cl-author-cell">
                                        <img src="https://ui-avatars.com/api/?name={{ $customerInitials }}&background=14b8a6&color=fff&size=32" alt="{{ $customerName }}" loading="lazy">
                                        <div>
                                            <span class="ord-customer-name">
                                                {{ $customerName }}
                                                @if($order->isGuest())
                                                    <span class="badge bg-warning-subtle text-warning ms-1" style="font-size:10px;">Misafir</span>
                                                @endif
                                            </span>
                                            <span class="ord-customer-email">{{ $order->user?->email ?? $order->guest_email ?? $order->shipping_phone }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Ürünler" class="d-none d-lg-table-cell">
                                    <div class="ord-products">
                                        <span class="ord-product-count">{{ $order->items_count }} ürün</span>
                                    </div>
                                </td>
                                <td data-label="Tutar">
                                    <span class="ord-amount {{ $statusVal === 'cancelled' ? 'ord-amount-cancelled' : '' }}">{{ number_format((float) $order->total, 0, ',', '.') }} ₺</span>
                                    @if($order->discount_amount > 0)
                                        <br><small class="text-neon-green">-{{ number_format((float) $order->discount_amount, 0, ',', '.') }} ₺</small>
                                    @endif
                                </td>
                                <td data-label="Durum">
                                    <span class="ord-status-badge {{ $statusCssMap[$statusVal] ?? '' }}"><i class="bi {{ $statusIconMap[$statusVal] ?? '' }}"></i> {{ $order->status->label() }}</span>
                                </td>
                                <td data-label="Tarih" class="d-none d-xxl-table-cell">
                                    <span class="usr-meta">{{ $order->created_at->translatedFormat('d M Y H:i') }}</span>
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        <a href="{{ route('admin.orders.show', $order) }}" class="usr-action-btn" title="Detay"><i class="bi bi-eye"></i></a>
                                        <button class="usr-action-btn" title="Durum" onclick="openStatusModal({{ $order->id }}, '{{ $order->order_number }}', '{{ $statusVal }}')"><i class="bi bi-arrow-repeat"></i></button>
                                        <button class="usr-action-btn danger" title="Sil" onclick="openDeleteModal({{ $order->id }}, '{{ $order->order_number }}')"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-cart-x d-block fs-1 mb-2"></i>
                                    Sipariş bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.admin.pagination', ['paginator' => $orders, 'itemLabel' => 'sipariş'])
    </div>


    <!-- ==================== STATUS CHANGE MODAL ==================== -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title prd-modal-title">Durum Güncelle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <p class="cl-modal-body-text mb-3">Sipariş <strong id="statusOrderNumber"></strong> için yeni durum seçin:</p>
                    <form method="POST" id="statusForm">
                        @csrf
                        @method('PATCH')
                        <div class="ord-status-options">
                            @foreach(\App\Enums\OrderStatus::cases() as $status)
                                @php
                                    $radioIconMap = [
                                        'pending' => 'bi-clock text-neon-orange',
                                        'processing' => 'bi-gear text-neon-blue',
                                        'shipped' => 'bi-truck text-neon-purple',
                                        'delivered' => 'bi-check-circle text-neon-green',
                                        'cancelled' => 'bi-x-circle text-neon-red',
                                    ];
                                @endphp
                                <label class="ord-status-option">
                                    <input type="radio" name="status" value="{{ $status->value }}">
                                    <span class="ord-status-option-label"><i class="bi {{ $radioIconMap[$status->value] ?? '' }}"></i> {{ $status->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="d-flex gap-2 justify-content-end mt-3">
                            <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn-teal">
                                <i class="bi bi-check-lg me-1"></i> Güncelle
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/orders.js') }}"></script>
@endpush
