@extends('layouts.admin')

@section('title', 'AI Görsel Logları')
@section('page_title', 'AI Görsel Logları')
@section('page_description', 'AI ile oluşturulan tüm görsellerin tam log kaydı (model, ölçü, maliyet, süre)')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">AI Görsel Logları</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">AI Görsel Logları</h1>
            <p class="page-subtitle">AI ile üretilen tüm görsellerin detaylı log kayıtları (kaynak, model, ölçü, maliyet, hata)</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.ai-images.index') }}" class="btn-glass">
                <i class="bi bi-magic me-1"></i> Görsel Üret
            </a>
        </div>
    </div>

    {{-- ==================== STAT CARDS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-calendar-day"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Bugün</span>
                    <h3 class="usr-stat-value" data-count="{{ $summary['today']['count'] }}">0</h3>
                    <small class="text-clr-muted">${{ number_format($summary['today']['cost'], 4) }}</small>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple">
                    <i class="bi bi-calendar-month"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Bu Ay</span>
                    <h3 class="usr-stat-value" data-count="{{ $summary['month']['count'] }}">0</h3>
                    <small class="text-clr-muted">${{ number_format($summary['month']['cost'], 2) }}</small>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Başarı</span>
                    <h3 class="usr-stat-value" data-count="{{ $summary['completed'] }}">0</h3>
                    <small class="text-clr-muted">{{ $summary['success_rate'] }}% başarı oranı</small>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Maliyet</span>
                    <h3 class="usr-stat-value">${{ number_format($summary['total']['cost'], 2) }}</h3>
                    <small class="text-clr-muted">{{ number_format($summary['total']['count']) }} üretim · {{ $summary['failed'] }} başarısız</small>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== SOURCE BREAKDOWN ==================== --}}
    @if(! empty($sourceBreakdown))
        <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card-body-custom">
                <h6 class="text-teal mb-3"><i class="bi bi-pie-chart me-1"></i> Kaynak Dağılımı</h6>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($sourceBreakdown as $row)
                        @php
                            // Yalnızca source set olanları tıklanabilir filtre yap.
                            // Eski (migration öncesi) NULL source kayıtları görsel chip olarak kalır
                            // ama tıklanınca filtre temizlemez — anlamlı bir filtre yok.
                            $hasSource = ! empty($row['source']);
                            $isActive  = $hasSource && ($filters['source'] ?? '') === $row['source'];
                            $href      = $hasSource
                                ? route('admin.ai-image-logs.index', array_merge(
                                    request()->except(['source', 'page']),
                                    ['source' => $row['source']],
                                ))
                                : null;
                        @endphp
                        @if($href)
                            <a href="{{ $href }}"
                               class="ail-source-pill {{ $isActive ? 'ail-source-pill--active' : '' }}">
                                <span class="badge {{ \App\Services\AiImageLogService::sourceColor($row['source']) }} me-2">
                                    {{ \App\Services\AiImageLogService::sourceLabel($row['source']) }}
                                </span>
                                <strong>{{ number_format($row['count']) }}</strong>
                                <small class="text-clr-muted ms-2">${{ number_format($row['cost'], 4) }}</small>
                            </a>
                        @else
                            <span class="ail-source-pill ail-source-pill--disabled" title="Eski kayıtlar (kaynak bilgisi yok)">
                                <span class="badge bg-dark me-2">Belirsiz / Eski</span>
                                <strong>{{ number_format($row['count']) }}</strong>
                                <small class="text-clr-muted ms-2">${{ number_format($row['cost'], 4) }}</small>
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- ==================== FILTERS & TOOLBAR ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.ai-image-logs.index') }}" class="cl-toolbar" id="filterForm">
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" id="aiImageLogSearch"
                           placeholder="Prompt veya hata mesajında ara..."
                           value="{{ $filters['q'] ?? '' }}">
                </div>
                <div class="cl-filters">
                    <select class="cl-filter-select" name="source" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Kaynaklar</option>
                        @foreach($sourceLabels as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['source'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select class="cl-filter-select" name="status" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Durumlar</option>
                        <option value="completed" {{ ($filters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Başarılı</option>
                        <option value="failed"    {{ ($filters['status'] ?? '') === 'failed'    ? 'selected' : '' }}>Başarısız</option>
                        <option value="pending"   {{ ($filters['status'] ?? '') === 'pending'   ? 'selected' : '' }}>Beklemede</option>
                    </select>
                    <select class="cl-filter-select" name="model" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Modeller</option>
                        @foreach($distinctModels as $modelName)
                            <option value="{{ $modelName }}" {{ ($filters['model'] ?? '') === $modelName ? 'selected' : '' }}>{{ $modelName }}</option>
                        @endforeach
                    </select>
                    <input type="date" class="cl-filter-select" name="date_from"
                           value="{{ $filters['date_from'] ?? '' }}"
                           onchange="document.getElementById('filterForm').submit()"
                           title="Başlangıç tarihi">
                    <input type="date" class="cl-filter-select" name="date_to"
                           value="{{ $filters['date_to'] ?? '' }}"
                           onchange="document.getElementById('filterForm').submit()"
                           title="Bitiş tarihi">
                </div>
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.ai-image-logs.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
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

    {{-- ==================== LOGS TABLE ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th class="ail-th-thumb">Görsel</th>
                            <th>Prompt</th>
                            <th>Kaynak</th>
                            <th>Durum</th>
                            <th class="d-none d-md-table-cell">Model</th>
                            <th class="d-none d-lg-table-cell">Ölçü</th>
                            <th class="d-none d-md-table-cell">Maliyet</th>
                            <th class="d-none d-lg-table-cell">Süre</th>
                            <th class="d-none d-xl-table-cell">Deneme</th>
                            <th>Tarih</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td data-label="Görsel">
                                    @if($log->isCompleted() && $log->image_path)
                                        <a href="{{ upload_url($log->image_path, 'lg') }}" target="_blank" class="ail-thumb">
                                            <img src="{{ upload_url($log->image_path, 'thumb') }}"
                                                 alt="AI görsel #{{ $log->id }}"
                                                 loading="lazy"
                                                 class="img-fluid">
                                        </a>
                                    @else
                                        <div class="ail-thumb ail-thumb--empty">
                                            <i class="bi bi-{{ $log->isFailed() ? 'x-octagon' : 'hourglass-split' }}"></i>
                                        </div>
                                    @endif
                                </td>
                                <td data-label="Prompt">
                                    <div class="cl-content-info">
                                        <span class="cl-content-title">{{ Str::limit($log->final_prompt, 90) }}</span>
                                        @if($log->error_message)
                                            <span class="cl-content-meta text-danger">
                                                <i class="bi bi-exclamation-circle me-1"></i>{{ Str::limit($log->error_message, 90) }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Kaynak">
                                    <span class="badge {{ \App\Services\AiImageLogService::sourceColor($log->source) }}">
                                        {{ \App\Services\AiImageLogService::sourceLabel($log->source) }}
                                    </span>
                                </td>
                                <td data-label="Durum">
                                    @if($log->isCompleted())
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Başarılı</span>
                                    @elseif($log->isFailed())
                                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Başarısız</span>
                                    @else
                                        <span class="badge bg-secondary"><i class="bi bi-hourglass-split me-1"></i>Beklemede</span>
                                    @endif
                                </td>
                                <td data-label="Model" class="d-none d-md-table-cell">
                                    <span class="usr-meta">{{ $log->model ?? '-' }}</span>
                                </td>
                                <td data-label="Ölçü" class="d-none d-lg-table-cell">
                                    @if($log->width && $log->height)
                                        <span class="usr-meta">{{ $log->width }}×{{ $log->height }}</span>
                                    @else
                                        <span class="usr-meta">{{ $log->aspect_ratio }}</span>
                                    @endif
                                </td>
                                <td data-label="Maliyet" class="d-none d-md-table-cell">
                                    @if($log->cost_usd !== null)
                                        <span class="usr-meta">${{ number_format((float) $log->cost_usd, 4) }}</span>
                                    @else
                                        <span class="usr-meta">-</span>
                                    @endif
                                </td>
                                <td data-label="Süre" class="d-none d-lg-table-cell">
                                    @if($log->generation_time_ms)
                                        <span class="usr-meta">{{ number_format($log->generation_time_ms) }}ms</span>
                                    @else
                                        <span class="usr-meta">-</span>
                                    @endif
                                </td>
                                <td data-label="Deneme" class="d-none d-xl-table-cell">
                                    <span class="usr-meta">{{ $log->attempt_count ?? '-' }}</span>
                                </td>
                                <td data-label="Tarih">
                                    <div class="d-flex flex-column">
                                        <span class="usr-meta">{{ $log->created_at->translatedFormat('d M Y') }}</span>
                                        <span class="usr-meta ail-time-small">{{ $log->created_at->format('H:i:s') }}</span>
                                    </div>
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        <a href="{{ route('admin.ai-image-logs.show', $log) }}" class="usr-action-btn" title="Detay">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button type="button" class="usr-action-btn ail-action-danger"
                                                data-log-id="{{ $log->id }}"
                                                data-log-label="#{{ $log->id }}"
                                                onclick="confirmDeleteAiImageLog(this)"
                                                title="Sil">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-5">
                                    <i class="bi bi-image d-block fs-1 mb-2"></i>
                                    Filtreye uyan AI görsel log kaydı bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.admin.pagination', ['paginator' => $logs, 'itemLabel' => 'kayıt'])
    </div>

@endsection

@push('styles')
<style>
.ail-th-thumb{width:80px}
.ail-thumb{display:inline-block;width:64px;height:64px;border-radius:8px;overflow:hidden;background:var(--bg-input);border:1px solid var(--border-color)}
.ail-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.ail-thumb--empty{display:inline-flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:24px}
.ail-time-small{font-size:11px}
.ail-source-pill{display:inline-flex;align-items:center;padding:8px 14px;border-radius:999px;background:var(--bg-input);border:1px solid var(--border-color);color:var(--text-primary);text-decoration:none;transition:all .15s ease}
.ail-source-pill:hover{border-color:var(--text-teal);transform:translateY(-1px);color:var(--text-primary)}
.ail-source-pill--active{border-color:var(--text-teal);background:color-mix(in srgb, var(--text-teal) 12%, transparent)}
.ail-source-pill--disabled{cursor:default;opacity:.55}
.ail-source-pill--disabled:hover{transform:none;border-color:var(--border-color)}
.ail-action-danger{color:#ef4444}
.ail-action-danger:hover{background:rgba(239,68,68,.15)}
.ail-delete-form{margin:0}
</style>
@endpush

@push('scripts')
<script>
document.getElementById('aiImageLogSearch')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('filterForm').submit();
    }
});

function confirmDeleteAiImageLog(btn) {
    var id = btn.getAttribute('data-log-id');
    var label = btn.getAttribute('data-log-label');
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    if (! window.AdminModal || typeof AdminModal.confirm !== 'function') {
        // AdminModal yoksa native confirm fallback (admin tema yüklenmemişse).
        if (! window.confirm('Log ' + label + ' silinsin mi? Bu işlem geri alınamaz.')) {
            return;
        }
        submitDelete(id, csrf);
        return;
    }

    AdminModal.confirm({
        title: 'AI Görsel Log Silinsin Mi?',
        message: '<strong>Log ' + label + '</strong> kaydını ve ilişkili görseli silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.',
        type: 'danger',
        confirmText: 'Evet, Sil',
        confirmIcon: 'bi bi-trash3',
    }).then(function (confirmed) {
        if (! confirmed) {
            return;
        }
        submitDelete(id, csrf);
    });
}

function submitDelete(id, csrf) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/ai-image-logs/' + id;
    form.innerHTML = '<input type="hidden" name="_token" value="' + csrf + '">' +
                     '<input type="hidden" name="_method" value="DELETE">';
    document.body.appendChild(form);
    form.submit();
}
</script>
@endpush
