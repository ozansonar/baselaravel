@extends('layouts.admin')

@section('title', 'Yönlendirme Yönetimi')
@section('page_title', 'Yönlendirme Yönetimi')
@section('page_description', 'URL yönlendirmelerini yönetin, eski linkleri yeni adreslere yönlendirin')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Yönlendirmeler</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Yönlendirme Yönetimi</h1>
            <p class="page-subtitle">Eski URL'leri yeni adreslere yönlendirin, SEO değerinizi koruyun</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn-teal" id="addRedirectBtn" data-bs-toggle="modal" data-bs-target="#redirectModal">
                <i class="bi bi-plus-lg"></i> Yeni Yönlendirme
            </button>
        </div>
    </div>

    {{-- ==================== SECTION 1: STATS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-signpost-2-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Yönlendirme</span>
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
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Hit</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total_hits'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red">
                    <i class="bi bi-lightning-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Bugün</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['today_hits'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 2: FILTERS & TOOLBAR ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.redirects.index') }}" id="filterForm" class="cl-toolbar">
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="redirectSearch" name="search" value="{{ request('search') }}"
                           placeholder="Eski veya yeni URL ile ara...">
                </div>

                <div class="cl-filters">
                    <select class="cl-filter-select" name="status_code" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Kodlar</option>
                        <option value="301" {{ request('status_code') === '301' ? 'selected' : '' }}>301 - Kalıcı</option>
                        <option value="302" {{ request('status_code') === '302' ? 'selected' : '' }}>302 - Geçici</option>
                        <option value="307" {{ request('status_code') === '307' ? 'selected' : '' }}>307 - Geçici (Strict)</option>
                        <option value="308" {{ request('status_code') === '308' ? 'selected' : '' }}>308 - Kalıcı (Strict)</option>
                        <option value="404" {{ request('status_code') === '404' ? 'selected' : '' }}>404 - Bulunamadı</option>
                        <option value="410" {{ request('status_code') === '410' ? 'selected' : '' }}>410 - Kaldırıldı</option>
                    </select>

                    <select class="cl-filter-select" name="status" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Durumlar</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Pasif</option>
                    </select>
                </div>

                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.redirects.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
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

    {{-- ==================== SECTION 3: TABLE ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th class="cl-th-checkbox">#</th>
                            <th>Eski URL</th>
                            <th>Yeni URL</th>
                            <th class="d-none d-md-table-cell">Durum Kodu</th>
                            <th class="d-none d-lg-table-cell">Hit</th>
                            <th class="d-none d-lg-table-cell">Son Hit</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($redirects as $redirect)
                            <tr>
                                <td class="cl-td-checkbox">{{ $redirect->id }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="redirect-url-code">{{ $redirect->old_url }}</code>
                                        <button type="button" class="btn-icon-xs js-copy-url" data-url="{{ $redirect->old_url }}" title="Kopyala">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    @if($redirect->note)
                                        <small class="text-clr-muted d-block mt-1">{{ $redirect->note }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($redirect->new_url)
                                        <code class="redirect-url-code">{{ $redirect->new_url }}</code>
                                    @else
                                        <span class="text-clr-muted">—</span>
                                    @endif
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <span class="redirect-badge redirect-badge-{{ $redirect->status_code }}">
                                        {{ $redirect->status_code }}
                                    </span>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <span class="fw-semibold">{{ number_format($redirect->hit_count) }}</span>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    @if($redirect->last_hit_at)
                                        <span title="{{ $redirect->last_hit_at->format('d.m.Y H:i') }}">
                                            {{ $redirect->last_hit_at->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-clr-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input js-toggle-active"
                                               type="checkbox"
                                               data-id="{{ $redirect->id }}"
                                               data-url="{{ route('admin.redirects.toggle-active', $redirect) }}"
                                               {{ $redirect->is_active ? 'checked' : '' }}>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="usr-actions justify-content-end">
                                        <button type="button"
                                                class="usr-action-btn js-edit-redirect"
                                                data-redirect="{{ json_encode($redirect) }}"
                                                title="Düzenle">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="{{ route('admin.redirects.destroy', $redirect) }}" method="POST" class="js-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="usr-action-btn danger"
                                                    data-confirm="Bu yönlendirmeyi silmek istediğinize emin misiniz?"
                                                    title="Sil">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="cl-empty-state">
                                        <i class="bi bi-signpost-2 cl-empty-icon"></i>
                                        <h5>Henüz yönlendirme yok</h5>
                                        <p class="text-clr-muted">Yeni bir yönlendirme ekleyerek başlayın</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Pagination --}}
    @include('partials.admin.pagination', ['paginator' => $redirects, 'itemLabel' => 'yönlendirme'])

    {{-- ==================== MODAL: ADD/EDIT ==================== --}}
    <div class="modal fade" id="redirectModal" tabindex="-1" aria-labelledby="redirectModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-theme">
            <div class="modal-content modal-content-theme">
                <div class="modal-header modal-header-theme">
                    <h5 class="modal-title" id="redirectModalTitle">Yeni Yönlendirme</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <form id="redirectForm" method="POST" action="{{ route('admin.redirects.store') }}">
                    @csrf
                    <input type="hidden" name="_method" id="redirectFormMethod" value="POST">
                    <div class="modal-body modal-body-theme">
                        {{-- Eski URL --}}
                        <div class="mb-3">
                            <label for="oldUrl" class="form-label text-clr-secondary">Eski URL <span class="text-danger">*</span></label>
                            <div class="input-group input-group-theme">
                                <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                <input type="text" class="form-control form-control-theme" id="oldUrl" name="old_url"
                                       placeholder="/eski-sayfa-adresi" required>
                            </div>
                            <small class="form-text text-clr-muted">/ ile başlamalıdır</small>
                        </div>

                        {{-- Durum Kodu --}}
                        <div class="mb-3">
                            <label for="statusCode" class="form-label text-clr-secondary">Durum Kodu <span class="text-danger">*</span></label>
                            <select class="form-select form-select-theme" id="statusCode" name="status_code" required>
                                <option value="301">301 - Kalıcı Yönlendirme</option>
                                <option value="302">302 - Geçici Yönlendirme</option>
                                <option value="307">307 - Geçici Yönlendirme (Strict)</option>
                                <option value="308">308 - Kalıcı Yönlendirme (Strict)</option>
                                <option value="404">404 - Sayfa Bulunamadı</option>
                                <option value="410">410 - Kalıcı Olarak Kaldırıldı</option>
                            </select>
                            <div id="statusCodeDesc" class="redirect-status-desc mt-2">
                                <i class="bi bi-info-circle me-1"></i>
                                <span>URL kalıcı olarak taşındı. SEO değeri yeni URL'ye aktarılır.</span>
                            </div>
                        </div>

                        {{-- Yeni URL --}}
                        <div class="mb-3" id="newUrlWrapper">
                            <label for="newUrl" class="form-label text-clr-secondary">Yeni URL <span class="text-danger">*</span></label>
                            <div class="input-group input-group-theme">
                                <span class="input-group-text"><i class="bi bi-arrow-right"></i></span>
                                <input type="text" class="form-control form-control-theme" id="newUrl" name="new_url"
                                       placeholder="/yeni-sayfa-adresi">
                            </div>
                            <small class="form-text text-clr-muted">Kullanıcının yönlendirileceği adres</small>
                        </div>

                        {{-- Not --}}
                        <div class="mb-3">
                            <label for="redirectNote" class="form-label text-clr-secondary">Not</label>
                            <textarea class="form-control form-control-theme" id="redirectNote" name="note"
                                      rows="2" placeholder="Opsiyonel açıklama..."></textarea>
                        </div>

                        {{-- Aktif --}}
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="redirectIsActive" name="is_active" value="1" checked>
                            <label class="form-check-label text-clr-secondary" for="redirectIsActive">Aktif</label>
                        </div>
                    </div>
                    <div class="modal-footer modal-footer-theme">
                        <button type="button" class="btn-glass" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn-teal" id="saveRedirectBtn">
                            <i class="bi bi-check-lg me-1"></i> Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/admin/js/redirects.js') }}"></script>
@endpush
