@extends('layouts.admin')

@section('title', 'Kategori Yönetimi')
@section('page_title', 'Kategori Yönetimi')
@section('page_description', 'Tüm kategorileri listeleyin, filtreleyin, düzenleyin ve yönetin')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Kategoriler</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Kategori Yönetimi</h1>
            <p class="page-subtitle">Tüm kategorileri listeleyin, filtreleyin, düzenleyin ve yönetin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.categories.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg"></i> Yeni Kategori
            </a>
        </div>
    </div>

    {{-- ==================== SECTION 1: STATS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-folder-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Kategori</span>
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
                    <i class="bi bi-x-circle-fill"></i>
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
                    <i class="bi bi-diagram-3-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Alt Kategori</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['children'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 2: STATUS TABS ==================== --}}
    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        <a href="{{ route('admin.categories.index', request()->except(['status', 'page'])) }}"
           class="cl-status-tab {{ !request('status') ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $statusCounts['all'] }}</span>
        </a>
        <a href="{{ route('admin.categories.index', array_merge(request()->except('page'), ['status' => 'active'])) }}"
           class="cl-status-tab {{ request('status') === 'active' ? 'active' : '' }}">
            <i class="bi bi-check-circle text-neon-green"></i>
            <span>Aktif</span>
            <span class="cl-tab-count">{{ $statusCounts['active'] }}</span>
        </a>
        <a href="{{ route('admin.categories.index', array_merge(request()->except('page'), ['status' => 'inactive'])) }}"
           class="cl-status-tab {{ request('status') === 'inactive' ? 'active' : '' }}">
            <i class="bi bi-x-circle text-neon-red"></i>
            <span>Pasif</span>
            <span class="cl-tab-count">{{ $statusCounts['inactive'] }}</span>
        </a>
    </div>

    {{-- ==================== SECTION 3: FILTERS & TOOLBAR ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.categories.index') }}" id="filterForm" class="cl-toolbar">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif

                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="categorySearch" name="search" value="{{ request('search') }}"
                           placeholder="Kategori adı veya slug ile ara...">
                </div>

                <div class="cl-filters">
                    <select class="cl-filter-select" name="parent" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Kategoriler</option>
                        <option value="root"  {{ request('parent') === 'root'  ? 'selected' : '' }}>Sadece Üst Kategoriler</option>
                        <option value="child" {{ request('parent') === 'child' ? 'selected' : '' }}>Sadece Alt Kategoriler</option>
                        <option value="" disabled>──────────</option>
                        @foreach($parentOptions as $option)
                            @if($option->parent_id === null)
                                <option value="{{ $option->id }}" {{ (string) request('parent') === (string) $option->id ? 'selected' : '' }}>
                                    Alt kategorileri: {{ $option->name }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.categories.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <div class="cl-per-page">
                        <label>Göster:</label>
                        <select name="per_page" onchange="document.getElementById('filterForm').submit()">
                            @foreach([10, 25, 50, 100] as $pp)
                                <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ==================== SECTION 4: CATEGORIES TABLE ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th class="cl-th-checkbox">#</th>
                            <th>Kategori</th>
                            <th class="d-none d-md-table-cell">Üst Kategori</th>
                            <th class="d-none d-lg-table-cell">Ürün Sayısı</th>
                            <th class="d-none d-lg-table-cell">Sıra</th>
                            <th>Durum</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                            <tr>
                                <td data-label="#">
                                    <span class="text-muted">{{ $category->id }}</span>
                                </td>
                                <td data-label="Kategori">
                                    <div class="cl-content-cell">
                                        <div class="cl-content-thumb">
                                            @if($category->image)
                                                <img src="{{ upload_url($category->image, 'thumb') }}"
                                                     alt="{{ $category->name }}" loading="lazy">
                                            @else
                                                <div class="d-flex align-items-center justify-content-center h-100 bg-dark rounded">
                                                    <i class="bi bi-folder text-muted"></i>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="cl-content-info">
                                            <span class="cl-content-title">{{ $category->name }}</span>
                                            <span class="cl-content-meta"><i class="bi bi-link-45deg me-1"></i>/{{ $category->slug }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Üst Kategori" class="d-none d-md-table-cell">
                                    @if($category->parent)
                                        <span class="cl-category-badge">{{ $category->parent->name }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="Ürün Sayısı" class="d-none d-lg-table-cell">
                                    <span class="usr-meta">
                                        <i class="bi bi-box-seam me-1"></i>{{ $category->products_count }}
                                    </span>
                                </td>
                                <td data-label="Sıra" class="d-none d-lg-table-cell">
                                    <span class="usr-meta">{{ $category->sort_order }}</span>
                                </td>
                                <td data-label="Durum">
                                    @if($category->is_active)
                                        <span class="usr-status-badge active">Aktif</span>
                                    @else
                                        <span class="usr-status-badge inactive">Pasif</span>
                                    @endif
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        <a class="usr-action-btn" title="Düzenle" href="{{ route('admin.categories.edit', $category) }}">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button class="usr-action-btn danger" title="Sil"
                                                onclick="openDeleteModal('{{ e($category->name) }}', {{ $category->id }})">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-folder2-open d-block fs-1 mb-3"></i>
                                    <h5 class="text-muted">Kategori bulunamadı</h5>
                                    <p class="text-muted mb-3">Arama kriterinizi değiştirin veya yeni kategori ekleyin</p>
                                    <a href="{{ route('admin.categories.create') }}" class="btn-teal">
                                        <i class="bi bi-plus-lg me-1"></i> Yeni Kategori Ekle
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.admin.pagination', ['paginator' => $categories, 'itemLabel' => 'kategori'])
    </div>
@endsection

@push('scripts')
<script>
    document.getElementById('categorySearch')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('filterForm').submit();
        }
    });

    function openDeleteModal(name, id) {
        AdminModal.confirm({
            title: 'Silme Onayı',
            message: 'Bu kategoriyi silmek istediğinizden emin misiniz?',
            type: 'danger',
            confirmText: 'Evet, Sil',
            confirmIcon: 'bi bi-trash3',
            detailTitle: name,
            warning: 'Kategori silindiğinde ürünler kategorisiz kalır.'
        }).then(function (confirmed) {
            if (!confirmed) return;
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '/admin/categories/' + id;
            form.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name="csrf-token"]').getAttribute('content') + '"><input type="hidden" name="_method" value="DELETE">';
            document.body.appendChild(form);
            form.submit();
        });
    }
</script>
@endpush
