@extends('layouts.admin')

@section('title', 'Popup Yönetimi')
@section('page_title', 'Popup Yönetimi')
@section('page_description', 'Popup modal yönetimi')

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Popup Yönetimi</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Popup Yönetimi</h1>
            <p class="page-subtitle">Site popup modallarını yönetin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.popups.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg"></i> Yeni Popup
            </a>
        </div>
    </div>


    <!-- ==================== SECTION 1: STAT CARDS ==================== -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-window-stack"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Popup</span>
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
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Zamanlanmış</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['scheduled'] }}">0</h3>
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
        <a href="{{ route('admin.popups.index', request()->except(['status', 'page'])) }}"
           class="cl-status-tab {{ $currentStatus === '' ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $totalCount }}</span>
        </a>
        <a href="{{ route('admin.popups.index', array_merge(request()->except(['status', 'page']), ['status' => 'active'])) }}"
           class="cl-status-tab {{ $currentStatus === 'active' ? 'active' : '' }}">
            <i class="bi bi-check-circle text-neon-green"></i>
            <span>Aktif</span>
            <span class="cl-tab-count">{{ $statusCounts['active'] ?? 0 }}</span>
        </a>
        <a href="{{ route('admin.popups.index', array_merge(request()->except(['status', 'page']), ['status' => 'passive'])) }}"
           class="cl-status-tab {{ $currentStatus === 'passive' ? 'active' : '' }}">
            <i class="bi bi-pause-circle text-neon-orange"></i>
            <span>Pasif</span>
            <span class="cl-tab-count">{{ $statusCounts['passive'] ?? 0 }}</span>
        </a>
        <a href="{{ route('admin.popups.index', array_merge(request()->except(['status', 'page']), ['status' => 'trashed'])) }}"
           class="cl-status-tab {{ $currentStatus === 'trashed' ? 'active' : '' }}">
            <i class="bi bi-trash text-neon-red"></i>
            <span>Silinmiş</span>
            <span class="cl-tab-count">{{ $statusCounts['trashed'] ?? 0 }}</span>
        </a>
    </div>


    <!-- ==================== SECTION 3: FILTERS & TOOLBAR ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.popups.index') }}" class="cl-toolbar" id="filterForm">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" id="popupSearch" placeholder="Popup başlığı ile ara..." value="{{ request('search') }}">
                </div>
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.popups.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
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
                            <th>Görsel</th>
                            <th>Başlık</th>
                            <th class="d-none d-md-table-cell">Boyut</th>
                            <th class="d-none d-md-table-cell">Sayfalar</th>
                            <th class="d-none d-lg-table-cell">Tarih Aralığı</th>
                            <th>Durum</th>
                            <th class="d-none d-lg-table-cell">Sıra</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($popups as $popup)
                            <tr data-status="{{ $popup->trashed() ? 'trashed' : ($popup->is_active ? 'active' : 'passive') }}">
                                <td data-label="Görsel">
                                    @if($popup->image)
                                        <img src="{{ upload_url($popup->image, 'thumb') }}"
                                             alt="{{ $popup->title }}"
                                             class="rounded" width="60" height="60" loading="lazy"
                                             style="object-fit: cover;">
                                    @else
                                        <div class="d-flex align-items-center justify-content-center rounded bg-dark" style="width:60px;height:60px;">
                                            <i class="bi bi-image text-muted"></i>
                                        </div>
                                    @endif
                                </td>
                                <td data-label="Başlık">
                                    <div class="cl-content-info">
                                        <span class="cl-content-title">{{ $popup->title }}</span>
                                        @if($popup->button_text)
                                            <span class="cl-content-meta">{{ $popup->button_text }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Boyut" class="d-none d-md-table-cell">
                                    <span class="cl-category-badge tech">{{ $popup->size->label() }}</span>
                                </td>
                                <td data-label="Sayfalar" class="d-none d-md-table-cell">
                                    @foreach($popup->pages ?? [] as $page)
                                        @php $pageEnum = \App\Enums\PopupPage::tryFrom($page); @endphp
                                        @if($pageEnum)
                                            <span class="cl-category-badge">{{ $pageEnum->label() }}</span>
                                        @endif
                                    @endforeach
                                </td>
                                <td data-label="Tarih" class="d-none d-lg-table-cell">
                                    @if($popup->start_date || $popup->end_date)
                                        <small class="usr-meta">
                                            {{ $popup->start_date?->format('d.m.Y') ?? '∞' }}
                                            —
                                            {{ $popup->end_date?->format('d.m.Y') ?? '∞' }}
                                        </small>
                                    @else
                                        <span class="cl-category-badge tech">Sürekli</span>
                                    @endif
                                </td>
                                <td data-label="Durum">
                                    @if($popup->trashed())
                                        <span class="usr-status-badge inactive">Silinmiş</span>
                                    @elseif($popup->is_active)
                                        <span class="usr-status-badge active">Aktif</span>
                                    @else
                                        <span class="usr-status-badge pending">Pasif</span>
                                    @endif
                                </td>
                                <td data-label="Sıra" class="d-none d-lg-table-cell">
                                    <span class="usr-meta">{{ $popup->sort_order }}</span>
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        @if($popup->trashed())
                                            <form method="POST" action="{{ route('admin.popups.restore', $popup->id) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="usr-action-btn success" title="Geri Yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                        @else
                                            <a href="{{ route('admin.popups.edit', $popup) }}" class="usr-action-btn" title="Düzenle"><i class="bi bi-pencil"></i></a>
                                            <button class="usr-action-btn danger" title="Sil" onclick="openDeleteModal({{ $popup->id }}, '{{ addslashes($popup->title) }}')"><i class="bi bi-trash"></i></button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-window-stack d-block fs-1 mb-2"></i>
                                    Popup bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.admin.pagination', ['paginator' => $popups, 'itemLabel' => 'popup'])
    </div>

@endsection

@push('scripts')
<script>
    function openDeleteModal(id, title) {
        AdminModal.confirm({
            title: 'Silme Onayı',
            message: '<strong>' + title + '</strong> popup\'ını silmek istediğinizden emin misiniz?',
            type: 'danger',
            confirmText: 'Evet, Sil',
            confirmIcon: 'bi bi-trash3'
        }).then(function (confirmed) {
            if (confirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '/admin/popups/' + id;
                form.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name=csrf-token]').content + '">' +
                                 '<input type="hidden" name="_method" value="DELETE">';
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>
@endpush
