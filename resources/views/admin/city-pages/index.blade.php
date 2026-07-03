@extends('layouts.admin')

@section('title', 'Şehir Sayfaları')
@section('page_title', 'Şehir Sayfaları')
@section('page_description', 'Şehir bazlı SEO landing page yönetimi')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Şehir Sayfaları</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Şehir Sayfaları</h1>
            <p class="page-subtitle">Şehir bazlı landing page'leri yönetin (SEO için "{şehir} köy ürünleri" aramalarına yönelik)</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.city-pages.bulk-create') }}" class="btn-glass">
                <i class="bi bi-collection"></i> Toplu Oluştur (AI)
            </a>
            <a href="{{ route('admin.city-pages.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg"></i> Yeni Şehir Sayfası
            </a>
        </div>
    </div>

    {{-- SECTION 1: STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Şehir</span>
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
                    <h3 class="usr-stat-value" data-count="{{ $stats['inactive'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple">
                    <i class="bi bi-trash-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Silinmiş</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['trashed'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 2: STATUS TABS --}}
    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        <a href="{{ route('admin.city-pages.index', request()->except(['status', 'page'])) }}"
           class="cl-status-tab {{ !request('status') ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $statusCounts['all'] }}</span>
        </a>
        <a href="{{ route('admin.city-pages.index', array_merge(request()->except('page'), ['status' => 'active'])) }}"
           class="cl-status-tab {{ request('status') === 'active' ? 'active' : '' }}">
            <i class="bi bi-check-circle text-neon-green"></i>
            <span>Yayında</span>
            <span class="cl-tab-count">{{ $statusCounts['active'] }}</span>
        </a>
        <a href="{{ route('admin.city-pages.index', array_merge(request()->except('page'), ['status' => 'inactive'])) }}"
           class="cl-status-tab {{ request('status') === 'inactive' ? 'active' : '' }}">
            <i class="bi bi-pause-circle text-neon-orange"></i>
            <span>Pasif</span>
            <span class="cl-tab-count">{{ $statusCounts['inactive'] }}</span>
        </a>
        @if($statusCounts['trashed'] > 0)
            <a href="{{ route('admin.city-pages.index', array_merge(request()->except('page'), ['status' => 'trashed'])) }}"
               class="cl-status-tab {{ request('status') === 'trashed' ? 'active' : '' }}">
                <i class="bi bi-trash text-neon-red"></i>
                <span>Silinmiş</span>
                <span class="cl-tab-count">{{ $statusCounts['trashed'] }}</span>
            </a>
        @endif
    </div>

    {{-- SECTION 3: FILTERS & TOOLBAR --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.city-pages.index') }}" id="filterForm" class="cl-toolbar">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif

                {{-- Search --}}
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Şehir adı, slug veya başlıkta ara...">
                </div>

                {{-- Filters Row --}}
                <div class="cl-filters">
                    <select class="cl-filter-select" name="tier" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Tier'lar</option>
                        @foreach([1, 2, 3, 4] as $t)
                            <option value="{{ $t }}" {{ (int) request('tier') === $t ? 'selected' : '' }}>
                                Tier {{ $t }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Toolbar Actions --}}
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.city-pages.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <div class="cl-per-page">
                        <label>Göster:</label>
                        <select name="per_page" onchange="document.getElementById('filterForm').submit()">
                            @foreach([10, 25, 50, 100] as $pp)
                                <option value="{{ $pp }}" {{ (int) request('per_page', 25) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- SECTION 4: TABLE --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>Şehir</th>
                            <th class="d-none d-md-table-cell">Bölge</th>
                            <th class="d-none d-md-table-cell">Tier</th>
                            <th class="d-none d-lg-table-cell">URL</th>
                            <th>Durum</th>
                            <th class="d-none d-xl-table-cell">Sıra</th>
                            <th class="d-none d-xxl-table-cell">Güncelleme</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pages as $page)
                            @php
                                $statusBadge = $page->is_active ? 'active' : 'pending';
                                $statusLabel = $page->is_active ? 'Yayında' : 'Pasif';
                                if ($page->trashed()) {
                                    $statusBadge = 'inactive';
                                    $statusLabel = 'Silinmiş';
                                }
                                $publicUrl = url('/' . $page->city_slug . '-koy-urunleri');
                            @endphp
                            <tr>
                                <td data-label="Şehir">
                                    <div class="cl-content-cell">
                                        <div class="cl-content-thumb draft"><i class="bi bi-geo-alt-fill"></i></div>
                                        <div class="cl-content-info">
                                            @if($page->is_active && !$page->trashed())
                                                <a href="{{ $publicUrl }}" target="_blank" class="cl-content-title cl-content-link">
                                                    {{ $page->city_name }} <i class="bi bi-box-arrow-up-right cl-link-icon"></i>
                                                </a>
                                            @else
                                                <span class="cl-content-title">{{ $page->city_name }}</span>
                                            @endif
                                            <span class="cl-content-meta">
                                                <i class="bi bi-tag me-1"></i>{{ Str::limit($page->title, 60) }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Bölge" class="d-none d-md-table-cell">
                                    @if($page->region)
                                        <span class="cl-category-badge">{{ $page->region }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="Tier" class="d-none d-md-table-cell">
                                    <span class="cl-category-badge">Tier {{ $page->tier }}</span>
                                </td>
                                <td data-label="URL" class="d-none d-lg-table-cell">
                                    <code class="text-muted">/{{ $page->city_slug }}-koy-urunleri</code>
                                </td>
                                <td data-label="Durum">
                                    <span class="usr-status-badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                                </td>
                                <td data-label="Sıra" class="d-none d-xl-table-cell">
                                    <span class="text-muted">{{ $page->sort_order }}</span>
                                </td>
                                <td data-label="Güncelleme" class="d-none d-xxl-table-cell">
                                    <span class="usr-meta">{{ $page->updated_at->translatedFormat('d M Y H:i') }}</span>
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        @if($page->trashed())
                                            <form method="POST" action="{{ route('admin.city-pages.restore', $page->id) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="usr-action-btn success" title="Geri Yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                        @else
                                            @if($page->is_active)
                                                <a class="usr-action-btn success" title="Sitede Görüntüle" href="{{ $publicUrl }}" target="_blank"><i class="bi bi-box-arrow-up-right"></i></a>
                                            @endif
                                            <a class="usr-action-btn" title="Düzenle" href="{{ route('admin.city-pages.edit', $page) }}"><i class="bi bi-pencil"></i></a>
                                            <button type="button" class="usr-action-btn danger" title="Sil"
                                                    onclick="openDeleteCityModal({{ $page->id }}, '{{ e($page->city_name) }}')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-geo-alt d-block fs-1 mb-2"></i>
                                    Henüz şehir sayfası eklenmemiş.
                                    <br>
                                    <a href="{{ route('admin.city-pages.create') }}" class="btn-teal mt-3 d-inline-flex">
                                        <i class="bi bi-plus-lg me-1"></i> İlk Şehir Sayfasını Oluştur
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.admin.pagination', ['paginator' => $pages, 'itemLabel' => 'şehir'])
    </div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/city-list.js') }}"></script>
@endpush
