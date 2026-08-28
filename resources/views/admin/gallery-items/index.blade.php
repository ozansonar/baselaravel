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
            <a href="{{ route('admin.gallery-items.bulk.create') }}" class="btn-glass">
                <i class="bi bi-images"></i> Toplu Yükleme
            </a>
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

                    {{-- Görünüm: tablo ↔ ızgara. Galeri görsellerden oluşuyor;
                         ızgara, tabloda küçük kalan kareyi asıl içerik hâline
                         getiriyor. Tercih adreste taşındığı için sayfalama ve
                         süzgeçlerle birlikte korunuyor. --}}
                    <div class="gl-view-toggle" role="group" aria-label="Görünüm">
                        <a href="{{ route('admin.gallery-items.index', array_merge(request()->except(['view', 'page']), ['view' => 'table'])) }}"
                           class="gl-view-btn {{ $viewMode === 'table' ? 'active' : '' }}"
                           title="Tablo görünümü" aria-label="Tablo görünümü"
                           @if($viewMode === 'table') aria-current="true" @endif>
                            <i class="bi bi-list-ul"></i>
                        </a>
                        <a href="{{ route('admin.gallery-items.index', array_merge(request()->except(['view', 'page']), ['view' => 'grid'])) }}"
                           class="gl-view-btn {{ $viewMode === 'grid' ? 'active' : '' }}"
                           title="Izgara görünümü" aria-label="Izgara görünümü"
                           @if($viewMode === 'grid') aria-current="true" @endif>
                            <i class="bi bi-grid-3x3-gap"></i>
                        </a>
                    </div>

                    {{-- Seçim yapılınca beliriyor. Düğmeler type=button: bu
                         çubuk süzgeç formunun içinde duruyor, aksi hâlde
                         tıklama listeyi yeniden süzerdi. --}}
                    <div class="cl-bulk-actions d-none" id="bulkActions">
                        <span class="cl-bulk-count"><span id="selectedCount">0</span> seçili</span>
                        @if(request('status') === 'trashed')
                            <button type="button" class="usr-action-btn success" onclick="bulkGalleryAction('restore')" title="Geri Yükle">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        @else
                            <button type="button" class="usr-action-btn danger" onclick="bulkGalleryAction('delete')" title="Seçilenleri Sil">
                                <i class="bi bi-trash"></i>
                            </button>
                        @endif
                        <button type="button" class="usr-action-btn" onclick="clearGallerySelection()" title="Seçimi Bırak">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- SECTION 4: LIST --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        @if($viewMode === 'table')
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th class="cl-th-checkbox">
                                <input type="checkbox" class="usr-checkbox" id="selectAll"
                                       onchange="toggleSelectAll(this)" aria-label="Tümünü seç" data-fv-ignore>
                            </th>
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
                    <tbody id="galleryTableBody">
                        @forelse($items as $item)
                            <tr>
                                <td data-label="Seç">
                                    <input type="checkbox" class="usr-checkbox gallery-checkbox"
                                           value="{{ $item->id }}" onchange="updateBulk()"
                                           aria-label="{{ $item->title }} seç" data-fv-ignore>
                                </td>
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
                                <td colspan="9" class="text-center text-muted py-4">
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
        @else
        {{-- Izgara görünümü: kare asıl içerik olduğu için öne çıkıyor, başlık
             ve durum onun altında. Seçim kutuları tablodakiyle aynı sınıfı
             taşıyor; toplu işlem iki görünümde de aynı kodla çalışıyor. --}}
        <div class="card-body-custom">
            @if($items->isNotEmpty())
                <div class="gl-grid" id="galleryGrid">
                    @foreach($items as $item)
                        <article class="gl-card {{ $item->trashed() ? 'gl-card--trashed' : '' }}">
                            <label class="gl-card__pick">
                                <input type="checkbox" class="usr-checkbox gallery-checkbox"
                                       value="{{ $item->id }}" onchange="updateBulk()"
                                       aria-label="{{ $item->title }} seç" data-fv-ignore>
                            </label>

                            <div class="gl-card__media">
                                @if($item->image)
                                    <a href="{{ upload_url($item->image) }}" class="glightbox"
                                       data-gallery="galeri-listesi" data-title="{{ $item->title }}">
                                        <img src="{{ upload_url($item->image, 'md') }}" alt="{{ $item->title }}" loading="lazy">
                                    </a>
                                @else
                                    <span class="gl-card__placeholder"><i class="bi bi-image"></i></span>
                                @endif

                                @if($item->type === \App\Enums\GalleryType::Video)
                                    <span class="gl-card__type"><i class="bi bi-play-fill"></i></span>
                                @endif

                                @if($item->trashed())
                                    <span class="usr-status-badge inactive gl-card__status">Silinmiş</span>
                                @elseif($item->is_active)
                                    <span class="usr-status-badge active gl-card__status">Aktif</span>
                                @else
                                    <span class="usr-status-badge pending gl-card__status">Pasif</span>
                                @endif
                            </div>

                            <div class="gl-card__body">
                                <span class="gl-card__title" title="{{ $item->title }}">{{ Str::limit($item->title, 40) }}</span>
                                <span class="gl-card__meta">
                                    @if($item->galleryCategory)
                                        <i class="bi bi-folder me-1"></i>{{ $item->galleryCategory->name }}
                                    @else
                                        <i class="bi bi-folder me-1"></i>Kategorisiz
                                    @endif
                                </span>
                                <x-language-badges :locales="$item->group_locales ?? []" />
                            </div>

                            <div class="gl-card__actions">
                                @if($item->trashed())
                                    <form method="POST" action="{{ route('admin.gallery-items.restore', $item->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="usr-action-btn success" title="Geri Yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    </form>
                                @else
                                    <a class="usr-action-btn" title="Düzenle" href="{{ route('admin.gallery-items.edit', $item) }}"><i class="bi bi-pencil"></i></a>
                                    <button type="button" class="usr-action-btn danger" title="Sil"
                                            onclick="openDeleteModal('{{ e($item->title) }}', {{ $item->id }})"><i class="bi bi-trash"></i></button>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="gl-empty">
                    <i class="bi bi-images"></i>
                    <p class="mb-3">Henüz galeri öğesi eklenmemiş.</p>
                    <a href="{{ route('admin.gallery-items.create') }}" class="btn-teal d-inline-flex">
                        <i class="bi bi-plus-lg me-1"></i> İlk Öğeyi Ekle
                    </a>
                </div>
            @endif
        </div>
        @endif

        @include('partials.admin.pagination', ['paginator' => $items, 'itemLabel' => 'öğe'])
    </div>

    {{-- Toplu işlem formları: seçim kutuları listenin içinde, formlar dışında.
         İç içe form olmasın diye ayrı duruyorlar; kimlikleri JS dolduruyor. --}}
    <form method="POST" action="{{ route('admin.gallery-items.bulk-destroy') }}" id="bulkDeleteForm" class="d-none">
        @csrf
        @method('DELETE')
    </form>
    <form method="POST" action="{{ route('admin.gallery-items.bulk-restore') }}" id="bulkRestoreForm" class="d-none">
        @csrf
        @method('PATCH')
    </form>

    {{-- BULK DELETE MODAL --}}
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="status-modal-icon danger">
                        <i class="bi bi-trash"></i>
                    </div>
                    <h5 class="cl-modal-heading">Toplu Silme Onayı</h5>
                    <p class="cl-modal-body-text"><strong id="bulkDeleteCount">0</strong> galeri öğesini silmek istediğinizden emin misiniz?</p>
                    <p class="cl-modal-warning"><i class="bi bi-exclamation-circle me-1"></i>Silinenler "Silinmiş" sekmesinden geri alınabilir.</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                        <button type="button" class="btn-teal btn-danger-gradient" data-bs-dismiss="modal" onclick="confirmBulkDelete()">
                            <i class="bi bi-trash"></i> Evet, Sil
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/vendor/glightbox/css/glightbox.min.css') }}">
@endpush

@push('scripts')
<script src="{{ versioned_asset('assets/vendor/glightbox/js/glightbox.min.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/gallery-list.js') }}"></script>
@endpush
