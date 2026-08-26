@extends('layouts.admin')

@section('title', 'Sayfalar')
@section('page_title', 'Sayfalar')
@section('page_description', 'Statik sayfa yönetimi')

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Sayfalar</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Sayfalar</h1>
            <p class="page-subtitle">Statik sayfaları listeleyin, düzenleyin ve yönetin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.pages.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg"></i> Yeni Sayfa
            </a>
        </div>
    </div>


    <!-- ==================== SECTION 1: STAT CARDS ==================== -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-file-earmark-text-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Sayfa</span>
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
                    <span class="usr-stat-label">Yayında</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['published'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-pencil-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Taslak</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['draft'] }}">0</h3>
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
        <a href="{{ route('admin.pages.index', request()->except(['status', 'page'])) }}"
           class="cl-status-tab {{ $currentStatus === '' ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $totalCount }}</span>
        </a>
        <a href="{{ route('admin.pages.index', array_merge(request()->except(['status', 'page']), ['status' => 'published'])) }}"
           class="cl-status-tab {{ $currentStatus === 'published' ? 'active' : '' }}">
            <i class="bi bi-check-circle text-neon-green"></i>
            <span>Yayında</span>
            <span class="cl-tab-count">{{ $statusCounts['published'] ?? 0 }}</span>
        </a>
        <a href="{{ route('admin.pages.index', array_merge(request()->except(['status', 'page']), ['status' => 'draft'])) }}"
           class="cl-status-tab {{ $currentStatus === 'draft' ? 'active' : '' }}">
            <i class="bi bi-pencil text-neon-orange"></i>
            <span>Taslak</span>
            <span class="cl-tab-count">{{ $statusCounts['draft'] ?? 0 }}</span>
        </a>
        <a href="{{ route('admin.pages.index', array_merge(request()->except(['status', 'page']), ['status' => 'archived'])) }}"
           class="cl-status-tab {{ $currentStatus === 'archived' ? 'active' : '' }}">
            <i class="bi bi-archive text-neon-blue"></i>
            <span>Arşiv</span>
            <span class="cl-tab-count">{{ $statusCounts['archived'] ?? 0 }}</span>
        </a>
        <a href="{{ route('admin.pages.index', array_merge(request()->except(['status', 'page']), ['status' => 'trashed'])) }}"
           class="cl-status-tab {{ $currentStatus === 'trashed' ? 'active' : '' }}">
            <i class="bi bi-trash text-neon-red"></i>
            <span>Silinmiş</span>
            <span class="cl-tab-count">{{ $statusCounts['trashed'] ?? 0 }}</span>
        </a>
    </div>


    <!-- ==================== SECTION 3: FILTERS & TOOLBAR ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.pages.index') }}" class="cl-toolbar" id="filterForm">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" id="pageSearch" placeholder="Sayfa adı veya slug ile ara..." value="{{ request('search') }}">
                </div>
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.pages.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
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
                            <th>Sayfa</th>
                            <th>Durum</th>
                            <th class="d-none d-md-table-cell">Sıra</th>
                            <th class="d-none d-lg-table-cell">Yayın Tarihi</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="pagesTableBody">
                        @forelse($pages as $page)
                            <tr data-status="{{ $page->trashed() ? 'trashed' : $page->status->value }}">
                                <td data-label="Seç"><input type="checkbox" class="usr-checkbox page-checkbox" value="{{ $page->id }}" onchange="updateBulk()"></td>
                                <td data-label="Sayfa">
                                    <div class="cl-content-info">
                                        <span class="cl-content-title">{{ $page->title }}</span>
                                        <span class="cl-content-meta"><i class="bi bi-link-45deg me-1"></i>/{{ $page->slug }}</span>
                                        <x-language-badges :locales="$page->group_locales ?? []" />
                                    </div>
                                </td>
                                <td data-label="Durum">
                                    @if($page->trashed())
                                        <span class="usr-status-badge inactive">Silinmiş</span>
                                    @elseif($page->status === \App\Enums\ContentStatus::Published)
                                        <span class="usr-status-badge active">Yayında</span>
                                    @elseif($page->status === \App\Enums\ContentStatus::Draft)
                                        <span class="usr-status-badge pending">Taslak</span>
                                    @elseif($page->status === \App\Enums\ContentStatus::Archived)
                                        <span class="usr-status-badge inactive">Arşiv</span>
                                    @elseif($page->status === \App\Enums\ContentStatus::Scheduled)
                                        <span class="usr-status-badge pending">Zamanlanmış</span>
                                    @endif
                                </td>
                                <td data-label="Sıra" class="d-none d-md-table-cell">
                                    <span class="usr-meta">{{ $page->sort_order }}</span>
                                </td>
                                <td data-label="Yayın Tarihi" class="d-none d-lg-table-cell">
                                    @if($page->published_at)
                                        <div class="cl-content-info">
                                            <span class="usr-meta">{{ $page->published_at->format('d.m.Y') }}</span>
                                            <span class="cl-content-meta">{{ $page->published_at->format('H:i') }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        @if($page->trashed())
                                            <form method="POST" action="{{ route('admin.pages.restore', $page->id) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="usr-action-btn success" title="Geri Yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                        @else
                                            <a href="{{ route('admin.pages.edit', $page) }}" class="usr-action-btn" title="Düzenle"><i class="bi bi-pencil"></i></a>
                                            <button class="usr-action-btn danger" title="Sil" onclick="openDeleteModal({{ $page->id }}, '{{ addslashes($page->title) }}')"><i class="bi bi-trash"></i></button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-file-earmark-text d-block fs-1 mb-2"></i>
                                    Sayfa bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.admin.pagination', ['paginator' => $pages, 'itemLabel' => 'sayfa'])
    </div>

@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/pages.js') }}"></script>
@endpush
