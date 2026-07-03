@extends('layouts.admin')

@section('title', 'Kampanyalar')
@section('page_title', 'Kampanyalar')
@section('page_description', 'Kampanya ve kupon yönetimi')

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Kampanyalar</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title">Kampanyalar</h1>
            <p class="page-subtitle">Kampanya ve kupon kodlarını yönetin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.campaigns.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg me-1"></i> Yeni Kampanya
            </a>
        </div>
    </div>


    <!-- ==================== SECTION 1: STAT CARDS ==================== -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-megaphone-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Kampanya</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-lightning-charge-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Aktif Kampanya</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['active'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-ticket-perforated-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Kullanılan Kupon</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total_usage'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red">
                    <i class="bi bi-trash3-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Silinmiş</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['trashed'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>


    <!-- ==================== SECTION 2: STATUS TABS ==================== -->
    @php
        $currentStatus = request('status', '');
        $totalCount = $stats['total'];
    @endphp

    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        <a href="{{ route('admin.campaigns.index', request()->except(['status', 'page'])) }}"
           class="cl-status-tab {{ $currentStatus === '' ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $totalCount }}</span>
        </a>
        <a href="{{ route('admin.campaigns.index', array_merge(request()->except(['status', 'page']), ['status' => 'active'])) }}"
           class="cl-status-tab {{ $currentStatus === 'active' ? 'active' : '' }}">
            <i class="bi bi-lightning-charge text-neon-green"></i>
            <span>Aktif</span>
            <span class="cl-tab-count">{{ $statusCounts['active'] ?? 0 }}</span>
        </a>
        <a href="{{ route('admin.campaigns.index', array_merge(request()->except(['status', 'page']), ['status' => 'scheduled'])) }}"
           class="cl-status-tab {{ $currentStatus === 'scheduled' ? 'active' : '' }}">
            <i class="bi bi-calendar-event text-neon-blue"></i>
            <span>Planlanmış</span>
            <span class="cl-tab-count">{{ $statusCounts['scheduled'] ?? 0 }}</span>
        </a>
        <a href="{{ route('admin.campaigns.index', array_merge(request()->except(['status', 'page']), ['status' => 'paused'])) }}"
           class="cl-status-tab {{ $currentStatus === 'paused' ? 'active' : '' }}">
            <i class="bi bi-pause-circle text-neon-orange"></i>
            <span>Duraklatılmış</span>
            <span class="cl-tab-count">{{ $statusCounts['paused'] ?? 0 }}</span>
        </a>
        <a href="{{ route('admin.campaigns.index', array_merge(request()->except(['status', 'page']), ['status' => 'ended'])) }}"
           class="cl-status-tab {{ $currentStatus === 'ended' ? 'active' : '' }}">
            <i class="bi bi-clock-history text-neon-red"></i>
            <span>Tamamlanmış</span>
            <span class="cl-tab-count">{{ $statusCounts['ended'] ?? 0 }}</span>
        </a>
        <a href="{{ route('admin.campaigns.index', array_merge(request()->except(['status', 'page']), ['status' => 'trashed'])) }}"
           class="cl-status-tab {{ $currentStatus === 'trashed' ? 'active' : '' }}">
            <i class="bi bi-trash text-neon-red"></i>
            <span>Silinmiş</span>
            <span class="cl-tab-count">{{ $statusCounts['trashed'] ?? 0 }}</span>
        </a>
    </div>


    <!-- ==================== SECTION 3: TOOLBAR ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.campaigns.index') }}" class="cl-toolbar" id="filterForm">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" id="campaignSearch" placeholder="Kampanya adı veya kod ile ara..." value="{{ request('search') }}">
                </div>
                <div class="cl-filters">
                    <select name="type" class="cl-filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Türler</option>
                        @foreach(\App\Enums\CampaignType::cases() as $type)
                            <option value="{{ $type->value }}" {{ request('type') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.campaigns.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
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
                        <button type="button" class="usr-action-btn danger" onclick="openBulkDeleteModal()" title="Toplu Sil"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <!-- ==================== SECTION 4: TABLE ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th class="cl-th-checkbox">
                                <input type="checkbox" class="usr-checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            </th>
                            <th>Kampanya</th>
                            <th>Tür</th>
                            <th>İndirim</th>
                            <th class="d-none d-lg-table-cell">Tarih Aralığı</th>
                            <th>Kullanım</th>
                            <th>Durum</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="campaignsTableBody">
                        @forelse($campaigns as $campaign)
                            @php
                                $isActive = !$campaign->trashed() && $campaign->is_active && $campaign->start_date <= now() && $campaign->end_date >= now();
                                $isScheduled = !$campaign->trashed() && $campaign->is_active && $campaign->start_date > now();
                                $isEnded = !$campaign->trashed() && $campaign->end_date < now();
                                $isPaused = !$campaign->trashed() && !$campaign->is_active && $campaign->end_date >= now();
                            @endphp
                            <tr>
                                <td data-label="Seç"><input type="checkbox" class="usr-checkbox campaign-checkbox" value="{{ $campaign->id }}" onchange="updateBulk()"></td>
                                <td data-label="Kampanya">
                                    <div class="cl-content-info">
                                        <span class="cl-content-title">{{ $campaign->name }}</span>
                                        @if($campaign->code)
                                            <span class="cl-content-meta"><i class="bi bi-tag me-1"></i><code>{{ $campaign->code }}</code></span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Tür">
                                    <span class="usr-status-badge {{ $campaign->type === \App\Enums\CampaignType::Discount ? 'pending' : ($campaign->type === \App\Enums\CampaignType::Coupon ? 'info' : ($campaign->type === \App\Enums\CampaignType::FlashSale ? 'inactive' : 'active')) }}">
                                        <i class="bi bi-{{ $campaign->type->icon() }} me-1"></i>{{ $campaign->type->label() }}
                                    </span>
                                </td>
                                <td data-label="İndirim">
                                    @if($campaign->discount_type === 'percentage')
                                        <span class="fw-semibold text-teal">%{{ (int) $campaign->discount_value }}</span>
                                    @else
                                        <span class="fw-semibold text-teal">{{ number_format((float) $campaign->discount_value, 2) }} ₺</span>
                                    @endif
                                </td>
                                <td data-label="Tarih" class="d-none d-lg-table-cell">
                                    <div class="cl-content-info">
                                        <span class="usr-meta">{{ $campaign->start_date->format('d.m.Y') }}</span>
                                        <span class="cl-content-meta">{{ $campaign->end_date->format('d.m.Y') }}</span>
                                    </div>
                                </td>
                                <td data-label="Kullanım">
                                    <span>{{ $campaign->used_count }}</span>
                                    @if($campaign->usage_limit)
                                        <span class="text-clr-secondary">/ {{ $campaign->usage_limit }}</span>
                                    @endif
                                </td>
                                <td data-label="Durum">
                                    @if($campaign->trashed())
                                        <span class="usr-status-badge inactive">Silinmiş</span>
                                    @elseif($isActive)
                                        <span class="usr-status-badge active">Aktif</span>
                                    @elseif($isScheduled)
                                        <span class="usr-status-badge info">Planlanmış</span>
                                    @elseif($isPaused)
                                        <span class="usr-status-badge pending">Duraklatılmış</span>
                                    @elseif($isEnded)
                                        <span class="usr-status-badge inactive">Tamamlanmış</span>
                                    @endif
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        @if($campaign->trashed())
                                            <form method="POST" action="{{ route('admin.campaigns.restore', $campaign->id) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="usr-action-btn success" title="Geri Yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                        @else
                                            <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="usr-action-btn" title="Düzenle"><i class="bi bi-pencil"></i></a>
                                            <button class="usr-action-btn danger" title="Sil" onclick="openDeleteModal({{ $campaign->id }}, '{{ addslashes($campaign->name) }}')"><i class="bi bi-trash"></i></button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-megaphone d-block fs-1 mb-2"></i>
                                    Kampanya bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.admin.pagination', ['paginator' => $campaigns, 'itemLabel' => 'kampanya'])
    </div>


@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/campaigns.js') }}"></script>
@endpush
