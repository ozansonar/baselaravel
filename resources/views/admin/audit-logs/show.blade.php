@extends('layouts.admin')

@section('title', 'Aktivite Log Detay')
@section('page_title', 'Kayıt Detayı')

@section('content')
    @php
        $actor = $log->user
            ? (trim(($log->user->first_name ?? '') . ' ' . ($log->user->last_name ?? '')) ?: $log->user->email)
            : null;
        $detail = $log->detailValues();
        $eventBadge = match ($log->event) {
            \App\Enums\AuditEvent::Deleted => 'suspended',
            \App\Enums\AuditEvent::Created => 'active',
            \App\Enums\AuditEvent::Updated => 'pending',
            default                        => 'inactive',
        };
    @endphp

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.audit-logs.index') }}" class="breadcrumb-link">Aktivite Logları</a>
            </li>
            <li class="breadcrumb-item active text-teal">Kayıt #{{ $log->id }}</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">{{ $log->label ?: $log->modelLabel() . ' #' . $log->auditable_id }}</h1>
            <p class="page-subtitle">
                <span class="usr-status-badge {{ $eventBadge }}">
                    <i class="bi {{ $log->event?->icon() }} me-1"></i>{{ $log->eventLabel() }}
                </span>
                <span class="al-header-time">
                    {{ $log->created_at?->translatedFormat('d F Y, H:i:s') }} · {{ $log->created_at?->diffForHumans() }}
                </span>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($neighbours['previous'])
                <a href="{{ route('admin.audit-logs.show', $neighbours['previous']->id) }}" class="btn-glass" title="Bir önceki (daha eski) kayıt">
                    <i class="bi bi-chevron-left"></i> Önceki
                </a>
            @endif
            @if($neighbours['next'])
                <a href="{{ route('admin.audit-logs.show', $neighbours['next']->id) }}" class="btn-glass" title="Bir sonraki (daha yeni) kayıt">
                    Sonraki <i class="bi bi-chevron-right"></i>
                </a>
            @endif
            <a href="{{ route('admin.audit-logs.index') }}" class="btn-teal">
                <i class="bi bi-list-ul"></i> Listeye Dön
            </a>
        </div>
    </div>

    {{-- ==================== SECTION 1: ÖZET ==================== --}}
    <div class="al-meta-grid mb-4" data-aos="fade-up">
        <div class="al-meta-card">
            <div class="al-meta-icon"><i class="bi bi-person-badge"></i></div>
            <div class="al-meta-body">
                <span class="al-meta-label">Kullanıcı</span>
                <span class="al-meta-value">{{ $actor ?? 'Sistem' }}</span>
                @if($actor && $log->user?->email)
                    <span class="al-meta-hint">{{ $log->user->email }}</span>
                @else
                    <span class="al-meta-hint">Zamanlanmış görev</span>
                @endif
            </div>
        </div>

        <div class="al-meta-card">
            <div class="al-meta-icon"><i class="bi bi-clock-history"></i></div>
            <div class="al-meta-body">
                <span class="al-meta-label">Zaman</span>
                <span class="al-meta-value">{{ $log->created_at?->translatedFormat('d M Y H:i') }}</span>
                <span class="al-meta-hint">{{ $log->created_at?->diffForHumans() }}</span>
            </div>
        </div>

        <div class="al-meta-card">
            <div class="al-meta-icon"><i class="bi bi-hdd-network"></i></div>
            <div class="al-meta-body">
                <span class="al-meta-label">IP Adresi</span>
                <span class="al-meta-value al-ip">{{ $log->ip_address ?? '—' }}</span>
                <span class="al-meta-hint">{{ $log->id }} numaralı kayıt</span>
            </div>
        </div>

        <div class="al-meta-card">
            <div class="al-meta-icon"><i class="bi bi-collection"></i></div>
            <div class="al-meta-body">
                <span class="al-meta-label">Kayıt</span>
                <span class="al-meta-value">
                    @if($log->auditable_type)
                        {{ $log->modelLabel() }} <span class="al-meta-id">#{{ $log->auditable_id }}</span>
                    @else
                        Modelsiz işlem
                    @endif
                </span>
                <span class="al-meta-hint">{{ $log->auditable_type ?? 'Sisteme ait olay' }}</span>
            </div>
        </div>
    </div>

    {{-- Hızlı süzgeçler: bu kayıttan yola çıkıp listeye dönmek --}}
    <div class="nt-info-note mb-4" data-aos="fade-up" data-aos-delay="50">
        <i class="bi bi-funnel-fill"></i>
        <div>
            <strong>Bu kayıttan yola çıkarak listeyi süzebilirsiniz.</strong>
            Aynı kişinin, aynı adresin veya aynı kayıt türünün diğer işlemleri tek tıkla listelenir.
            <div class="al-quick-filters">
                @if($log->user_id !== null)
                    <a href="{{ route('admin.audit-logs.index', ['user_id' => $log->user_id]) }}" class="al-quick-filter">
                        <i class="bi bi-person"></i> Bu kullanıcının işlemleri
                    </a>
                @else
                    <a href="{{ route('admin.audit-logs.index', ['user_id' => 0]) }}" class="al-quick-filter">
                        <i class="bi bi-cpu"></i> Sistemin işlemleri
                    </a>
                @endif
                @if($log->ip_address)
                    <a href="{{ route('admin.audit-logs.index', ['ip' => $log->ip_address]) }}" class="al-quick-filter">
                        <i class="bi bi-hdd-network"></i> Bu IP'den yapılanlar
                    </a>
                @endif
                @if($log->auditable_type)
                    <a href="{{ route('admin.audit-logs.index', ['model' => $log->auditable_type]) }}" class="al-quick-filter">
                        <i class="bi bi-collection"></i> Bu kayıt türü
                    </a>
                @endif
                <a href="{{ route('admin.audit-logs.index', ['event' => $log->event?->value]) }}" class="al-quick-filter">
                    <i class="bi {{ $log->event?->icon() }}"></i> {{ $log->eventLabel() }} işlemleri
                </a>
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 2: DEĞERLER ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="card-header-custom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6>
                <i class="bi bi-arrow-left-right me-2 text-teal"></i>{{ $detail['title'] }}
                @if($detail['rows'] !== [])
                    <span class="cl-tab-count ms-1">{{ count($detail['rows']) }}</span>
                @endif
            </h6>
            <small class="al-card-hint">{{ $detail['hint'] }}</small>
        </div>
        <div class="card-body-custom p-0">
            @if($detail['rows'] === [])
                <div class="text-center text-muted py-5">
                    <i class="bi bi-file-earmark-x d-block fs-1 mb-2 opacity-50"></i>
                    Bu işlem için kaydedilmiş alan değeri yok.
                </div>
            @else
                <div class="table-responsive">
                    <table class="cl-table al-diff-table">
                        <thead>
                            <tr>
                                <th>Alan</th>
                                @if($detail['mode'] === 'diff')
                                    <th>Eski değer</th>
                                    <th>Yeni değer</th>
                                @else
                                    <th>Değer</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($detail['rows'] as $field => $value)
                                <tr>
                                    <td data-label="Alan"><span class="al-field-name">{{ $field }}</span></td>
                                    @if($detail['mode'] === 'diff')
                                        @php
                                            $old = \App\Models\AuditLog::formatValue($value['old']);
                                            $new = \App\Models\AuditLog::formatValue($value['new']);
                                        @endphp
                                        <td data-label="Eski değer">
                                            <span class="al-value al-value--old" title="{{ $old }}">{{ \Illuminate\Support\Str::limit($old, 120) }}</span>
                                        </td>
                                        <td data-label="Yeni değer">
                                            <span class="al-value al-value--new" title="{{ $new }}">{{ \Illuminate\Support\Str::limit($new, 120) }}</span>
                                        </td>
                                    @else
                                        @php $plain = \App\Models\AuditLog::formatValue($value); @endphp
                                        <td data-label="Değer">
                                            <span class="al-value" title="{{ $plain }}">{{ \Illuminate\Support\Str::limit($plain, 200) }}</span>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ==================== SECTION 3: TEKNİK BİLGİ ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="150">
            <div class="card-dark h-100">
                <div class="card-header-custom">
                    <h6><i class="bi bi-info-circle me-2 text-teal"></i>İstek Bilgileri</h6>
                </div>
                <div class="card-body-custom">
                    <div class="al-detail-list">
                        <div class="al-detail-row">
                            <span class="al-detail-label">Adres</span>
                            <span class="al-detail-value">
                                @if($log->url)
                                    <span class="al-ip">{{ $log->url }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </span>
                        </div>
                        <div class="al-detail-row">
                            <span class="al-detail-label">Tarayıcı</span>
                            <span class="al-detail-value">{{ $log->user_agent ?: '—' }}</span>
                        </div>
                        <div class="al-detail-row">
                            <span class="al-detail-label">Model sınıfı</span>
                            <span class="al-detail-value"><span class="al-ip">{{ $log->auditable_type ?? '—' }}</span></span>
                        </div>
                        <div class="al-detail-row">
                            <span class="al-detail-label">Kayıt no</span>
                            <span class="al-detail-value">#{{ $log->id }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="200">
            <div class="card-dark h-100">
                <div class="card-header-custom">
                    <h6><i class="bi bi-shield-check me-2 text-teal"></i>Bu kayıt hakkında</h6>
                </div>
                <div class="card-body-custom">
                    <div class="nt-summary-list">
                        <div class="nt-hint-row">
                            <i class="bi bi-lock"></i>
                            <span>Denetim kayıtları panelden düzenlenemez ve silinemez; değeri dokunulmamış olmasından gelir.</span>
                        </div>
                        <div class="nt-hint-row">
                            <i class="bi bi-eye-slash"></i>
                            <span>Şifre gibi hassas alanlar kayda yazılmadan önce ayıklanır, burada görünmez.</span>
                        </div>
                        <div class="nt-hint-row">
                            <i class="bi bi-recycle"></i>
                            <span>{{ $retentionDays }} günden eski kayıtlar haftalık temizlik görevinde otomatik silinir.</span>
                        </div>
                        <div class="nt-hint-row">
                            <i class="bi bi-braces"></i>
                            <span>Ham JSON aşağıdaki bölümden açılabilir; tabloda okunmayan bir ayrıntı kaldıysa oradadır.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 4: HAM JSON ==================== --}}
    @if($log->old_values || $log->new_values)
        <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="250">
            <div class="card-header-custom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6><i class="bi bi-braces me-2 text-teal"></i>Ham kayıt (JSON)</h6>
                <button type="button" class="btn-glass sm" data-bs-toggle="collapse"
                        data-bs-target="#alRawJson" aria-expanded="false" aria-controls="alRawJson">
                    <i class="bi bi-chevron-down"></i> Göster / Gizle
                </button>
            </div>
            <div class="collapse" id="alRawJson">
                <div class="card-body-custom">
                    <div class="row g-4">
                        @if($log->old_values)
                            <div class="col-lg-6">
                                <span class="al-json-title"><i class="bi bi-dash-circle me-1"></i>Eski değerler</span>
                                <pre class="al-json al-json--old">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        @endif
                        @if($log->new_values)
                            <div class="col-lg-6">
                                <span class="al-json-title"><i class="bi bi-plus-circle me-1"></i>Yeni değerler</span>
                                <pre class="al-json al-json--new">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
