@extends('layouts.admin')

@section('title', 'Sliderlar')
@section('page_title', 'Sliderlar')
@section('page_description', 'Anasayfa slider yönetimi')

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Sliderlar</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Sliderlar</h1>
            <p class="page-subtitle">Anasayfa slider görsellerini yönetin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.sliders.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg"></i> Yeni Slider
            </a>
        </div>
    </div>


    <!-- ==================== SECTION 1: STAT CARDS ==================== -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-images"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Slider</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Aktif</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['active'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-pause-circle-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Pasif</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['passive'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red">
                    <i class="bi bi-trash-fill"></i>
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
        <a href="{{ route('admin.sliders.index', request()->except(['status', 'page'])) }}"
           class="cl-status-tab {{ $currentStatus === '' ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $totalCount }}</span>
        </a>
        <a href="{{ route('admin.sliders.index', array_merge(request()->except(['status', 'page']), ['status' => 'active'])) }}"
           class="cl-status-tab {{ $currentStatus === 'active' ? 'active' : '' }}">
            <i class="bi bi-check-circle text-neon-green"></i>
            <span>Aktif</span>
            <span class="cl-tab-count">{{ $statusCounts['active'] ?? 0 }}</span>
        </a>
        <a href="{{ route('admin.sliders.index', array_merge(request()->except(['status', 'page']), ['status' => 'passive'])) }}"
           class="cl-status-tab {{ $currentStatus === 'passive' ? 'active' : '' }}">
            <i class="bi bi-pause-circle text-neon-orange"></i>
            <span>Pasif</span>
            <span class="cl-tab-count">{{ $statusCounts['passive'] ?? 0 }}</span>
        </a>
        <a href="{{ route('admin.sliders.index', array_merge(request()->except(['status', 'page']), ['status' => 'trashed'])) }}"
           class="cl-status-tab {{ $currentStatus === 'trashed' ? 'active' : '' }}">
            <i class="bi bi-trash text-neon-red"></i>
            <span>Silinmiş</span>
            <span class="cl-tab-count">{{ $statusCounts['trashed'] ?? 0 }}</span>
        </a>
    </div>


    <!-- ==================== SECTION 3: FILTERS & TOOLBAR ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.sliders.index') }}" class="cl-toolbar" id="filterForm">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" id="sliderSearch" placeholder="Slider başlığı ile ara..." value="{{ request('search') }}">
                </div>
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.sliders.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
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
                        <button type="button" class="usr-action-btn danger" onclick="openBulkDeleteModal()" title="Sil"><i class="bi bi-trash"></i></button>
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
                            <th>Görsel</th>
                            <th>Başlık</th>
                            <th class="d-none d-md-table-cell">Buton</th>
                            <th>Durum</th>
                            <th class="d-none d-lg-table-cell">Sıra</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="slidersTableBody">
                        @forelse($sliders as $slider)
                            <tr data-status="{{ $slider->trashed() ? 'trashed' : ($slider->is_active ? 'active' : 'passive') }}">
                                <td data-label="Seç"><input type="checkbox" class="usr-checkbox slider-checkbox" value="{{ $slider->id }}" onchange="updateBulk()"></td>
                                <td data-label="Görsel">
                                    @if($slider->image)
                                        <img src="{{ upload_url($slider->image, 'thumb') }}"
                                             alt="{{ $slider->title }}"
                                             class="rounded" width="80" height="40" loading="lazy">
                                    @else
                                        <div class="d-flex align-items-center justify-content-center rounded bg-dark slider-thumb-placeholder">
                                            <i class="bi bi-image text-muted"></i>
                                        </div>
                                    @endif
                                </td>
                                <td data-label="Başlık">
                                    <div class="cl-content-info">
                                        <span class="cl-content-title">{{ $slider->title }}</span>
                                        @if($slider->subtitle)
                                            <span class="cl-content-meta">{{ Str::limit($slider->subtitle, 40) }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Buton" class="d-none d-md-table-cell">
                                    @if($slider->button_text)
                                        <span class="cl-category-badge tech">{{ $slider->button_text }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="Durum">
                                    @if($slider->trashed())
                                        <span class="usr-status-badge inactive">Silinmiş</span>
                                    @elseif($slider->is_active)
                                        <span class="usr-status-badge active">Aktif</span>
                                    @else
                                        <span class="usr-status-badge pending">Pasif</span>
                                    @endif
                                </td>
                                <td data-label="Sıra" class="d-none d-lg-table-cell">
                                    <span class="usr-meta">{{ $slider->sort_order }}</span>
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        @if($slider->trashed())
                                            <form method="POST" action="{{ route('admin.sliders.restore', $slider->id) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="usr-action-btn success" title="Geri Yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                        @else
                                            <a href="{{ route('admin.sliders.edit', $slider) }}" class="usr-action-btn" title="Düzenle"><i class="bi bi-pencil"></i></a>
                                            <button class="usr-action-btn danger" title="Sil" onclick="openDeleteModal({{ $slider->id }}, '{{ addslashes($slider->title) }}')"><i class="bi bi-trash"></i></button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-images d-block fs-1 mb-2"></i>
                                    Slider bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.admin.pagination', ['paginator' => $sliders, 'itemLabel' => 'slider'])
    </div>

@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/sliders.js') }}"></script>
@endpush
