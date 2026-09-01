@extends('layouts.admin')

@section('title', 'Analitik')
@section('page_title', 'Ziyaretçi Analitiği')
@section('page_description', 'Sayfa görüntüleme, ziyaretçi, cihaz ve kaynak analizleri')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Analitik</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-bar-chart-line-fill text-teal me-2"></i>Ziyaretçi Analitiği</h1>
            <p class="page-subtitle">Sayfa görüntüleme, tekil ziyaretçi, cihaz ve trafik kaynak dağılımı.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.analytics.live') }}" class="btn-teal">
                <i class="bi bi-broadcast"></i> Canlı Ziyaretçiler
            </a>
            <a href="{{ route('admin.analytics.visits', request()->query()) }}" class="btn-glass">
                <i class="bi bi-list-ul"></i> Tüm Ziyaretler
            </a>
            <a href="{{ route('admin.analytics.index', array_merge(request()->query(), ['refresh' => 1])) }}" class="btn-glass" title="Cache temizle ve yenile">
                <i class="bi bi-arrow-clockwise"></i> Yenile
            </a>
        </div>
    </div>

    {{-- ===== ANALYTICS TOOLBAR: date range + bot toggle ===== --}}
    <div class="analytics-toolbar mb-4" data-aos="fade-up">
        <div class="analytics-toolbar-inner">
            <div class="analytics-range-group" role="group" aria-label="Tarih aralığı">
                @php
                    $ranges = [
                        'today' => 'Bugün',
                        '7d'    => 'Son 7 Gün',
                        '30d'   => 'Son 30 Gün',
                        '90d'   => 'Son 90 Gün',
                    ];
                @endphp
                @foreach($ranges as $val => $label)
                    <a href="{{ route('admin.analytics.index', array_merge(request()->query(), ['range' => $val, 'from' => null, 'to' => null])) }}"
                       class="analytics-range-btn {{ $range === $val ? 'active' : '' }}">{{ $label }}</a>
                @endforeach
                <button type="button" class="analytics-range-btn {{ $range === 'custom' ? 'active' : '' }}" id="customRangeBtn">
                    <i class="bi bi-calendar-event me-1"></i>Özel
                </button>
            </div>

            <div class="analytics-toolbar-divider d-none d-lg-block"></div>

            <div id="customDateWrap" class="d-flex gap-2 align-items-center flex-wrap {{ $range === 'custom' ? '' : 'd-none' }}">
                <input type="date" class="form-control form-control-sm form-control-theme analytics-date-input"
                       id="customFrom" value="{{ request('from', $from->toDateString()) }}" data-fv-ignore>
                <span class="text-clr-secondary">—</span>
                <input type="date" class="form-control form-control-sm form-control-theme analytics-date-input"
                       id="customTo" value="{{ request('to', $to->toDateString()) }}" data-fv-ignore>
                <button class="btn-teal btn-sm" id="applyCustomRange">Uygula</button>
            </div>

            <div class="analytics-toolbar-right ms-auto d-flex align-items-center gap-3 flex-wrap">
                <span class="analytics-period-badge">
                    <i class="bi bi-calendar3 me-1"></i>
                    {{ $from->format('d.m.Y') }} — {{ $to->format('d.m.Y') }}
                </span>
                <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" id="includeBotsToggle" {{ $includeBots ? 'checked' : '' }} data-fv-ignore>
                    <label class="form-check-label text-clr-secondary" for="includeBotsToggle">Botları dahil et</label>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== NO-DATA HELPER ===== --}}
    @if($stats['total_views'] === 0 && $totalRecords > 0)
        <div class="alert alert-warning d-flex align-items-start gap-3 border-0 mb-4" data-aos="fade-up">
            <i class="bi bi-info-circle-fill fs-4"></i>
            <div>
                <strong>Bu tarih aralığında kayıt yok</strong> — ama veritabanında toplam <strong>{{ $totalRecords }}</strong> ziyaret kaydı var.
                Daha geniş bir tarih aralığı seç veya "Botları dahil et" seçeneğini aç. Ziyaretlerinin tamamı bot olarak işaretlenmiş olabilir.
            </div>
        </div>
    @elseif($totalRecords === 0)
        <div class="alert alert-info d-flex align-items-start gap-3 border-0 mb-4" data-aos="fade-up">
            <i class="bi bi-info-circle-fill fs-4"></i>
            <div>
                <strong>Henüz hiç ziyaret kaydedilmedi.</strong> Siteyi ziyaret ettiğinde tracker 3 saniye sonra istek atar.
                Admin kullanıcılar (sen) izleme listesinde değilsin — gizli sekmeden test et.
            </div>
        </div>
    @endif

    {{-- ===== STAT CARDS ===== --}}
    <div class="row g-3 g-xl-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="analytics-stat-card">
                <div class="analytics-stat-icon bg-icon-teal"><i class="bi bi-eye-fill"></i></div>
                <div class="analytics-stat-body">
                    <span class="analytics-stat-label">Toplam Görüntülenme</span>
                    <h3 class="analytics-stat-value" data-count="{{ $stats['total_views'] }}">{{ number_format($stats['total_views']) }}</h3>
                    <small class="analytics-stat-foot">
                        <i class="bi bi-calendar3"></i>
                        {{ (int) $from->diffInDays($to) + 1 }} gün aralığı
                    </small>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="analytics-stat-card">
                <div class="analytics-stat-icon bg-icon-blue"><i class="bi bi-people-fill"></i></div>
                <div class="analytics-stat-body">
                    <span class="analytics-stat-label">Tekil Ziyaretçi</span>
                    <h3 class="analytics-stat-value" data-count="{{ $stats['unique_visitors'] }}">{{ number_format($stats['unique_visitors']) }}</h3>
                    <small class="analytics-stat-foot"><i class="bi bi-fingerprint"></i> Session bazlı</small>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="analytics-stat-card">
                <div class="analytics-stat-icon bg-icon-orange"><i class="bi bi-robot"></i></div>
                <div class="analytics-stat-body">
                    <span class="analytics-stat-label">Bot Ziyareti</span>
                    <h3 class="analytics-stat-value" data-count="{{ $stats['bot_views'] }}">{{ number_format($stats['bot_views']) }}</h3>
                    <small class="analytics-stat-foot"><i class="bi bi-shield-check"></i> Search engines & crawlers</small>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="analytics-stat-card">
                <div class="analytics-stat-icon bg-icon-purple"><i class="bi bi-graph-up"></i></div>
                <div class="analytics-stat-body">
                    <span class="analytics-stat-label">Günlük Ortalama</span>
                    <h3 class="analytics-stat-value" data-count="{{ $stats['avg_daily_views'] }}">{{ number_format($stats['avg_daily_views']) }}</h3>
                    <small class="analytics-stat-foot"><i class="bi bi-speedometer2"></i> Görüntülenme / gün</small>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== DAILY LINE CHART ===== --}}
    <div class="analytics-panel mb-4" data-aos="fade-up">
        <div class="analytics-panel-header">
            <div>
                <h5 class="analytics-panel-title"><i class="bi bi-graph-up-arrow text-teal me-2"></i>Günlük Ziyaret Trendi</h5>
                <small class="text-clr-muted">Seçilen tarih aralığındaki günlük görüntülenme sayısı</small>
            </div>
            <span class="analytics-chip">
                <i class="bi bi-calendar-range me-1"></i>
                {{ $from->format('d.m.Y') }} — {{ $to->format('d.m.Y') }}
            </span>
        </div>
        <div class="analytics-panel-body">
            @if(empty(array_filter($dailyChart['data'] ?? [])))
                <div class="analytics-empty">
                    <i class="bi bi-activity"></i>
                    <p>Bu aralıkta kayıt yok</p>
                </div>
            @else
                <div class="analytics-chart-wrap">
                    <canvas id="dailyViewsChart"></canvas>
                </div>
            @endif
        </div>
    </div>

    {{-- ===== TOP PAGES + DEVICE ===== --}}
    <div class="row g-3 g-xl-4 mb-4">
        <div class="col-xl-8" data-aos="fade-up">
            <div class="analytics-panel h-100">
                <div class="analytics-panel-header">
                    <div>
                        <h5 class="analytics-panel-title"><i class="bi bi-trophy-fill text-warning me-2"></i>En Çok Ziyaret Edilen Sayfalar</h5>
                        <small class="text-clr-muted">{{ count($topPages) }} sayfa · en çok görüntülenenden başlayarak</small>
                    </div>
                    @if(! empty($topPages))
                        <div class="anl-tabletools">
                            <div class="anl-tabletools__search">
                                <i class="bi bi-search"></i>
                                <label class="visually-hidden" for="topPagesSearch">Sayfalarda ara</label>
                                <input type="text" id="topPagesSearch" class="stg-input stg-input--sm"
                                       placeholder="Sayfa yolunda ara..." autocomplete="off"
                                       data-validation-engine="validate[maxSize[191]]">
                            </div>
                            <select class="cl-filter-select" id="topPagesPerPage" aria-label="Sayfa başına satır" data-fv-ignore>
                                @foreach([10, 25, 50] as $adet)
                                    <option value="{{ $adet }}">{{ $adet }} satır</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
                <div class="analytics-panel-body">
                    @if(empty($topPages))
                        <div class="analytics-empty">
                            <i class="bi bi-trophy"></i>
                            <p>Henüz sayfa ziyareti yok</p>
                        </div>
                    @else
                        @php $enYuksek = max(array_column($topPages, 'count')) ?: 1; @endphp
                        <div class="analytics-top-pages" id="topPagesList" data-page-size="10">
                            @foreach($topPages as $idx => $page)
                                <div class="analytics-top-row anl-row"
                                     data-search="{{ mb_strtolower($page['path']) }}">
                                    <div class="analytics-top-rank">#{{ $idx + 1 }}</div>
                                    <div class="analytics-top-main">
                                        <div class="analytics-top-path">
                                            <i class="bi bi-link-45deg"></i>
                                            <span>{{ $page['path'] }}</span>
                                        </div>
                                        <div class="analytics-top-bar-wrap">
                                            {{-- Oran veriden geliyor, biçim CSS'te: genişlik değişkenle veriliyor. --}}
                                            <div class="analytics-top-bar" style="--anl-top-bar: {{ (int) round(($page['count'] / $enYuksek) * 100) }}%"></div>
                                        </div>
                                    </div>
                                    <div class="analytics-top-count">{{ number_format($page['count']) }}</div>
                                </div>
                            @endforeach
                        </div>
                        <div class="anl-pager" id="topPagesPager" data-empty-text="Bu aramayla eşleşen sayfa yok."></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-4" data-aos="fade-up" data-aos-delay="100">
            <div class="analytics-panel h-100">
                <div class="analytics-panel-header">
                    <div>
                        <h5 class="analytics-panel-title"><i class="bi bi-phone-fill text-info me-2"></i>Cihaz Dağılımı</h5>
                        <small class="text-clr-muted">Ziyaretçi cihaz türleri</small>
                    </div>
                </div>
                <div class="analytics-panel-body">
                    @if(empty($deviceBreakdown))
                        <div class="analytics-empty">
                            <i class="bi bi-phone"></i>
                            <p>Veri yok</p>
                        </div>
                    @else
                        <div class="analytics-chart-wrap analytics-chart-md">
                            <canvas id="deviceChart"></canvas>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ===== BROWSER + REFERRER + BOT ===== --}}
    <div class="row g-3 g-xl-4 mb-4">
        <div class="col-xl-4" data-aos="fade-up">
            <div class="analytics-panel h-100">
                <div class="analytics-panel-header">
                    <div>
                        <h5 class="analytics-panel-title"><i class="bi bi-browser-chrome text-primary me-2"></i>Tarayıcı</h5>
                        <small class="text-clr-muted">Kullanılan tarayıcılar</small>
                    </div>
                </div>
                <div class="analytics-panel-body">
                    @if(empty($browserBreakdown))
                        <div class="analytics-empty">
                            <i class="bi bi-browser-chrome"></i>
                            <p>Veri yok</p>
                        </div>
                    @else
                        <div class="analytics-chart-wrap analytics-chart-md">
                            <canvas id="browserChart"></canvas>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-4" data-aos="fade-up" data-aos-delay="100">
            <div class="analytics-panel h-100">
                <div class="analytics-panel-header">
                    <div>
                        <h5 class="analytics-panel-title"><i class="bi bi-link-45deg text-success me-2"></i>Trafik Kaynakları</h5>
                        <small class="text-clr-muted">Referrer domain</small>
                    </div>
                </div>
                <div class="analytics-panel-body">
                    @if(empty($referrers))
                        <div class="analytics-empty">
                            <i class="bi bi-globe"></i>
                            <p>Veri yok</p>
                        </div>
                    @else
                        <div class="analytics-chart-wrap analytics-chart-md">
                            <canvas id="referrerChart"></canvas>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-4" data-aos="fade-up" data-aos-delay="200">
            <div class="analytics-panel h-100">
                <div class="analytics-panel-header">
                    <div>
                        <h5 class="analytics-panel-title"><i class="bi bi-robot text-warning me-2"></i>Bot Aktivitesi</h5>
                        <small class="text-clr-muted">Arama motorları ve crawler'lar</small>
                    </div>
                </div>
                <div class="analytics-panel-body">
                    @if(empty($botActivity))
                        <div class="analytics-empty">
                            <i class="bi bi-robot"></i>
                            <p>Bot ziyareti kaydı yok</p>
                        </div>
                    @else
                        <div class="analytics-chart-wrap analytics-chart-md">
                            <canvas id="botChart"></canvas>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ===== RECENT VISITS TABLE ===== --}}
    <div class="analytics-panel" data-aos="fade-up">
        <div class="analytics-panel-header">
            <div>
                <h5 class="analytics-panel-title"><i class="bi bi-clock-history text-teal me-2"></i>Son Ziyaretler</h5>
                <small class="text-clr-muted">
                    Son {{ number_format($recentVisits->count()) }} kayıt ({{ $includeBots ? 'botlar dahil' : 'sadece insan' }})
                </small>
            </div>
            <div class="anl-tabletools">
                @if($recentVisits->isNotEmpty())
                    <div class="anl-tabletools__search">
                        <i class="bi bi-search"></i>
                        <label class="visually-hidden" for="recentSearch">Ziyaretlerde ara</label>
                        <input type="text" id="recentSearch" class="stg-input stg-input--sm"
                               placeholder="Sayfa, IP, tarayıcı veya kaynak ara..." autocomplete="off"
                               data-validation-engine="validate[maxSize[191]]">
                    </div>
                    <select class="cl-filter-select" id="recentDevice" aria-label="Cihaz türü" data-fv-ignore>
                        <option value="">Tüm cihazlar</option>
                        @foreach(['desktop' => 'Masaüstü', 'mobile' => 'Mobil', 'tablet' => 'Tablet', 'bot' => 'Bot', 'other' => 'Diğer'] as $tur => $etiket)
                            <option value="{{ $tur }}">{{ $etiket }}</option>
                        @endforeach
                    </select>
                    <select class="cl-filter-select" id="recentPerPage" aria-label="Sayfa başına satır" data-fv-ignore>
                        @foreach([10, 25, 50] as $adet)
                            <option value="{{ $adet }}">{{ $adet }} satır</option>
                        @endforeach
                    </select>
                @endif
                <a href="{{ route('admin.analytics.visits') }}" class="btn-glass btn-sm">
                    <i class="bi bi-arrow-right"></i> Tümünü Gör
                </a>
            </div>
        </div>
        <div class="analytics-panel-body p-0">
            <div class="table-responsive">
                <table class="table analytics-table mb-0">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Sayfa</th>
                            <th>IP</th>
                            <th>Cihaz</th>
                            <th>Tarayıcı</th>
                            <th>Kaynak</th>
                        </tr>
                    </thead>
                    <tbody id="recentVisitsBody" data-page-size="10">
                        @forelse($recentVisits as $visit)
                        <tr class="anl-row"
                            data-device="{{ $visit->device_type }}"
                            data-search="{{ mb_strtolower($visit->url_path . ' ' . $visit->ip_address . ' ' . ($visit->browser ?? '') . ' ' . ($visit->referrer_domain ?? 'direct')) }}">
                            <td class="text-nowrap small text-clr-secondary">
                                {{ $visit->viewed_at->format('d.m H:i') }}
                                <small class="d-block text-clr-muted">{{ $visit->viewed_at->diffForHumans() }}</small>
                            </td>
                            <td class="text-truncate anl-cell-path">
                                <a href="{{ $visit->url }}" target="_blank" class="analytics-page-link" title="{{ $visit->url }}">
                                    {{ $visit->url_path }}
                                </a>
                                @if($visit->is_bot)
                                    <span class="badge bg-warning-subtle text-warning ms-1"><i class="bi bi-robot"></i> bot</span>
                                @endif
                            </td>
                            <td class="small text-clr-secondary font-monospace">{{ $visit->ip_address }}</td>
                            <td>
                                @php
                                    $deviceIcons = [
                                        'desktop' => 'bi-display',
                                        'mobile'  => 'bi-phone',
                                        'tablet'  => 'bi-tablet',
                                        'bot'     => 'bi-robot',
                                        'other'   => 'bi-question-circle',
                                    ];
                                    $icon = $deviceIcons[$visit->device_type] ?? 'bi-question-circle';
                                @endphp
                                <span class="analytics-device-badge device-{{ $visit->device_type }}">
                                    <i class="bi {{ $icon }}"></i> {{ ucfirst($visit->device_type) }}
                                </span>
                            </td>
                            <td class="small">{{ $visit->browser ?? '—' }}</td>
                            <td class="small text-clr-secondary">
                                @if($visit->referrer_domain)
                                    <i class="bi bi-box-arrow-in-right me-1"></i>{{ $visit->referrer_domain }}
                                @else
                                    <span class="text-clr-muted">direct</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-clr-secondary py-5">
                                <i class="bi bi-inbox anl-empty-icon"></i>
                                <p class="mt-2 mb-0">Henüz ziyaret kaydı yok</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="anl-pager anl-pager--table" id="recentPager" data-empty-text="Bu süzgeçle eşleşen ziyaret yok."></div>
        </div>
    </div>

@endsection

@push('scripts')
{{-- Kütüphane projede duruyor; CDN'den çekmek dış bir servise bağımlılık
     demekti: erişilemediğinde grafikler sessizce boş kalıyordu. --}}
<script src="{{ versioned_asset('assets/vendor/chartjs/chart.umd.min.js') }}"></script>
<script nonce="{{ csp_nonce() }}">
    window.analyticsConfig = {
        dailyChart: @json($dailyChart),
        topPages: @json($topPages),
        deviceBreakdown: @json($deviceBreakdown),
        browserBreakdown: @json($browserBreakdown),
        referrers: @json($referrers),
        botActivity: @json($botActivity)
    };
</script>
<script src="{{ versioned_asset('assets/admin/js/analytics.js') }}"></script>
@endpush
