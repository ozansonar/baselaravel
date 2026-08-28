@extends('layouts.admin')

@section('title', 'Aktivite Logları')
@section('page_title', 'Aktivite Logları')
@section('page_description', 'Kim ne zaman ne yaptı — sistemdeki tüm değişikliklerin kaydı')

@section('content')
    @php
        $activeEvent = $filters['event'];
        $hasFilter   = $filters['q'] !== '' || $filters['user_id'] !== '' || $filters['model'] !== ''
            || $filters['ip'] !== '' || $filters['from'] !== '' || $filters['to'] !== '' || $activeEvent !== '';
    @endphp

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Aktivite Logları</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Aktivite Logları</h1>
            <p class="page-subtitle">Kim ne zaman ne yaptı — sistemdeki tüm değişikliklerin kaydı</p>
        </div>
        @if($hasFilter)
            <a href="{{ route('admin.audit-logs.index') }}" class="btn-glass">
                <i class="bi bi-arrow-counterclockwise"></i> Filtreleri Sıfırla
            </a>
        @endif
    </div>

    {{-- ==================== SECTION 1: STATS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-teal"><i class="bi bi-clock-history"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Kayıt</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-calendar-day"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Bugün</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['today'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-graph-up"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Son 7 Gün</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['week'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red"><i class="bi bi-trash-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Silme İşlemi</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['deletions'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- Sayfanın ne olduğu ve kayıtların ne kadar kalacağı --}}
    <div class="nt-info-note mb-4" data-aos="fade-up" data-aos-delay="50">
        <i class="bi bi-shield-check"></i>
        <div>
            <strong>Bu kayıtlar panelden değiştirilemez, silinemez.</strong>
            İzlenen kayıtlarda yapılan her oluşturma, güncelleme ve silme işlemi
            kullanıcısı, IP adresi ve değişen alanlarıyla birlikte buraya yazılır.
            {{ $retentionDays }} günden eski kayıtlar haftalık temizlik görevinde otomatik silinir.
            @if($stats['oldest'])
                Listedeki en eski kayıt {{ $stats['oldest']->translatedFormat('d F Y') }} tarihli.
            @endif
        </div>
    </div>

    {{-- ==================== SECTION 2: EVENT TABS ==================== --}}
    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        <a href="{{ route('admin.audit-logs.index', request()->except(['event', 'page'])) }}"
           class="cl-status-tab {{ $activeEvent === '' ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $stats['total'] }}</span>
        </a>
        @foreach($eventTypes as $event)
            @php $eventTotal = (int) ($eventCounts[$event->value] ?? 0); @endphp
            <a href="{{ route('admin.audit-logs.index', array_merge(request()->except('page'), ['event' => $event->value])) }}"
               class="cl-status-tab {{ $activeEvent === $event->value ? 'active' : '' }}">
                <i class="bi {{ $event->icon() }} text-{{ $event->color() }}"></i>
                <span>{{ $event->label() }}</span>
                <span class="cl-tab-count">{{ $eventTotal }}</span>
            </a>
        @endforeach
    </div>

    {{-- ==================== SECTION 3: TOOLBAR ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.audit-logs.index') }}" id="alFilterForm" class="cl-toolbar">
                @if($activeEvent !== '')
                    <input type="hidden" name="event" value="{{ $activeEvent }}">
                @endif

                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Açıklama, IP veya adres içinde ara..." data-fv-ignore>
                </div>

                {{-- Alanların hepsi başlıklı: seçim kutuları ile tarih alanları
                     aynı hizada dursun. --}}
                <div class="cl-filters al-filters">
                    <div class="al-field">
                        <span>Kullanıcı</span>
                        <select class="cl-filter-select" name="user_id" aria-label="Kullanıcı"
                                onchange="document.getElementById('alFilterForm').submit()" data-fv-ignore>
                            <option value="">Tüm kullanıcılar</option>
                            <option value="0" {{ $filters['user_id'] === '0' ? 'selected' : '' }}>Sistem (kullanıcısız)</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ (string) $filters['user_id'] === (string) $user->id ? 'selected' : '' }}>
                                    {{ trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="al-field">
                        <span>Kayıt türü</span>
                        <select class="cl-filter-select" name="model" aria-label="Kayıt türü"
                                onchange="document.getElementById('alFilterForm').submit()" data-fv-ignore>
                            <option value="">Tüm kayıt türleri</option>
                            @foreach($modelOptions as $class => $option)
                                <option value="{{ $class }}" {{ $filters['model'] === $class ? 'selected' : '' }}>
                                    {{ $option['label'] }} ({{ $option['count'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="al-field">
                        <span>IP adresi</span>
                        <select class="cl-filter-select" name="ip" aria-label="IP adresi"
                                onchange="document.getElementById('alFilterForm').submit()" data-fv-ignore>
                            <option value="">Tüm IP adresleri</option>
                            @foreach($ipOptions as $ip => $count)
                                <option value="{{ $ip }}" {{ $filters['ip'] === (string) $ip ? 'selected' : '' }}>
                                    {{ $ip }} ({{ $count }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="al-field">
                        <span>Başlangıç</span>
                        <input type="date" class="cl-filter-select" name="from" value="{{ $filters['from'] }}" aria-label="Başlangıç tarihi" data-fv-ignore>
                    </div>

                    <div class="al-field">
                        <span>Bitiş</span>
                        <input type="date" class="cl-filter-select" name="to" value="{{ $filters['to'] }}" aria-label="Bitiş tarihi" data-fv-ignore>
                    </div>

                    <div class="al-field al-field--actions ms-auto">
                        <div class="cl-toolbar-actions">
                            <button type="submit" class="usr-action-btn" title="Süz"><i class="bi bi-funnel"></i></button>
                            <a href="{{ route('admin.audit-logs.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                            <div class="cl-per-page">
                                <label for="alPerPage">Göster:</label>
                                <select id="alPerPage" name="per_page" onchange="document.getElementById('alFilterForm').submit()" data-fv-ignore>
                                    @foreach($perPageOptions as $option)
                                        <option value="{{ $option }}" {{ $perPage === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <x-export-menu export="audit-logs" :total="$logs->total()" />
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ==================== SECTION 4: TABLE ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table al-table">
                    <thead>
                        <tr>
                            <th>Zaman</th>
                            <th class="d-none d-lg-table-cell">Kullanıcı</th>
                            <th>İşlem</th>
                            <th class="d-none d-xl-table-cell">Kayıt</th>
                            <th>Açıklama</th>
                            <th class="d-none d-xxl-table-cell">IP</th>
                            <th class="cl-th-actions">Detay</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            @php
                                $changed = $log->event === \App\Enums\AuditEvent::Updated ? $log->changedFields() : [];
                                $actor = $log->user
                                    ? (trim(($log->user->first_name ?? '') . ' ' . ($log->user->last_name ?? '')) ?: $log->user->email)
                                    : null;
                            @endphp
                            <tr>
                                <td data-label="Zaman">
                                    <span class="al-time">{{ $log->created_at?->translatedFormat('d M Y H:i') }}</span>
                                    <span class="cl-content-meta">{{ $log->created_at?->diffForHumans() }}</span>
                                </td>
                                <td data-label="Kullanıcı" class="d-none d-lg-table-cell">
                                    @if($actor !== null)
                                        <div class="cl-author-cell">
                                            <img src="https://ui-avatars.com/api/?name={{ urlencode($actor) }}&background=14b8a6&color=fff&size=28" alt="{{ $actor }}">
                                            <span>{{ $actor }}</span>
                                        </div>
                                    @else
                                        <span class="al-system"><i class="bi bi-cpu me-1"></i>Sistem</span>
                                    @endif
                                </td>
                                <td data-label="İşlem">
                                    <span class="usr-status-badge {{ $log->event === \App\Enums\AuditEvent::Deleted ? 'suspended' : ($log->event === \App\Enums\AuditEvent::Created ? 'active' : ($log->event === \App\Enums\AuditEvent::Updated ? 'pending' : 'inactive')) }}">
                                        <i class="bi {{ $log->event?->icon() }} me-1"></i>{{ $log->eventLabel() }}
                                    </span>
                                </td>
                                <td data-label="Kayıt" class="d-none d-xl-table-cell">
                                    @if($log->auditable_type)
                                        <span class="cl-category-badge">{{ $log->modelLabel() }}</span>
                                        <span class="cl-content-meta">#{{ $log->auditable_id }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="Açıklama">
                                    <span class="al-label">{{ $log->label ?: $log->modelLabel() . ' #' . $log->auditable_id }}</span>
                                    @if($changed !== [])
                                        <span class="cl-content-meta">
                                            <i class="bi bi-pencil-square me-1"></i>{{ count($changed) }} alan değişti:
                                            {{ \Illuminate\Support\Str::limit(implode(', ', array_keys($changed)), 60) }}
                                        </span>
                                    @endif
                                </td>
                                <td data-label="IP" class="d-none d-xxl-table-cell">
                                    <span class="al-ip">{{ $log->ip_address ?? '—' }}</span>
                                </td>
                                <td data-label="Detay">
                                    <div class="usr-actions">
                                        <a class="usr-action-btn" title="Detay" href="{{ route('admin.audit-logs.show', $log->id) }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-clock-history d-block fs-1 mb-2 opacity-50"></i>
                                    @if($hasFilter)
                                        Bu filtreyle eşleşen kayıt yok.
                                        <br>
                                        <a href="{{ route('admin.audit-logs.index') }}" class="text-teal">Filtreleri temizle</a>
                                    @else
                                        Henüz kayıt yok. İzlenen bir kayıt değiştiğinde burada görünecek.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('partials.admin.pagination', ['paginator' => $logs, 'itemLabel' => 'kayıt'])
        </div>
    </div>

    {{-- ==================== SECTION 5: ÖZET ==================== --}}
    @if($stats['total'] > 0)
        <div class="row g-4 mb-4">
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="50">
                <div class="nt-card">
                    <div class="nt-card-header">
                        <div class="nt-card-icon c-teal"><i class="bi bi-person-check"></i></div>
                        <h6>En Çok İşlem Yapanlar</h6>
                    </div>
                    <div class="nt-card-body">
                        <div class="nt-summary-list">
                            @foreach($topActors as $actor)
                                <div class="nt-summary-row">
                                    <div class="nt-summary-dot c-teal"></div>
                                    <span class="nt-summary-label">{{ $actor['name'] }}</span>
                                    <div class="nt-summary-bar">
                                        <div class="nt-summary-fill c-teal nt-summary-fill--{{ $actor['percent'] }}"></div>
                                    </div>
                                    <span class="nt-summary-num">{{ $actor['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
                <div class="nt-card">
                    <div class="nt-card-header">
                        <div class="nt-card-icon c-purple"><i class="bi bi-collection"></i></div>
                        <h6>Neler Kaydediliyor?</h6>
                    </div>
                    <div class="nt-card-body">
                        <div class="nt-summary-list">
                            @forelse($modelOptions as $class => $option)
                                <div class="nt-summary-row">
                                    <div class="nt-summary-dot c-purple"></div>
                                    <span class="nt-summary-label">{{ $option['label'] }}</span>
                                    <div class="nt-summary-bar">
                                        <div class="nt-summary-fill c-purple nt-summary-fill--{{ $stats['total'] > 0 ? (int) (round($option['count'] / $stats['total'] * 100 / 5) * 5) : 0 }}"></div>
                                    </div>
                                    <span class="nt-summary-num">{{ $option['count'] }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">Henüz izlenen bir kayıt değişmedi.</p>
                            @endforelse
                        </div>

                        <div class="nt-auto-clean">
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <span class="nt-auto-clean-title"><i class="bi bi-recycle me-2"></i>Saklama</span>
                                    <small>{{ $retentionDays }} günden eski kayıtlar her pazar 03:30'da otomatik siliniyor.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
