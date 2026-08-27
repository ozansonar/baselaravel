@extends('layouts.admin')

@section('title', 'Galeri Yönetimi')
@section('page_title', 'Galeri Yönetimi')
@section('page_description', 'Fotoğrafları ve videoları listeleyin, filtreleyin, düzenleyin ve yönetin')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Galeri</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Galeri Yönetimi</h1>
            <p class="page-subtitle">Fotoğrafları ve videoları listeleyin, filtreleyin, düzenleyin ve yönetin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.gallery-items.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg"></i> Yeni Öğe
            </a>
        </div>
    </div>

    {{-- SECTION 1: STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-collection-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Öğe</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-image-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Fotoğraf</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['photos'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple">
                    <i class="bi bi-camera-video-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Video</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['videos'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-teal">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Aktif</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['active'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 2: STATUS TABS --}}
    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        <a href="{{ route('admin.gallery-items.index', request()->except(['status', 'page'])) }}"
           class="cl-status-tab {{ !request('status') ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $statusCounts['active'] + $statusCounts['passive'] }}</span>
        </a>
        <a href="{{ route('admin.gallery-items.index', array_merge(request()->except('page'), ['status' => 'active'])) }}"
           class="cl-status-tab {{ request('status') === 'active' ? 'active' : '' }}">
            <i class="bi bi-check-circle text-neon-green"></i>
            <span>Aktif</span>
            <span class="cl-tab-count">{{ $statusCounts['active'] }}</span>
        </a>
        <a href="{{ route('admin.gallery-items.index', array_merge(request()->except('page'), ['status' => 'passive'])) }}"
           class="cl-status-tab {{ request('status') === 'passive' ? 'active' : '' }}">
            <i class="bi bi-pause-circle text-neon-orange"></i>
            <span>Pasif</span>
            <span class="cl-tab-count">{{ $statusCounts['passive'] }}</span>
        </a>
        @if($statusCounts['trashed'] > 0)
            <a href="{{ route('admin.gallery-items.index', array_merge(request()->except('page'), ['status' => 'trashed'])) }}"
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
            <form method="GET" action="{{ route('admin.gallery-items.index') }}" id="filterForm" class="cl-toolbar">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif

                {{-- Search --}}
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="contentSearch" name="search" value="{{ request('search') }}"
                           placeholder="Başlık veya açıklama ile ara..." data-fv-ignore>
                </div>

                {{-- Filters Row --}}
                <div class="cl-filters">
                    <select class="cl-filter-select" name="type" onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                        <option value="">Tüm Türler</option>
                        @foreach($types as $type)
                            <option value="{{ $type->value }}" {{ request('type') === $type->value ? 'selected' : '' }}>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>

                    <select class="cl-filter-select" name="category" onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                        <option value="">Tüm Kategoriler</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ (int) request('category') === $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Toolbar Actions --}}
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.gallery-items.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <div class="cl-per-page">
                        <label>Göster:</label>
                        <select id="perPage" name="per_page" onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                            @foreach([10, 25, 50, 100] as $pp)
                                <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-export-menu export="gallery-items" :total="$items->total()" />
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
                            <th>Görsel</th>
                            <th>Başlık</th>
                            <th class="d-none d-md-table-cell">Tür</th>
                            <th class="d-none d-lg-table-cell">Kategori</th>
                            <th>Durum</th>
                            <th class="d-none d-xl-table-cell">Sıra</th>
                            <th class="d-none d-xxl-table-cell">Tarih</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td data-label="Görsel">
                                    <div class="cl-content-cell">
                                        @if($item->image)
                                            <div class="cl-content-thumb">
                                                <img src="{{ upload_url($item->image, 'thumb') }}" alt="{{ $item->title }}" loading="lazy">
                                                @if($item->type === \App\Enums\GalleryType::Video)
                                                    <div class="cl-thumb-badge video"><i class="bi bi-play-fill"></i></div>
                                                @endif
                                            </div>
                                        @else
                                            <div class="cl-content-thumb draft"><i class="bi bi-image"></i></div>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Başlık">
                                    <div class="cl-content-info">
                                        <span class="cl-content-title">{{ Str::limit($item->title, 50) }}</span>
                                        @if($item->description)
                                            <span class="cl-content-meta">{{ Str::limit($item->description, 60) }}</span>
                                        @endif
                                        <x-language-badges :locales="$item->group_locales ?? []" />
                                    </div>
                                </td>
                                <td data-label="Tür" class="d-none d-md-table-cell">
                                    <span class="cl-category-badge">
                                        <i class="{{ $item->type->icon() }} me-1"></i>{{ $item->type->label() }}
                                    </span>
                                </td>
                                <td data-label="Kategori" class="d-none d-lg-table-cell">
                                    @if($item->galleryCategory)
                                        <span class="cl-category-badge">{{ $item->galleryCategory->name }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="Durum">
                                    @if($item->trashed())
                                        <span class="usr-status-badge inactive">Silinmiş</span>
                                    @elseif($item->is_active)
                                        <span class="usr-status-badge active">Aktif</span>
                                    @else
                                        <span class="usr-status-badge pending">Pasif</span>
                                    @endif
                                </td>
                                <td data-label="Sıra" class="d-none d-xl-table-cell">
                                    <span class="usr-meta">{{ $item->sort_order }}</span>
                                </td>
                                <td data-label="Tarih" class="d-none d-xxl-table-cell">
                                    <span class="usr-meta">{{ $item->created_at->translatedFormat('d M Y H:i') }}</span>
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        @if($item->trashed())
                                            <form method="POST" action="{{ route('admin.gallery-items.restore', $item->id) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="usr-action-btn success" title="Geri Yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                        @else
                                            <a class="usr-action-btn" title="Düzenle" href="{{ route('admin.gallery-items.edit', $item) }}"><i class="bi bi-pencil"></i></a>
                                            <button class="usr-action-btn danger" title="Sil" onclick="openDeleteModal('{{ e($item->title) }}', {{ $item->id }})"><i class="bi bi-trash"></i></button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-images d-block fs-1 mb-2"></i>
                                    Henüz galeri öğesi eklenmemiş.
                                    <br>
                                    <a href="{{ route('admin.gallery-items.create') }}" class="btn-teal mt-3 d-inline-flex">
                                        <i class="bi bi-plus-lg me-1"></i> İlk Öğeyi Ekle
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.admin.pagination', ['paginator' => $items, 'itemLabel' => 'öğe'])
    </div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/gallery-list.js') }}"></script>
@endpush
