@extends('layouts.admin')

@section('title', 'Dosya Yöneticisi')
@section('page_title', 'Dosya Yöneticisi')
@section('page_description', 'Blog ve sayfalarda kullanmak için dosya yükle, link al')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Dosya Yöneticisi</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Dosya Yöneticisi</h1>
            <p class="page-subtitle">
                PDF, Word, Excel, görsel ve diğer dosyaları yükle, public URL'ini blog/sayfalarda kullan.
            </p>
        </div>
    </div>

    {{-- ==================== STAT CARDS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-folder-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Dosya</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total_files'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple"><i class="bi bi-hdd"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Boyut</span>
                    <h3 class="usr-stat-value">{{ \Illuminate\Support\Number::fileSize($stats['total_size'], precision: 1) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-calendar-month"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Bu Ay Eklenen</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['this_month'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red"><i class="bi bi-images"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Görsel Sayısı</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['by_category']['image'] ?? 0 }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== UPLOAD FORM ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up">
        <div class="card-header-custom d-flex align-items-center gap-3">
            <div class="form-section-icon bg-icon-teal"><i class="bi bi-cloud-upload-fill"></i></div>
            <div class="flex-grow-1">
                <h6 class="mb-0">Dosya Yükle</h6>
                <small class="text-muted">
                    Dosya bırakılır bırakılmaz <strong>otomatik yüklenir</strong> · 6 paralel istek ·
                    Max 50 MB · 50 dosya/seans · Aynı dosya tekrar yüklenmez (SHA256) ·
                    SVG kabul edilmez
                </small>
            </div>
        </div>
        <div class="card-body-custom">
            <div id="fileManagerDropzone"
                 class="dropzone fmgr-dropzone"
                 data-upload-url="{{ route('admin.files.upload') }}">
                {{-- Dropzone JS init eder, autoProcessQueue: true --}}
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" id="fmgrClearAllBtn" class="btn-glass">
                    <i class="bi bi-x-circle me-1"></i> Hatalıları/Tümünü Kaldır
                </button>
            </div>
        </div>
    </div>

    {{-- ==================== FILTERS ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.files.index') }}" class="cl-toolbar" id="filterForm">
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" id="fmgrSearch"
                           placeholder="Dosya adı, başlık veya alt metinde ara..."
                           value="{{ $filters['q'] ?? '' }}" data-fv-ignore>
                </div>
                <div class="cl-filters">
                    <select class="cl-filter-select" name="category" onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                        <option value="">Tüm Kategoriler</option>
                        @foreach($categories as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['category'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="date" class="cl-filter-select" name="date_from"
                           value="{{ $filters['date_from'] ?? '' }}"
                           onchange="document.getElementById('filterForm').submit()"
                           title="Başlangıç tarihi" data-fv-ignore>
                    <input type="date" class="cl-filter-select" name="date_to"
                           value="{{ $filters['date_to'] ?? '' }}"
                           onchange="document.getElementById('filterForm').submit()"
                           title="Bitiş tarihi" data-fv-ignore>
                </div>
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.files.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <div class="cl-per-page">
                        <label>Göster:</label>
                        <select name="per_page" onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                            @foreach([12, 24, 48, 96] as $pp)
                                <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-export-menu export="files" :total="$files->total()" />
                </div>
            </form>
        </div>
    </div>

    {{-- ==================== FILE GRID ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            @if($files->isEmpty())
                <div class="fmgr-empty-state">
                    <i class="bi bi-folder2-open"></i>
                    <h6>Dosya bulunamadı</h6>
                    <p class="text-muted small mb-0">
                        @if(! empty($filters['q']) || ! empty($filters['category']))
                            Filtreye uyan dosya yok. Filtreleri temizleyip tekrar dene.
                        @else
                            Henüz dosya yüklenmemiş. Yukarıdaki yükleme alanından dosya ekle.
                        @endif
                    </p>
                </div>
            @else
                <div class="row g-3">
                    @foreach($files as $file)
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="fmgr-tile fmgr-tile--{{ $file->category }}">
                                {{-- Preview --}}
                                <a href="{{ route('admin.files.show', $file) }}" class="fmgr-tile__preview" title="Detay">
                                    @if($file->isImage())
                                        <img src="{{ $file->thumbnailUrl() }}"
                                             alt="{{ $file->alt_text ?? $file->original_name }}"
                                             loading="lazy"
                                             class="img-fluid">
                                    @else
                                        <div class="fmgr-tile__icon">
                                            <i class="bi {{ $file->iconClass() }} {{ $file->iconColorClass() }}"></i>
                                        </div>
                                    @endif
                                    <span class="fmgr-tile__category-badge">{{ $file->categoryLabel() }}</span>
                                </a>

                                {{-- Meta --}}
                                <div class="fmgr-tile__meta">
                                    <div class="fmgr-tile__name" title="{{ $file->original_name }}">
                                        {{ \Illuminate\Support\Str::limit($file->original_name, 28) }}
                                    </div>
                                    <div class="fmgr-tile__info">
                                        @if($file->isImage() && $file->webp_size)
                                            <span title="Orijinal boyut">
                                                <i class="bi bi-hdd me-1"></i>{{ $file->humanSize() }}
                                            </span>
                                            <span class="fmgr-tile__webp-size" title="WebP boyut">
                                                → <i class="bi bi-arrow-down-circle me-1"></i>{{ $file->webpSizeHuman() }}
                                            </span>
                                        @else
                                            <span><i class="bi bi-hdd me-1"></i>{{ $file->humanSize() }}</span>
                                        @endif
                                        <span class="ms-auto"><i class="bi bi-clock me-1"></i>{{ $file->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="fmgr-tile__actions">
                                    <button type="button" class="usr-action-btn"
                                            data-fmgr-url="{{ $file->fullUrl() }}"
                                            data-fmgr-name="{{ $file->original_name }}"
                                            onclick="fmgrCopyUrl(this)"
                                            title="Full URL Kopyala">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                    <a href="{{ route('admin.files.show', $file) }}" class="usr-action-btn" title="Detay">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button type="button" class="usr-action-btn fmgr-action-danger"
                                            data-file-id="{{ $file->id }}"
                                            data-file-name="{{ $file->original_name }}"
                                            onclick="fmgrConfirmDelete(this)"
                                            title="Sil">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                <div class="mt-4">
                    @include('partials.admin.pagination', ['paginator' => $files, 'itemLabel' => 'dosya'])
                </div>
            @endif
        </div>
    </div>

@endsection

@push('styles')
<style>
.fmgr-dropzone{min-height:160px;border:2px dashed var(--border-color);background:var(--bg-input);border-radius:12px;transition:border-color .2s ease}
.fmgr-dropzone:hover{border-color:var(--text-teal)}

.fmgr-empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:48px 16px;color:var(--text-muted);text-align:center}
.fmgr-empty-state i{font-size:48px;opacity:.4}
.fmgr-empty-state h6{margin:8px 0 0;color:var(--text-primary)}

.fmgr-tile{display:flex;flex-direction:column;background:var(--bg-input);border:1px solid var(--border-color);border-radius:12px;overflow:hidden;transition:all .2s ease;height:100%}
.fmgr-tile:hover{border-color:var(--text-teal);transform:translateY(-2px)}

.fmgr-tile__preview{display:block;position:relative;aspect-ratio:1/1;overflow:hidden;background:var(--bg-base);text-decoration:none}
.fmgr-tile__preview img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .3s ease}
.fmgr-tile__preview:hover img{transform:scale(1.04)}
.fmgr-tile__icon{display:flex;align-items:center;justify-content:center;width:100%;height:100%;font-size:64px;opacity:.85}
.fmgr-tile__category-badge{position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.65);color:#fff;font-size:10px;font-weight:600;padding:3px 8px;border-radius:4px;text-transform:uppercase;letter-spacing:0.04em}

.fmgr-tile__meta{padding:10px 12px;display:flex;flex-direction:column;gap:4px;flex-grow:1}
.fmgr-tile__name{font-size:13px;font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.fmgr-tile__info{display:flex;font-size:11px;color:var(--text-muted);flex-wrap:wrap;gap:6px;align-items:center}
.fmgr-tile__webp-size{color:var(--text-teal)}

.fmgr-tile__actions{display:flex;gap:4px;padding:0 12px 10px;justify-content:flex-end}
.fmgr-action-danger{color:#ef4444}
.fmgr-action-danger:hover{background:rgba(239,68,68,.15)}

.text-purple{color:#a855f7}

.dz-warning .dz-progress{background:rgba(255,193,7,.3) !important}
.dz-warning .dz-progress .dz-upload{background:#ffc107 !important}
</style>
@endpush

@push('scripts')
<script src="{{ asset('assets/admin/js/file-manager-upload.js') }}?v={{ filemtime(public_path('assets/admin/js/file-manager-upload.js')) }}" defer></script>
<script>
'use strict';

document.getElementById('fmgrSearch')?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        document.getElementById('filterForm').submit();
    }
});

function fmgrCopyUrl(btn) {
    var url = btn.getAttribute('data-fmgr-url');
    var name = btn.getAttribute('data-fmgr-name');

    var notify = function (success) {
        if (success) {
            if (typeof showToast === 'function') {
                showToast('URL kopyalandı: ' + name, 'success');
            }
        } else if (window.AdminModal && typeof AdminModal.status === 'function') {
            AdminModal.status({
                title: 'Kopyalanamadı',
                message: 'Tarayıcı izin vermedi. Detay sayfasından elle kopyalayabilirsin.',
                type: 'warning',
            });
        }
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function () { notify(true); }, function () { notify(false); });
    } else {
        // Fallback: temp textarea
        var ta = document.createElement('textarea');
        ta.value = url;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            var ok = document.execCommand('copy');
            notify(ok);
        } catch (e) {
            notify(false);
        }
        document.body.removeChild(ta);
    }
}

function fmgrConfirmDelete(btn) {
    var id = btn.getAttribute('data-file-id');
    var name = btn.getAttribute('data-file-name');
    var csrf = document.querySelector('meta[name="csrf-token"]').content;

    var doSubmit = function () {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/files/' + id;
        form.innerHTML =
            '<input type="hidden" name="_token" value="' + csrf + '">' +
            '<input type="hidden" name="_method" value="DELETE">';
        document.body.appendChild(form);
        form.submit();
    };

    if (window.AdminModal && typeof AdminModal.confirm === 'function') {
        AdminModal.confirm({
            title: 'Dosya Silinsin Mi?',
            message: '<strong>' + name + '</strong> kalıcı olarak silinecek (dosya + DB kaydı). Bu işlem geri alınamaz.',
            type: 'danger',
            confirmText: 'Evet, Sil',
            confirmIcon: 'bi bi-trash3',
        }).then(function (confirmed) {
            if (confirmed) doSubmit();
        });
    } else if (window.confirm(name + ' silinsin mi?')) {
        doSubmit();
    }
}
</script>
@endpush
