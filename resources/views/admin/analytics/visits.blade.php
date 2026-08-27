@extends('layouts.admin')

@section('title', 'Tüm Ziyaretler')
@section('page_title', 'Tüm Ziyaretler')
@section('page_description', 'Filtreleyebileceğiniz detaylı ziyaret log kaydı')

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.analytics.index') }}" class="breadcrumb-link">Analitik</a>
            </li>
            <li class="breadcrumb-item active text-teal">Tüm Ziyaretler</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-list-ul text-teal me-2"></i>Tüm Ziyaretler</h1>
            <p class="page-subtitle">Filtreleyebileceğiniz detaylı ziyaret log kaydı</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.analytics.index') }}" class="btn-glass">
                <i class="bi bi-bar-chart-line"></i> Analitik Paneli
            </a>
        </div>
    </div>


    <!-- ==================== STAT CARDS ==================== -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="anl-kpi-card">
                <div class="anl-kpi-header">
                    <div class="anl-kpi-icon anl-kpi-icon-teal">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                </div>
                <h3 class="anl-kpi-value" data-target="{{ $totalAll }}">{{ number_format($totalAll) }}</h3>
                <span class="anl-kpi-label">Toplam Kayıt</span>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="anl-kpi-card">
                <div class="anl-kpi-header">
                    <div class="anl-kpi-icon anl-kpi-icon-blue">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <h3 class="anl-kpi-value" data-target="{{ $totalHumans }}">{{ number_format($totalHumans) }}</h3>
                <span class="anl-kpi-label">İnsan Ziyareti</span>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="anl-kpi-card">
                <div class="anl-kpi-header">
                    <div class="anl-kpi-icon anl-kpi-icon-orange">
                        <i class="bi bi-robot"></i>
                    </div>
                </div>
                <h3 class="anl-kpi-value" data-target="{{ $totalBots }}">{{ number_format($totalBots) }}</h3>
                <span class="anl-kpi-label">Bot Ziyareti</span>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="anl-kpi-card">
                <div class="anl-kpi-header">
                    <div class="anl-kpi-icon anl-kpi-icon-purple">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                </div>
                <h3 class="anl-kpi-value" data-target="{{ $todayCount }}">{{ number_format($todayCount) }}</h3>
                <span class="anl-kpi-label">Bugün</span>
            </div>
        </div>
    </div>


    <!-- ==================== FILTERS & TOOLBAR ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.analytics.visits') }}" class="cl-toolbar" id="visitsFilterForm">
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="url" id="urlSearch" placeholder="Sayfa URL'si ile ara..." value="{{ $filters['url'] ?? '' }}" data-fv-ignore>
                </div>
                <div class="cl-filters">
                    <select class="cl-filter-select" name="is_bot" id="filterBot" onchange="document.getElementById('visitsFilterForm').submit()" data-fv-ignore>
                        <option value="">Tüm Trafik</option>
                        <option value="0" {{ ($filters['is_bot'] ?? '') === '0' ? 'selected' : '' }}>Sadece İnsan</option>
                        <option value="1" {{ ($filters['is_bot'] ?? '') === '1' ? 'selected' : '' }}>Sadece Bot</option>
                    </select>
                    <select class="cl-filter-select" name="device_type" id="filterDevice" onchange="document.getElementById('visitsFilterForm').submit()" data-fv-ignore>
                        <option value="">Tüm Cihazlar</option>
                        @foreach(['desktop' => 'Masaüstü', 'mobile' => 'Mobil', 'tablet' => 'Tablet', 'bot' => 'Bot', 'other' => 'Diğer'] as $val => $label)
                            <option value="{{ $val }}" {{ ($filters['device_type'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="from" class="cl-filter-select" value="{{ $filters['from'] ?? '' }}" placeholder="Başlangıç" title="Başlangıç tarihi" onchange="document.getElementById('visitsFilterForm').submit()" data-fv-ignore>
                    <input type="date" name="to" class="cl-filter-select" value="{{ $filters['to'] ?? '' }}" placeholder="Bitiş" title="Bitiş tarihi" onchange="document.getElementById('visitsFilterForm').submit()" data-fv-ignore>
                </div>
                <div class="cl-toolbar-actions">
                    @if(!empty(array_filter($filters)))
                        <a href="{{ route('admin.analytics.visits') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>


    <!-- ==================== VISITS TABLE ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Sayfa</th>
                            <th class="d-none d-lg-table-cell">IP</th>
                            <th>Cihaz</th>
                            <th class="d-none d-md-table-cell">Tarayıcı</th>
                            <th class="d-none d-xl-table-cell">İşletim Sistemi</th>
                            <th class="d-none d-lg-table-cell">Kaynak</th>
                            <th class="d-none d-xxl-table-cell">Session</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($visits as $visit)
                            @php
                                $deviceIcons = [
                                    'desktop' => 'bi-display',
                                    'mobile'  => 'bi-phone',
                                    'tablet'  => 'bi-tablet',
                                    'bot'     => 'bi-robot',
                                    'other'   => 'bi-question-circle',
                                ];
                                $deviceColors = [
                                    'desktop' => 'text-neon-blue',
                                    'mobile'  => 'text-neon-green',
                                    'tablet'  => 'text-neon-purple',
                                    'bot'     => 'text-neon-orange',
                                    'other'   => 'text-clr-secondary',
                                ];
                                $deviceLabels = [
                                    'desktop' => 'Masaüstü',
                                    'mobile'  => 'Mobil',
                                    'tablet'  => 'Tablet',
                                    'bot'     => 'Bot',
                                    'other'   => 'Diğer',
                                ];
                                $icon = $deviceIcons[$visit->device_type] ?? 'bi-question-circle';
                                $color = $deviceColors[$visit->device_type] ?? 'text-clr-secondary';
                                $deviceLabel = $deviceLabels[$visit->device_type] ?? 'Diğer';
                            @endphp
                            <tr>
                                {{-- Tarih --}}
                                <td class="text-nowrap">
                                    <span class="fw-medium">{{ $visit->viewed_at->format('d.m.Y') }}</span>
                                    <small class="d-block text-clr-muted">{{ $visit->viewed_at->format('H:i:s') }}</small>
                                </td>

                                {{-- Sayfa --}}
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ $visit->url }}" target="_blank" class="anl-visit-url" title="{{ $visit->url }}">
                                            {{ Str::limit($visit->url_path, 40) }}
                                        </a>
                                        @if($visit->is_bot)
                                            <span class="status-badge pending">{{ $visit->bot_name ?? 'bot' }}</span>
                                        @endif
                                    </div>
                                </td>

                                {{-- IP --}}
                                <td class="d-none d-lg-table-cell">
                                    <code class="anl-visit-ip">{{ $visit->ip_address }}</code>
                                    @if($visit->ip_masked)
                                        <i class="bi bi-shield-check text-neon-green ms-1" title="KVKK maskelenmiş"></i>
                                    @endif
                                </td>

                                {{-- Cihaz --}}
                                <td>
                                    <span class="anl-visit-device {{ $color }}">
                                        <i class="bi {{ $icon }}"></i>
                                        <span class="d-none d-sm-inline">{{ $deviceLabel }}</span>
                                    </span>
                                </td>

                                {{-- Tarayıcı --}}
                                <td class="d-none d-md-table-cell">
                                    @if($visit->browser)
                                        <span class="text-clr-primary">{{ $visit->browser }}</span>
                                        <small class="text-clr-muted">{{ $visit->browser_version }}</small>
                                    @else
                                        <span class="text-clr-muted">—</span>
                                    @endif
                                </td>

                                {{-- OS --}}
                                <td class="d-none d-xl-table-cell">
                                    <span class="text-clr-secondary">{{ $visit->os ?? '—' }}</span>
                                </td>

                                {{-- Kaynak --}}
                                <td class="d-none d-lg-table-cell">
                                    @if($visit->referrer_domain)
                                        <div class="d-flex align-items-center gap-1">
                                            <i class="bi bi-box-arrow-in-right text-clr-muted"></i>
                                            <span class="text-clr-secondary">{{ $visit->referrer_domain }}</span>
                                        </div>
                                    @else
                                        <span class="text-clr-muted">direct</span>
                                    @endif
                                </td>

                                {{-- Session --}}
                                <td class="d-none d-xxl-table-cell">
                                    <code class="anl-visit-session">{{ Str::limit($visit->session_id, 10, '') }}</code>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center gap-2">
                                        <i class="bi bi-inbox anl-visit-empty-icon"></i>
                                        <p class="text-clr-secondary mb-0">Kayıt bulunamadı</p>
                                        @if(!empty(array_filter($filters)))
                                            <a href="{{ route('admin.analytics.visits') }}" class="btn-glass btn-sm mt-2">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i> Filtreleri Temizle
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('partials.admin.pagination', ['paginator' => $visits, 'itemLabel' => 'ziyaret'])

@endsection
