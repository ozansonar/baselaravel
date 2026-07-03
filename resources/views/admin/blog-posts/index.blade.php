@extends('layouts.admin')

@section('title', 'İçerik Yönetimi')
@section('page_title', 'İçerik Yönetimi')
@section('page_description', 'Tüm içerikleri listeleyin, filtreleyin, düzenleyin ve yönetin')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">İçerikler</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">İçerik Yönetimi</h1>
            <p class="page-subtitle">Tüm içerikleri listeleyin, filtreleyin, düzenleyin ve yönetin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.blog-posts.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg"></i> Yeni İçerik
            </a>
        </div>
    </div>

    {{-- SECTION 1: STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-file-earmark-text-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam İçerik</span>
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
                    <i class="bi bi-file-earmark-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Taslak</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['draft'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple">
                    <i class="bi bi-eye-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Görüntülenme</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total_views'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 2: STATUS TABS --}}
    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        <a href="{{ route('admin.blog-posts.index', request()->except(['status', 'page'])) }}"
           class="cl-status-tab {{ !request('status') ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $statusCounts['all'] }}</span>
        </a>
        <a href="{{ route('admin.blog-posts.index', array_merge(request()->except('page'), ['status' => 'published'])) }}"
           class="cl-status-tab {{ request('status') === 'published' ? 'active' : '' }}">
            <i class="bi bi-check-circle text-neon-green"></i>
            <span>Yayında</span>
            <span class="cl-tab-count">{{ $statusCounts['published'] }}</span>
        </a>
        <a href="{{ route('admin.blog-posts.index', array_merge(request()->except('page'), ['status' => 'draft'])) }}"
           class="cl-status-tab {{ request('status') === 'draft' ? 'active' : '' }}">
            <i class="bi bi-file-earmark text-neon-orange"></i>
            <span>Taslak</span>
            <span class="cl-tab-count">{{ $statusCounts['draft'] }}</span>
        </a>
        @if($statusCounts['scheduled'] > 0)
            <a href="{{ route('admin.blog-posts.index', array_merge(request()->except('page'), ['status' => 'scheduled'])) }}"
               class="cl-status-tab {{ request('status') === 'scheduled' ? 'active' : '' }}">
                <i class="bi bi-clock text-neon-blue"></i>
                <span>Zamanlanmış</span>
                <span class="cl-tab-count">{{ $statusCounts['scheduled'] }}</span>
            </a>
        @endif
        @if($statusCounts['trashed'] > 0)
            <a href="{{ route('admin.blog-posts.index', array_merge(request()->except('page'), ['status' => 'trashed'])) }}"
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
            <form method="GET" action="{{ route('admin.blog-posts.index') }}" id="filterForm" class="cl-toolbar">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif

                {{-- Search --}}
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="contentSearch" name="search" value="{{ request('search') }}"
                           placeholder="Başlık veya özet ile ara...">
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
                </div>

                {{-- Toolbar Actions --}}
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.blog-posts.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <div class="cl-per-page">
                        <label>Göster:</label>
                        <select id="perPage" name="per_page" onchange="document.getElementById('filterForm').submit()">
                            @foreach([10, 25, 50, 100] as $pp)
                                <option value="{{ $pp }}" {{ (int) request('per_page', 25) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cl-bulk-actions d-none" id="bulkActions">
                        <span class="cl-bulk-count"><span id="selectedCount">0</span> seçili</span>
                        <button type="button" class="usr-action-btn success" onclick="bulkContentAction('publish')" title="Yayınla"><i class="bi bi-check-circle"></i></button>
                        <button type="button" class="usr-action-btn" onclick="bulkContentAction('draft')" title="Taslağa Al"><i class="bi bi-file-earmark"></i></button>
                        <button type="button" class="usr-action-btn danger" onclick="bulkContentAction('delete')" title="Sil"><i class="bi bi-trash"></i></button>
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
                            <th class="cl-th-checkbox">
                                <input type="checkbox" class="usr-checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            </th>
                            <th>İçerik</th>
                            <th class="d-none d-md-table-cell">Kategori</th>
                            <th class="d-none d-lg-table-cell">Yazar</th>
                            <th>Durum</th>
                            <th class="d-none d-xl-table-cell">Görüntülenme</th>
                            <th class="d-none d-xxl-table-cell">Tarih</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="contentTableBody">
                        @forelse($posts as $post)
                            @php
                                $statusBadge = 'pending';
                                $statusLabel = 'Taslak';
                                if ($post->trashed()) {
                                    $statusBadge = 'inactive';
                                    $statusLabel = 'Silinmiş';
                                } elseif ($post->is_published && $post->published_at?->lte(now())) {
                                    $statusBadge = 'active';
                                    $statusLabel = 'Yayında';
                                } elseif ($post->is_published && $post->published_at?->gt(now())) {
                                    $statusBadge = 'info';
                                    $statusLabel = 'Zamanlanmış';
                                }
                            @endphp
                            <tr>
                                <td data-label="Seç">
                                    <input type="checkbox" class="usr-checkbox content-checkbox" value="{{ $post->id }}" onchange="updateBulk()">
                                </td>
                                <td data-label="İçerik">
                                    <div class="cl-content-cell">
                                        @if($post->image)
                                            <div class="cl-content-thumb">
                                                <img src="{{ upload_url($post->image, 'thumb') }}" alt="{{ $post->title }}">
                                            </div>
                                        @else
                                            <div class="cl-content-thumb draft"><i class="bi bi-file-earmark-text"></i></div>
                                        @endif
                                        <div class="cl-content-info">
                                            @if($post->is_published && $post->published_at?->lte(now()))
                                                <a href="{{ route('blog.show', [$post->category->slug, $post->slug]) }}" target="_blank" class="cl-content-title cl-content-link">{{ Str::limit($post->title, 50) }} <i class="bi bi-box-arrow-up-right cl-link-icon"></i></a>
                                            @else
                                                <span class="cl-content-title">{{ Str::limit($post->title, 50) }}</span>
                                            @endif
                                            <span class="cl-content-meta">
                                                @if($post->category)
                                                    <i class="bi bi-tag me-1"></i>{{ $post->category->name }}
                                                    <span class="cl-separator">|</span>
                                                @endif
                                                <i class="bi bi-clock me-1"></i>{{ $post->created_at->translatedFormat('d M Y H:i') }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Kategori" class="d-none d-md-table-cell">
                                    @if($post->category)
                                        <span class="cl-category-badge">{{ $post->category->name }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="Yazar" class="d-none d-lg-table-cell">
                                    @if($post->author)
                                        <div class="cl-author-cell">
                                            <img src="https://ui-avatars.com/api/?name={{ urlencode($post->author->name) }}&background=14b8a6&color=fff&size=28" alt="{{ $post->author->name }}">
                                            <span>{{ $post->author->name }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td data-label="Durum">
                                    <span class="usr-status-badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                                </td>
                                <td data-label="Görüntülenme" class="d-none d-xl-table-cell">
                                    <div class="cl-views">
                                        @if($post->is_published)
                                            <i class="bi bi-eye me-1"></i> {{ number_format($post->views, 0, ',', '.') }}
                                        @else
                                            <span class="text-muted">--</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Tarih" class="d-none d-xxl-table-cell">
                                    <span class="usr-meta">{{ ($post->published_at ?? $post->created_at)->translatedFormat('d M Y H:i') }}</span>
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        @if($post->trashed())
                                            <form method="POST" action="{{ route('admin.blog-posts.restore', $post->id) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="usr-action-btn success" title="Geri Yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                        @else
                                            <a class="usr-action-btn" title="Detay" href="{{ route('admin.blog-posts.show', $post) }}"><i class="bi bi-eye"></i></a>
                                            @if($post->is_published && $post->published_at?->lte(now()))
                                                <a class="usr-action-btn success" title="Sitede Görüntüle" href="{{ route('blog.show', [$post->category->slug, $post->slug]) }}" target="_blank"><i class="bi bi-box-arrow-up-right"></i></a>
                                            @endif
                                            <a class="usr-action-btn" title="Düzenle" href="{{ route('admin.blog-posts.edit', $post) }}"><i class="bi bi-pencil"></i></a>
                                            <button class="usr-action-btn danger" title="Sil" onclick="openDeleteModal('{{ e($post->title) }}', {{ $post->id }})"><i class="bi bi-trash"></i></button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-file-earmark-text d-block fs-1 mb-2"></i>
                                    Henüz içerik eklenmemiş.
                                    <br>
                                    <a href="{{ route('admin.blog-posts.create') }}" class="btn-teal mt-3 d-inline-flex">
                                        <i class="bi bi-plus-lg me-1"></i> İlk İçeriği Oluştur
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.admin.pagination', ['paginator' => $posts, 'itemLabel' => 'içerik'])
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
                    <p class="cl-modal-body-text"><strong id="bulkDeleteCount">0</strong> içeriği silmek istediğinizden emin misiniz?</p>
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
<script src="{{ versioned_asset('assets/admin/js/blog-list.js') }}"></script>
@endpush
