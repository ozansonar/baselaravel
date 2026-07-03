@extends('layouts.admin')

@section('title', 'Ürün Yönetimi')
@section('page_title', 'Ürün Yönetimi')
@section('page_description', 'Tüm ürünleri listeleyin, filtreleyin, düzenleyin ve yönetin')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Ürünler</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Ürün Yönetimi</h1>
            <p class="page-subtitle">Tüm ürünleri listeleyin, filtreleyin, düzenleyin ve yönetin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.products.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg"></i> Yeni Ürün
            </a>
        </div>
    </div>

    {{-- SECTION 1: STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-box-seam-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Ürün</span>
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
                    <span class="usr-stat-label">Aktif Ürün</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['active'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Stok Azalan</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['low_stock'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple">
                    <i class="bi bi-x-circle-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Stok Tükenen</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['out_of_stock'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 2: STATUS TABS --}}
    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        <a href="{{ route('admin.products.index', request()->except(['status', 'page'])) }}"
           class="cl-status-tab {{ !request('status') ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $statusCounts['all'] }}</span>
        </a>
        <a href="{{ route('admin.products.index', array_merge(request()->except('page'), ['status' => 'active'])) }}"
           class="cl-status-tab {{ request('status') === 'active' ? 'active' : '' }}">
            <i class="bi bi-check-circle text-neon-green"></i>
            <span>Aktif</span>
            <span class="cl-tab-count">{{ $statusCounts['active'] }}</span>
        </a>
        <a href="{{ route('admin.products.index', array_merge(request()->except('page'), ['status' => 'draft'])) }}"
           class="cl-status-tab {{ request('status') === 'draft' ? 'active' : '' }}">
            <i class="bi bi-file-earmark text-neon-orange"></i>
            <span>Taslak</span>
            <span class="cl-tab-count">{{ $statusCounts['draft'] }}</span>
        </a>
        <a href="{{ route('admin.products.index', array_merge(request()->except('page'), ['status' => 'inactive'])) }}"
           class="cl-status-tab {{ request('status') === 'inactive' ? 'active' : '' }}">
            <i class="bi bi-x-circle text-neon-red"></i>
            <span>Pasif</span>
            <span class="cl-tab-count">{{ $statusCounts['inactive'] }}</span>
        </a>
    </div>

    {{-- SECTION 3: FILTERS & TOOLBAR --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.products.index') }}" id="filterForm" class="cl-toolbar">
                {{-- Preserve status tab --}}
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif

                {{-- Search --}}
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="productSearch" name="search" value="{{ request('search') }}"
                           placeholder="Ürün adı, SKU veya marka ile ara...">
                </div>

                {{-- Filters Row --}}
                <div class="cl-filters">
                    <select class="cl-filter-select" id="filterCategory" name="category_id" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Kategoriler</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>

                    <select class="cl-filter-select" id="filterStock" name="stock" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Stok</option>
                        <option value="instock" {{ request('stock') === 'instock' ? 'selected' : '' }}>Stokta</option>
                        <option value="lowstock" {{ request('stock') === 'lowstock' ? 'selected' : '' }}>Stok Azalıyor</option>
                        <option value="outofstock" {{ request('stock') === 'outofstock' ? 'selected' : '' }}>Tükendi</option>
                    </select>

                    <select class="cl-filter-select" id="filterFeatured" name="is_featured" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Ürünler</option>
                        <option value="1" {{ request('is_featured') === '1' ? 'selected' : '' }}>Öne Çıkan</option>
                        <option value="0" {{ request('is_featured') === '0' ? 'selected' : '' }}>Normal</option>
                    </select>
                </div>

                {{-- Toolbar Actions --}}
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.products.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    {{-- View Toggle --}}
                    <div class="prd-view-toggle">
                        <button type="button" class="prd-view-btn active" id="gridViewBtn" onclick="switchView('grid')" title="Kart Görünümü">
                            <i class="bi bi-grid-3x3-gap-fill"></i>
                        </button>
                        <button type="button" class="prd-view-btn" id="tableViewBtn" onclick="switchView('table')" title="Tablo Görünümü">
                            <i class="bi bi-list-ul"></i>
                        </button>
                    </div>
                    <div class="cl-per-page">
                        <label>Göster:</label>
                        <select id="perPage" name="per_page" onchange="document.getElementById('filterForm').submit()">
                            @foreach([12, 24, 48, 96] as $pp)
                                <option value="{{ $pp }}" {{ (int) request('per_page', 24) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cl-bulk-actions d-none" id="bulkActions">
                        <span class="cl-bulk-count"><span id="selectedCount">0</span> seçili</span>
                        <button type="button" class="usr-action-btn success" onclick="bulkProductAction('activate')" title="Aktif Et"><i class="bi bi-check-circle"></i></button>
                        <button type="button" class="usr-action-btn" onclick="bulkProductAction('draft')" title="Taslağa Al"><i class="bi bi-file-earmark"></i></button>
                        <button type="button" class="usr-action-btn danger" onclick="bulkProductAction('delete')" title="Sil"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- SECTION 4: PRODUCT GRID VIEW --}}
    <div id="productGridView">
        <div class="row g-4">
            @forelse($products as $product)
                @php
                    $stockClass = 'prd-stock-in';
                    $stockLabel = 'Stokta';
                    $stockIcon = 'bi-check-circle-fill';
                    if ($product->stock <= 0) {
                        $stockClass = 'prd-stock-out';
                        $stockLabel = 'Tükendi';
                        $stockIcon = 'bi-x-circle-fill';
                    } elseif ($product->stock <= 10) {
                        $stockClass = 'prd-stock-low';
                        $stockLabel = 'Az Kaldı';
                        $stockIcon = 'bi-exclamation-triangle-fill';
                    }

                    $cardClass = 'prd-card';
                    if ($product->status === \App\Enums\ProductStatus::Draft) {
                        $cardClass .= ' prd-card-draft';
                    } elseif ($product->stock <= 0) {
                        $cardClass .= ' prd-card-outofstock';
                    }
                @endphp
                <div class="col-xxl-3 col-xl-4 col-md-6" data-aos="fade-up">
                    <div class="{{ $cardClass }}">
                        <div class="prd-card-img-wrapper">
                            @if($product->cover_image)
                                <img src="{{ upload_url($product->cover_image, 'md') }}"
                                     alt="{{ $product->name }}"
                                     loading="lazy" class="prd-card-img">
                            @else
                                <div class="prd-card-img d-flex align-items-center justify-content-center bg-dark">
                                    <i class="bi bi-image text-muted fs-1"></i>
                                </div>
                            @endif
                            <div class="prd-card-badges">
                                @if($product->is_featured)
                                    <span class="prd-badge prd-badge-hot">Popüler</span>
                                @endif
                                @if($product->status === \App\Enums\ProductStatus::Draft)
                                    <span class="prd-badge prd-badge-draft">Taslak</span>
                                @endif
                                @if($product->stock <= 0)
                                    <span class="prd-badge prd-badge-outofstock">Tükendi</span>
                                @endif
                                @if($product->discounted_price)
                                    @php
                                        $discountPercent = round((1 - $product->price / $product->discounted_price) * 100);
                                    @endphp
                                    <span class="prd-badge prd-badge-discount">-{{ $discountPercent }}%</span>
                                @endif
                            </div>
                            <div class="prd-card-overlay">
                                <a href="{{ route('admin.products.show', $product) }}" class="prd-overlay-btn" title="Detay"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('admin.products.edit', $product) }}" class="prd-overlay-btn" title="Düzenle"><i class="bi bi-pencil"></i></a>
                                <button class="prd-overlay-btn prd-overlay-btn-danger" onclick="openDeleteModal('{{ e($product->name) }}', {{ $product->id }})" title="Sil"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <div class="prd-card-body">
                            <div class="prd-card-category">{{ $product->category?->name ?? '—' }}</div>
                            <h6 class="prd-card-title">{{ Str::limit($product->name, 40) }}</h6>
                            <div class="prd-card-footer">
                                <div class="prd-card-price">
                                    @if($product->discounted_price)
                                        <span class="prd-price-current">{{ number_format($product->price, 0, ',', '.') }} ₺</span>
                                        <span class="prd-price-old">{{ number_format($product->discounted_price, 0, ',', '.') }} ₺</span>
                                    @else
                                        <span class="prd-price-current">{{ number_format($product->price, 0, ',', '.') }} ₺</span>
                                    @endif
                                </div>
                                <div class="prd-card-stock {{ $stockClass }}">
                                    <i class="bi {{ $stockIcon }}"></i> {{ $stockLabel }}
                                </div>
                            </div>
                            <div class="prd-card-meta">
                                <span><i class="bi bi-upc-scan me-1"></i>{{ $product->sku }}</span>
                                <span><i class="bi bi-box me-1"></i>{{ $product->stock }} {{ $product->unit }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="card-dark">
                        <div class="card-body-custom text-center py-5">
                            <i class="bi bi-box-seam d-block fs-1 mb-3 text-muted"></i>
                            <h5 class="text-muted">Henüz ürün eklenmemiş</h5>
                            <p class="text-muted mb-3">İlk ürününüzü ekleyerek başlayın</p>
                            <a href="{{ route('admin.products.create') }}" class="btn-teal">
                                <i class="bi bi-plus-lg me-1"></i> Yeni Ürün Ekle
                            </a>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    {{-- SECTION 5: PRODUCT TABLE VIEW (Hidden by default) --}}
    <div id="productTableView" class="d-none">
        <div class="card-dark mb-4" data-aos="fade-up">
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="cl-table">
                        <thead>
                            <tr>
                                <th class="cl-th-checkbox">
                                    <input type="checkbox" class="usr-checkbox" id="selectAllProducts" onchange="toggleSelectAllProducts(this)">
                                </th>
                                <th>Ürün</th>
                                <th class="d-none d-md-table-cell">Kategori</th>
                                <th>Fiyat</th>
                                <th class="d-none d-lg-table-cell">Stok</th>
                                <th>Durum</th>
                                <th class="cl-th-actions">İşlem</th>
                            </tr>
                        </thead>
                        <tbody id="productTableBody">
                            @forelse($products as $product)
                                @php
                                    $tblStockClass = 'prd-stock-in';
                                    if ($product->stock <= 0) {
                                        $tblStockClass = 'prd-stock-out';
                                    } elseif ($product->stock <= 10) {
                                        $tblStockClass = 'prd-stock-low';
                                    }

                                    $statusBadgeClass = match($product->status) {
                                        \App\Enums\ProductStatus::Active => 'active',
                                        \App\Enums\ProductStatus::Draft => 'pending',
                                        \App\Enums\ProductStatus::Inactive => 'inactive',
                                    };
                                @endphp
                                <tr>
                                    <td data-label="Seç"><input type="checkbox" class="usr-checkbox product-checkbox" value="{{ $product->id }}" onchange="updateProductBulk()"></td>
                                    <td data-label="Ürün">
                                        <div class="cl-content-cell">
                                            <div class="cl-content-thumb">
                                                @if($product->cover_image)
                                                    <img src="{{ upload_url($product->cover_image, 'thumb') }}" alt="{{ $product->name }}">
                                                @else
                                                    <div class="d-flex align-items-center justify-content-center h-100 bg-dark rounded">
                                                        <i class="bi bi-image text-muted"></i>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="cl-content-info">
                                                <span class="cl-content-title">{{ Str::limit($product->name, 35) }}</span>
                                                <span class="cl-content-meta">
                                                    <i class="bi bi-upc-scan me-1"></i>{{ $product->sku }}
                                                    @if($product->is_featured)
                                                        <span class="cl-separator">|</span>
                                                        <i class="bi bi-star-fill text-warning me-1"></i>Öne Çıkan
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Kategori" class="d-none d-md-table-cell">
                                        <span class="cl-category-badge">{{ $product->category?->name ?? '—' }}</span>
                                    </td>
                                    <td data-label="Fiyat">
                                        <div class="prd-table-price">
                                            @if($product->discounted_price)
                                                <span class="prd-price-current">{{ number_format($product->price, 0, ',', '.') }} ₺</span>
                                                <span class="prd-price-old">{{ number_format($product->discounted_price, 0, ',', '.') }} ₺</span>
                                            @else
                                                <span class="prd-price-current">{{ number_format($product->price, 0, ',', '.') }} ₺</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td data-label="Stok" class="d-none d-lg-table-cell">
                                        <div class="prd-table-stock {{ $tblStockClass }}">
                                            <i class="bi bi-box me-1"></i>{{ $product->stock }}
                                        </div>
                                    </td>
                                    <td data-label="Durum">
                                        <span class="usr-status-badge {{ $statusBadgeClass }}">{{ $product->status->label() }}</span>
                                    </td>
                                    <td data-label="İşlem">
                                        <div class="usr-actions">
                                            <a class="usr-action-btn" title="Detay" href="{{ route('admin.products.show', $product) }}"><i class="bi bi-eye"></i></a>
                                            <a class="usr-action-btn" title="Düzenle" href="{{ route('admin.products.edit', $product) }}"><i class="bi bi-pencil"></i></a>
                                            <button class="usr-action-btn danger" title="Sil" onclick="openDeleteModal('{{ e($product->name) }}', {{ $product->id }})"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="bi bi-box-seam d-block fs-1 mb-2"></i>
                                        Henüz ürün eklenmemiş.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('partials.admin.pagination', ['paginator' => $products, 'itemLabel' => 'ürün'])

    {{-- QUICK VIEW MODAL --}}
    <div class="modal fade" id="quickViewModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title prd-modal-title">Ürün Detayı</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body" id="quickViewBody">
                    <div class="row g-4">
                        {{-- Image --}}
                        <div class="col-md-5">
                            <div class="prd-modal-gallery">
                                <div class="prd-modal-main-img">
                                    <img src="" alt="Ürün görseli" id="quickViewMainImg" loading="lazy" class="prd-modal-img">
                                </div>
                            </div>
                        </div>
                        {{-- Product Info --}}
                        <div class="col-md-7">
                            <div class="prd-modal-info">
                                <span class="prd-modal-category" id="quickViewCategory"></span>
                                <h4 class="prd-modal-name" id="quickViewName"></h4>
                                <div class="prd-modal-price-box">
                                    <span class="prd-modal-price" id="quickViewPrice"></span>
                                    <span class="prd-modal-old-price d-none" id="quickViewOldPrice"></span>
                                    <span class="prd-modal-discount-tag d-none" id="quickViewDiscount"></span>
                                </div>
                                <p class="prd-modal-desc" id="quickViewDesc"></p>
                                <div class="prd-modal-specs">
                                    <div class="prd-modal-spec-item">
                                        <span class="prd-spec-label"><i class="bi bi-upc-scan me-1"></i>SKU</span>
                                        <span class="prd-spec-value" id="quickViewSku"></span>
                                    </div>
                                    <div class="prd-modal-spec-item">
                                        <span class="prd-spec-label"><i class="bi bi-box me-1"></i>Stok</span>
                                        <span class="prd-spec-value" id="quickViewStock"></span>
                                    </div>
                                    <div class="prd-modal-spec-item">
                                        <span class="prd-spec-label"><i class="bi bi-tag me-1"></i>Durum</span>
                                        <span class="prd-spec-value" id="quickViewStatus"></span>
                                    </div>
                                </div>
                                <div class="prd-modal-actions">
                                    <a href="#" class="btn-teal" id="quickViewEditBtn">
                                        <i class="bi bi-pencil me-1"></i> Düzenle
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- BULK DELETE MODAL --}}
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="status-modal-icon danger">
                        <i class="bi bi-trash"></i>
                    </div>
                    <h5 class="cl-modal-heading">Toplu Silme Onayı</h5>
                    <p class="cl-modal-body-text"><strong id="bulkDeleteCount">0</strong> ürünü silmek istediğinizden emin misiniz?</p>
                    <p class="cl-modal-warning"><i class="bi bi-exclamation-circle me-1"></i>Bu işlem geri alınamaz.</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                        <button class="btn-teal btn-danger-gradient" data-bs-dismiss="modal" onclick="confirmBulkDelete()">
                            <i class="bi bi-trash"></i> Evet, Sil
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
{{-- Product data for quick view --}}
@php
    $productJsonData = $products->getCollection()->mapWithKeys(function ($product) {
        return [$product->id => [
            'id'        => $product->id,
            'name'      => $product->name,
            'category'  => $product->category?->name ?? '—',
            'price'     => number_format((float) $product->price, 0, ',', '.') . ' ₺',
            'oldPrice'  => $product->discounted_price
                ? number_format((float) $product->discounted_price, 0, ',', '.') . ' ₺'
                : '',
            'discount'  => $product->discounted_price
                ? '-' . round((1 - (float) $product->price / (float) $product->discounted_price) * 100) . '%'
                : '',
            'sku'       => $product->sku,
            'stock'     => $product->stock . ' ' . $product->unit,
            'status'    => $product->status->label(),
            'desc'      => $product->short_description ?? Str::limit(strip_tags($product->description ?? ''), 200),
            'image'     => $product->cover_image ? upload_url($product->cover_image, 'md') : '',
            'editUrl'   => route('admin.products.edit', $product),
            'deleteUrl' => route('admin.products.destroy', $product),
        ]];
    });
@endphp
<script>
    window.productData = {!! json_encode($productJsonData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
</script>
<script src="{{ versioned_asset('assets/admin/js/products.js') }}"></script>
@endpush
