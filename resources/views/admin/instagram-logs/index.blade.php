@extends('layouts.admin')

@section('title', 'Instagram API Logları')
@section('page_title', 'Instagram API Logları')
@section('page_description', 'Meta Graph API çağrılarının başarılı/başarısız kayıtları, hata mesajları ve cron sağlık durumu.')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/admin/css/instagram-logs.css') }}">
@endpush

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.instagram-posts.index') }}" class="breadcrumb-link"><i class="bi bi-instagram me-1"></i>Instagram Gönderileri</a>
            </li>
            <li class="breadcrumb-item active text-teal">API Logları</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-title">Instagram API Logları</h1>
            <p class="page-subtitle">Her API çağrısının (container, publish, test, refresh, FB cross-post) ham detayı.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.instagram-posts.index') }}" class="btn-glass">
                <i class="bi bi-arrow-left me-1"></i> Gönderilere Dön
            </a>
        </div>
    </div>

    {{-- CRON HEARTBEAT --}}
    <div class="ig-log-heartbeat mb-4 {{ $cronHeartbeat['healthy'] ? 'is-healthy' : 'is-unhealthy' }}" data-aos="fade-up">
        <div class="ig-log-heartbeat-icon">
            @if($cronHeartbeat['healthy'])
                <i class="bi bi-heart-pulse-fill"></i>
            @else
                <i class="bi bi-exclamation-octagon-fill"></i>
            @endif
        </div>
        <div class="ig-log-heartbeat-info">
            <div class="ig-log-heartbeat-title">
                @if($cronHeartbeat['healthy'])
                    Cron Sağlıklı Çalışıyor (her gün 19:00)
                @elseif($cronHeartbeat['last_run'] === null)
                    Cron Hiç Çalışmamış
                @else
                    Cron {{ $cronHeartbeat['threshold_label'] }}'tir Çalışmıyor
                @endif
            </div>
            <div class="ig-log-heartbeat-detail">
                @if($cronHeartbeat['last_run'])
                    Son çalışma: <strong>{{ $cronHeartbeat['last_run'] }}</strong>
                    @if($cronHeartbeat['last_run_human'])
                        <span class="text-muted">({{ $cronHeartbeat['last_run_human'] }})</span>
                    @endif
                @else
                    Linux crontab'ında <code>* * * * * cd /path && php artisan schedule:run</code> tanımlı mı kontrol et.
                @endif
            </div>
        </div>
    </div>

    {{-- STAT CARDS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-journal-text"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Log</span>
                    <h3 class="usr-stat-value">{{ number_format($stats['total'], 0, ',', '.') }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">24 Saat Başarı Oranı</span>
                    <h3 class="usr-stat-value">
                        @if($stats['success_rate_24h'] !== null)
                            %{{ $stats['success_rate_24h'] }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </h3>
                    <small class="text-muted">{{ $stats['success_24h'] }} başarılı / {{ $stats['failed_24h'] }} başarısız</small>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-graph-up"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">7 Gün Başarı Oranı</span>
                    <h3 class="usr-stat-value">
                        @if($stats['success_rate_7d'] !== null)
                            %{{ $stats['success_rate_7d'] }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </h3>
                    <small class="text-muted">{{ $stats['success_7d'] }} başarılı / {{ $stats['failed_7d'] }} başarısız</small>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red"><i class="bi bi-stopwatch"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Ortalama Süre (7g)</span>
                    <h3 class="usr-stat-value">
                        @if($stats['avg_duration_ms'] !== null)
                            {{ number_format($stats['avg_duration_ms'], 0, ',', '.') }} <small>ms</small>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </h3>
                </div>
            </div>
        </div>
    </div>

    {{-- TOOLBAR (üst aksiyon barı) --}}
    <div class="ig-log-toolbar mb-4">
        <form method="POST" action="{{ route('admin.instagram-logs.clear') }}" class="d-inline ig-log-clear-form">
            @csrf
            <input type="hidden" name="mode" value="old">
            <input type="hidden" name="days" value="30">
            <button type="submit" class="btn-glass" data-confirm="30 günden eski {{ $oldLogsCount }} log kaydı silinsin mi?">
                <i class="bi bi-trash3 me-1"></i>
                30 Günden Eski Logları Sil
                @if($oldLogsCount > 0)
                    <span class="ig-log-badge-count">{{ $oldLogsCount }}</span>
                @endif
            </button>
        </form>

        <form method="POST" action="{{ route('admin.instagram-logs.clear') }}" class="d-inline ig-log-clear-form">
            @csrf
            <input type="hidden" name="mode" value="all">
            <button type="submit" class="btn-glass btn-glass-danger" data-confirm="DİKKAT: TÜM Instagram API logları ({{ number_format($totalLogsCount, 0, ',', '.') }} kayıt) silinecek. Bu geri alınamaz. Devam edilsin mi?">
                <i class="bi bi-fire me-1"></i>
                Tüm Logları Sil
            </button>
        </form>
    </div>

    {{-- FİLTRELER --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.instagram-logs.index') }}" class="ig-log-filter-grid">
                <div>
                    <label class="form-label small text-muted mb-1">Action</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        @foreach($actionOptions as $key => $label)
                            <option value="{{ $key }}" {{ $filters['action'] === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">Durum</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <option value="success" {{ $filters['status'] === 'success' ? 'selected' : '' }}>Başarılı</option>
                        <option value="failed"  {{ $filters['status'] === 'failed'  ? 'selected' : '' }}>Başarısız</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">Post ID</label>
                    <input type="number" name="post_id" min="1" value="{{ $filters['post_id'] }}"
                           class="form-control form-control-sm" placeholder="örn. 42">
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">Tarihten</label>
                    <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">Tarihe</label>
                    <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">Sayfa Başına</label>
                    <select name="per_page" class="form-select form-select-sm">
                        @foreach([25, 50, 100, 200] as $pp)
                            <option value="{{ $pp }}" {{ $filters['per_page'] === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ig-log-filter-actions">
                    <button type="submit" class="btn-teal">
                        <i class="bi bi-funnel me-1"></i> Filtrele
                    </button>
                    <a href="{{ route('admin.instagram-logs.index') }}" class="btn-glass">
                        <i class="bi bi-x-lg me-1"></i> Temizle
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- TABLO --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table ig-log-table">
                    <thead>
                        <tr>
                            <th class="ig-log-th-time">Zaman</th>
                            <th class="ig-log-th-post">Post</th>
                            <th>Action</th>
                            <th class="ig-log-th-status">Durum</th>
                            <th class="ig-log-th-dur">Süre</th>
                            <th>Hata Önizleme</th>
                            <th class="cl-th-actions ig-log-th-act">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td data-label="Zaman" class="ig-log-cell-time">
                                <div class="ig-log-time">{{ $log->created_at->format('d.m.Y') }}</div>
                                <div class="ig-log-time-sub">{{ $log->created_at->format('H:i:s') }}</div>
                            </td>
                            <td data-label="Post">
                                @if($log->instagram_post_id && $log->post)
                                    <a href="{{ route('admin.instagram-posts.edit', $log->instagram_post_id) }}"
                                       class="ig-log-post-thumb" title="Post #{{ $log->instagram_post_id }}">
                                        <img src="{{ upload_url($log->post->image_path, 'thumb') }}" alt="Post #{{ $log->instagram_post_id }}">
                                        <span class="ig-log-post-id">#{{ $log->instagram_post_id }}</span>
                                    </a>
                                @elseif($log->instagram_post_id)
                                    <span class="text-muted">#{{ $log->instagram_post_id }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td data-label="Action">
                                <span class="ig-log-action-badge">
                                    <code>{{ $log->action }}</code>
                                </span>
                            </td>
                            <td data-label="Durum">
                                <span class="ig-log-status-badge {{ $log->status === 'success' ? 'is-success' : 'is-failed' }}">
                                    @if($log->status === 'success')
                                        <i class="bi bi-check-circle"></i>Başarılı
                                    @else
                                        <i class="bi bi-x-circle"></i>Başarısız
                                    @endif
                                </span>
                            </td>
                            <td data-label="Süre">
                                @if($log->api_duration_ms !== null)
                                    <span class="ig-log-duration {{ $log->api_duration_ms > 5000 ? 'is-slow' : '' }}">
                                        {{ number_format($log->api_duration_ms, 0, ',', '.') }} ms
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td data-label="Hata">
                                @if($log->error_message)
                                    <span class="ig-log-error" title="{{ $log->error_message }}">
                                        <i class="bi bi-exclamation-circle me-1"></i>{{ \Illuminate\Support\Str::limit($log->error_message, 90) }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td data-label="İşlem">
                                <button type="button" class="usr-action-btn ig-log-detail-btn"
                                        data-log-id="{{ $log->id }}"
                                        title="Detayları Görüntüle">
                                    <i class="bi bi-eye"></i>
                                </button>
                                @if($log->instagram_post_id && $log->post && $log->post->status === \App\Enums\InstagramPostStatus::Failed)
                                <form method="POST" action="{{ route('admin.instagram-logs.replay', $log->instagram_post_id) }}" class="d-inline ig-log-replay-form">
                                    @csrf
                                    <button type="submit" class="usr-action-btn success" title="Postu Yeniden Dene"
                                            data-confirm="#{{ $log->instagram_post_id }} numaralı post yeniden denemeye alınsın mı?">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-journal-x display-4 d-block mb-2"></i>
                                    <strong>Log kaydı bulunamadı</strong>
                                    <div class="small mt-1">
                                        @if($filters['action'] || $filters['status'] || $filters['post_id'] || $filters['from'] || $filters['to'])
                                            Filtreleri temizleyip tekrar deneyin.
                                        @else
                                            API çağrıları otomatik kaydedilir. Henüz hiç çağrı yapılmamış.
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- PAGINATION --}}
    @include('partials.admin.pagination', ['paginator' => $logs, 'itemLabel' => 'log'])

    {{-- DETAY MODAL --}}
    <div class="modal fade" id="igLogDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content ig-log-modal">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-journal-code me-2"></i>
                        API Log Detayı
                        <span class="ig-log-modal-id ms-2" id="igLogModalId"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    {{-- Loading state --}}
                    <div id="igLogModalLoading" class="text-center py-5">
                        <div class="spinner-border text-teal" role="status"></div>
                        <div class="mt-2 small text-muted">Yükleniyor…</div>
                    </div>

                    {{-- Error state --}}
                    <div id="igLogModalError" class="alert alert-danger d-none">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <span id="igLogModalErrorMsg"></span>
                    </div>

                    {{-- Content --}}
                    <div id="igLogModalContent" class="d-none">
                        {{-- Özet --}}
                        <div class="ig-log-summary-grid">
                            <div class="ig-log-summary-item">
                                <span class="ig-log-summary-label">Action</span>
                                <span class="ig-log-summary-value" id="igLogModalAction"></span>
                            </div>
                            <div class="ig-log-summary-item">
                                <span class="ig-log-summary-label">Durum</span>
                                <span class="ig-log-summary-value" id="igLogModalStatus"></span>
                            </div>
                            <div class="ig-log-summary-item">
                                <span class="ig-log-summary-label">Süre</span>
                                <span class="ig-log-summary-value" id="igLogModalDuration"></span>
                            </div>
                            <div class="ig-log-summary-item">
                                <span class="ig-log-summary-label">Tarih</span>
                                <span class="ig-log-summary-value" id="igLogModalDate"></span>
                            </div>
                            <div class="ig-log-summary-item">
                                <span class="ig-log-summary-label">Post</span>
                                <span class="ig-log-summary-value" id="igLogModalPost"></span>
                            </div>
                        </div>

                        {{-- Hata mesajı (varsa) --}}
                        <div id="igLogModalErrorBox" class="ig-log-error-box d-none">
                            <div class="ig-log-error-title">
                                <i class="bi bi-exclamation-octagon-fill me-1"></i> Hata Mesajı
                            </div>
                            <div class="ig-log-error-msg" id="igLogModalErrorText"></div>
                        </div>

                        {{-- Tabs: request / response --}}
                        <ul class="nav nav-tabs ig-log-tabs mt-3" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#igLogTabRequest" type="button">
                                    <i class="bi bi-arrow-up-right me-1"></i> Request Payload
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#igLogTabResponse" type="button">
                                    <i class="bi bi-arrow-down-left me-1"></i> Response Body
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content ig-log-tab-content">
                            <div class="tab-pane fade show active" id="igLogTabRequest" role="tabpanel">
                                <div class="ig-log-json-toolbar">
                                    <span class="text-muted small">Meta API'ye gönderilen parametreler (token gizli)</span>
                                    <button type="button" class="btn-glass btn-glass-sm ig-log-copy-btn" data-target="igLogModalRequest">
                                        <i class="bi bi-clipboard me-1"></i> Kopyala
                                    </button>
                                </div>
                                <pre class="ig-log-json"><code id="igLogModalRequest"></code></pre>
                            </div>
                            <div class="tab-pane fade" id="igLogTabResponse" role="tabpanel">
                                <div class="ig-log-json-toolbar">
                                    <span class="text-muted small">Meta API'den dönen ham yanıt</span>
                                    <button type="button" class="btn-glass btn-glass-sm ig-log-copy-btn" data-target="igLogModalResponse">
                                        <i class="bi bi-clipboard me-1"></i> Kopyala
                                    </button>
                                </div>
                                <pre class="ig-log-json"><code id="igLogModalResponse"></code></pre>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-glass" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/instagram-logs.js') }}"></script>
@endpush
