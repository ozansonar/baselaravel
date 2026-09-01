@extends('layouts.admin')

@section('title', 'Rapor Merkezi')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Raporlar</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title">Rapor Merkezi</h1>
            <p class="page-subtitle">Trafik, içerik, kullanıcı ve gönderim raporlarını oluşturun, planlayın ve indirin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            {{-- Tarih aralığı: sunucu tarafında, sayfa yenilenerek. Tema'daki
                 JS süzgeci istemcide çalışıyordu; rapor verisi sunucuda
                 üretildiği için burada seçim adres satırına yazılıyor. --}}
            <form method="GET" action="{{ route('admin.reports.index') }}" id="rangeForm">
                <input type="hidden" name="type" value="{{ $type->value }}">
                <div class="rpr-date-range">
                    <i class="bi bi-calendar3"></i>
                    <select class="rpr-date-select" name="range" onchange="document.getElementById('rangeForm').submit()" data-fv-ignore>
                        @foreach($ranges as $key => $label)
                            {{-- (string) şart: '7', '30', '90' sayısal anahtarlar
                                 olduğu için PHP onları tamsayıya çeviriyor ve
                                 katı karşılaştırma hiçbir zaman tutmuyordu. --}}
                            <option value="{{ $key }}" @selected((string) $key === $range)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
            @can('manage-reports')
                <button class="btn-teal" onclick="openScheduleModal(null)">
                    <i class="bi bi-alarm"></i> Yeni Plan
                </button>
            @endcan
        </div>
    </div>

    {{-- ==================== SECTION 1: KPI CARDS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-eye"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Görüntülenme</span>
                    <h3 class="usr-stat-value" data-count="{{ $summary['views'] }}">0</h3>
                    <span class="usr-stat-change neutral"><i class="bi bi-calendar-event"></i> {{ $ranges[$range] ?? 'Seçili aralık' }}</span>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-file-earmark-text"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Üretilen İçerik</span>
                    <h3 class="usr-stat-value" data-count="{{ $summary['content'] }}">0</h3>
                    <span class="usr-stat-change neutral"><i class="bi bi-collection"></i> Yazı, sayfa ve galeri</span>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple"><i class="bi bi-person-plus"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Yeni Kullanıcı</span>
                    <h3 class="usr-stat-value" data-count="{{ $summary['users'] }}">0</h3>
                    <span class="usr-stat-change neutral"><i class="bi bi-people"></i> Kayıt olanlar</span>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-envelope"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">E-posta Gönderimi</span>
                    <h3 class="usr-stat-value" data-count="{{ $summary['mails'] }}">0</h3>
                    <span class="usr-stat-change neutral"><i class="bi bi-send"></i> Kayıtlı gönderim</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 2: QUICK REPORTS ==================== --}}
    <div class="d-flex align-items-center justify-content-between mb-3" data-aos="fade-up">
        <h2 class="rpr-section-title">Hızlı Rapor Oluştur</h2>
        <span class="rpr-section-sub">{{ count($types) }} rapor türü mevcut</span>
    </div>

    <div class="row g-4 mb-4">
        @foreach($types as $index => $reportType)
            <div class="col-xl-4 col-md-6" data-aos="fade-up" data-aos-delay="{{ $index * 80 }}">
                <div class="rpr-quick-card rpr-quick-{{ $reportType->color() }} @if($reportType === $type) rpr-quick-current @endif">
                    <div class="rpr-quick-header">
                        <div class="rpr-quick-icon"><i class="bi {{ $reportType->icon() }}"></i></div>
                        {{-- Biçim rozetleri gerçekten üretilebilen biçimleri
                             gösteriyor: kit Excel, CSV ve PDF yazıyor. --}}
                        <div class="rpr-quick-badges">
                            <span class="rpr-format-badge pdf">PDF</span>
                            <span class="rpr-format-badge excel">Excel</span>
                            <span class="rpr-format-badge csv">CSV</span>
                        </div>
                    </div>
                    <h4 class="rpr-quick-title">{{ $reportType->label() }}</h4>
                    <p class="rpr-quick-desc">{{ $reportType->description() }}</p>
                    <div class="rpr-quick-meta">
                        <span><i class="bi bi-calendar3 me-1"></i>{{ $ranges[$range] ?? '' }}</span>
                        @if($reportType === $type)
                            <span><i class="bi bi-list-ul me-1"></i>{{ count($report['rows']) }} satır</span>
                        @endif
                    </div>
                    <div class="rpr-quick-actions">
                        <a class="btn-teal rpr-generate-btn" href="{{ route('admin.reports.index', ['type' => $reportType->value, 'range' => $range]) }}">
                            <i class="bi bi-play-circle me-1"></i> Oluştur
                        </a>
                        @can('manage-reports')
                            <button type="button" class="btn-glass rpr-schedule-btn" onclick="openScheduleModal(null, '{{ $reportType->value }}')" title="Planla">
                                <i class="bi bi-alarm"></i>
                            </button>
                        @endcan
                        <button type="button" class="btn-glass rpr-preview-btn" onclick="openPreviewModal('{{ $reportType->value }}')" title="Önizle">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ==================== SECTION 3: CHART + CURRENT REPORT ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-8" data-aos="fade-up" data-aos-delay="0">
            <div class="card-dark h-100">
                <div class="card-body-custom">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                        <div>
                            <h5 class="rpr-chart-title">{{ $type->label() }}</h5>
                            <p class="rpr-chart-sub">{{ $from->format('d.m.Y') }} – {{ $to->format('d.m.Y') }}</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a class="btn-glass btn-sm" href="{{ route('admin.export', ['key' => 'reports', 'format' => 'excel']) }}?type={{ $type->value }}&range={{ $range }}&search={{ urlencode($search) }}">
                                <i class="bi bi-file-earmark-excel me-1"></i> Excel
                            </a>
                            <a class="btn-glass btn-sm" href="{{ route('admin.export', ['key' => 'reports', 'format' => 'csv']) }}?type={{ $type->value }}&range={{ $range }}&search={{ urlencode($search) }}">
                                <i class="bi bi-filetype-csv me-1"></i> CSV
                            </a>
                            <a class="btn-glass btn-sm" href="{{ route('admin.export', ['key' => 'reports', 'format' => 'pdf']) }}?type={{ $type->value }}&range={{ $range }}&search={{ urlencode($search) }}">
                                <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                            </a>
                        </div>
                    </div>
                    <div class="rpr-chart-wrap">
                        <canvas id="reportTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card-dark h-100">
                <div class="card-body-custom">
                    <h5 class="rpr-chart-title mb-1">Özet</h5>
                    <p class="rpr-chart-sub mb-4">{{ $type->label() }} için öne çıkan sayılar</p>

                    <div class="rpr-metric-list">
                        @foreach($report['metrics'] as $metric)
                            <div class="rpr-metric-item">
                                <span class="rpr-metric-label">
                                    {{ $metric['label'] }}
                                    @isset($metric['hint'])
                                        <small class="rpr-metric-hint">{{ $metric['hint'] }}</small>
                                    @endisset
                                </span>
                                <span class="rpr-metric-value">{{ $metric['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 4: REPORT TABLE ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="0">
        <div class="card-body-custom">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                <div>
                    <h5 class="rpr-chart-title">Rapor Satırları</h5>
                    <p class="rpr-chart-sub">{{ count($report['rows']) }} satır</p>
                </div>
                <form method="GET" action="{{ route('admin.reports.index') }}" class="cl-search rpr-table-search">
                    <input type="hidden" name="type" value="{{ $type->value }}">
                    <input type="hidden" name="range" value="{{ $range }}">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Rapor içinde ara..." data-fv-ignore>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover cl-table mb-0">
                    <thead>
                        <tr>
                            @foreach($report['columns'] as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(array_slice($report['rows'], 0, 100) as $row)
                            <tr>
                                @foreach($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ max(count($report['columns']), 1) }}" class="text-center py-4">
                                    <span class="usr-meta">Bu aralıkta kayıt yok.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(count($report['rows']) > 100)
                <p class="usr-meta mt-3 mb-0">
                    İlk 100 satır gösteriliyor; tamamı için Excel ya da PDF olarak indirin.
                </p>
            @endif
        </div>
    </div>

    {{-- ==================== SECTION 5: SCHEDULED REPORTS ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="0">
        <div class="card-body-custom">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                <div>
                    <h5 class="rpr-chart-title">Zamanlanan Raporlar</h5>
                    <p class="rpr-chart-sub">Otomatik çalışan ve planlanan raporlar</p>
                </div>
                @can('manage-reports')
                    <button class="btn-teal btn-sm" onclick="openScheduleModal(null)">
                        <i class="bi bi-plus-lg me-1"></i> Yeni Plan
                    </button>
                @endcan
            </div>

            <div class="table-responsive">
                <table class="table table-hover cl-table mb-0">
                    <thead>
                        <tr>
                            <th>Rapor</th>
                            <th class="d-none d-md-table-cell">Sıklık</th>
                            <th class="d-none d-lg-table-cell">Son Çalışma</th>
                            <th>Format</th>
                            <th>Durum</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schedules as $schedule)
                            @php
                                // @json() içinde köşeli parantez Blade
                                // ayrıştırıcısını bozuyor; veri burada
                                // hazırlanıp basılıyor.
                                $scheduleJson = json_encode([
                                    'id'         => $schedule->id,
                                    'type'       => $schedule->type->value,
                                    'frequency'  => $schedule->frequency->value,
                                    'range'      => $schedule->range,
                                    'format'     => $schedule->format,
                                    'is_active'  => $schedule->is_active,
                                    'recipients' => implode(', ', $schedule->recipients),
                                ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
                            @endphp
                            <tr>
                                <td>
                                    <div class="rpr-sched-name">
                                        <div class="rpr-sched-icon rpr-sched-icon-{{ $schedule->type->color() }}">
                                            <i class="bi {{ $schedule->type->icon() }}"></i>
                                        </div>
                                        <div>
                                            <span class="rpr-sched-title">{{ $schedule->type->label() }}</span>
                                            <span class="rpr-sched-sub">{{ implode(', ', $schedule->recipients) }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell"><span class="rpr-freq-badge">{{ $schedule->frequency->label() }}</span></td>
                                <td class="d-none d-lg-table-cell">
                                    <span class="usr-meta">
                                        {{ $schedule->last_run_at?->format('d.m.Y H:i') ?? 'Hiç çalışmadı' }}
                                    </span>
                                    @if($schedule->last_error)
                                        <span class="rpr-sched-error" title="{{ $schedule->last_error }}">
                                            <i class="bi bi-exclamation-triangle"></i> Son denemede hata
                                        </span>
                                    @endif
                                </td>
                                <td><span class="rpr-format-badge {{ $schedule->format }}">{{ strtoupper($schedule->format) }}</span></td>
                                <td>
                                    <span class="rpr-status-badge {{ $schedule->is_active ? 'active' : 'inactive' }}">
                                        {{ $schedule->is_active ? 'Aktif' : 'Pasif' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="usr-actions">
                                        @can('manage-reports')
                                            <button type="button" class="usr-action-btn" title="Düzenle"
                                                    onclick="openScheduleModal({!! $scheduleJson !!})">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="{{ route('admin.reports.schedules.run', $schedule) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="usr-action-btn" title="Şimdi çalıştır">
                                                    <i class="bi bi-play-fill"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="usr-action-btn danger" title="Sil"
                                                    onclick="openDeleteScheduleModal({{ $schedule->id }}, '{{ $schedule->type->label() }}')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <span class="usr-meta">Henüz zamanlanmış rapor yok.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 6: RECENT DOWNLOADS ==================== --}}
    <div class="card-dark" data-aos="fade-up" data-aos-delay="0">
        <div class="card-body-custom">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                <div>
                    <h5 class="rpr-chart-title">Son İndirilenler</h5>
                    <p class="rpr-chart-sub">Panelden dışa aktarılan son dosyalar</p>
                </div>
            </div>

            <div class="rpr-download-list">
                @forelse($downloads as $download)
                    <div class="rpr-download-item">
                        <div class="rpr-dl-icon {{ str_contains($download->label, 'PDF') ? 'rpr-dl-icon-pdf' : 'rpr-dl-icon-excel' }}">
                            <i class="bi {{ str_contains($download->label, 'PDF') ? 'bi-filetype-pdf' : 'bi-filetype-xlsx' }}"></i>
                        </div>
                        <div class="rpr-dl-info">
                            <span class="rpr-dl-name">{{ $download->label }}</span>
                            <div class="rpr-dl-meta">
                                <span><i class="bi bi-clock me-1"></i>{{ $download->created_at?->format('d.m.Y H:i') }}</span>
                                <span><i class="bi bi-person me-1"></i>{{ $download->user?->full_name ?? 'Sistem' }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="usr-meta mb-0">Henüz dışa aktarma yapılmamış.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ==================== MODALS ==================== --}}
    @can('manage-reports')
        <div class="modal fade" id="scheduleModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-custom">
                    <form method="POST" action="{{ route('admin.reports.schedules.store') }}" id="scheduleForm" data-validate novalidate>
                        @csrf
                        <input type="hidden" name="_method" value="POST" id="scheduleMethod">

                        <div class="modal-header">
                            <h5 class="modal-title prd-modal-title"><i class="bi bi-alarm me-2"></i><span id="scheduleModalTitle">Yeni Zamanlanmış Rapor</span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                        </div>

                        <div class="modal-body">
                            <div class="stg-field">
                                <label class="stg-label" for="schedType">Rapor Türü</label>
                                <select class="stg-select" name="type" id="schedType" data-validation-engine="validate[required]">
                                    @foreach($types as $reportType)
                                        <option value="{{ $reportType->value }}">{{ $reportType->label() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="stg-field">
                                <label class="stg-label" for="schedFrequency">Sıklık</label>
                                <select class="stg-select" name="frequency" id="schedFrequency" data-validation-engine="validate[required]">
                                    @foreach($frequencies as $frequency)
                                        <option value="{{ $frequency->value }}">{{ $frequency->label() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="stg-field">
                                <label class="stg-label" for="schedRange">Kapsanan Aralık</label>
                                <select class="stg-select" name="range" id="schedRange" data-validation-engine="validate[required]">
                                    @foreach($ranges as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="stg-field">
                                <label class="stg-label" for="schedFormat">Dosya Biçimi</label>
                                <select class="stg-select" name="format" id="schedFormat" data-validation-engine="validate[required]">
                                    <option value="excel">Excel</option>
                                    <option value="csv">CSV</option>
                                    <option value="pdf">PDF</option>
                                </select>
                            </div>

                            <div class="stg-field">
                                <label class="stg-label" for="schedRecipients">Alıcılar</label>
                                <textarea class="stg-textarea" name="recipients" id="schedRecipients" rows="2"
                                          placeholder="ornek@site.com, ikinci@site.com"
                                          data-validation-engine="validate[required,maxSize[500]]"></textarea>
                                <small class="stg-hint">Virgülle ayırın. En fazla 10 adres.</small>
                            </div>

                            <div class="stg-toggle-list">
                                <div class="stg-toggle-item">
                                    <div class="stg-toggle-info">
                                        <span>Aktif</span>
                                        <small>Kapatılan tanım cron'da atlanır</small>
                                    </div>
                                    <label class="stg-switch">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" id="schedActive" checked data-fv-ignore>
                                        <span class="stg-switch-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn-teal"><i class="bi bi-check-lg me-1"></i> Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteScheduleModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-custom">
                    <div class="modal-body text-center p-4">
                        <div class="delete-modal-icon"><i class="bi bi-trash"></i></div>
                        <h5 class="mt-3">Zamanlanmış rapor silinsin mi?</h5>
                        <p class="usr-meta" id="deleteScheduleName"></p>

                        <form method="POST" id="deleteScheduleForm" class="d-flex gap-2 justify-content-center mt-4">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn-danger-solid">Sil</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endcan

    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content modal-custom">
                <div class="modal-header">
                    <h5 class="modal-title prd-modal-title"><i class="bi bi-eye me-2"></i><span id="previewModalTitle">Rapor Önizleme</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <p class="rpr-chart-sub" id="previewRange"></p>
                    <div class="row g-3 mb-4" id="previewMetrics"></div>
                    <div class="table-responsive">
                        <table class="table cl-table mb-0" id="previewTable">
                            <thead id="previewTableHead"></thead>
                            <tbody id="previewTableBody"></tbody>
                        </table>
                    </div>
                    <p class="usr-meta mt-3 mb-0" id="previewTotal"></p>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    @php
        $chartData = [
            'labels' => $report['series']['labels'],
            'values' => $report['series']['values'],
            'label'  => $report['series']['label'],
        ];
    @endphp
    <script nonce="{{ csp_nonce() }}">
        window.reportChartData = {!! json_encode($chartData, JSON_UNESCAPED_UNICODE) !!};
        window.reportPreviewUrl = '{{ route('admin.reports.preview', ['type' => '__TYPE__']) }}';
        window.reportRange = '{{ $range }}';
        @can('manage-reports')
        window.reportScheduleStoreUrl = '{{ route('admin.reports.schedules.store') }}';
        window.reportScheduleUpdateUrl = '{{ route('admin.reports.schedules.update', ['schedule' => '__ID__']) }}';
        window.reportScheduleDeleteUrl = '{{ route('admin.reports.schedules.destroy', ['schedule' => '__ID__']) }}';
        @endcan
    </script>
    <script src="{{ versioned_asset('assets/vendor/chartjs/chart.umd.min.js') }}"></script>
    <script src="{{ versioned_asset('assets/admin/js/reports.js') }}"></script>
@endpush
