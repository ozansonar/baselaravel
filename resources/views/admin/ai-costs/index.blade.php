@extends('layouts.admin')

@section('title', 'AI Maliyet Raporu')
@section('page_title', 'AI Maliyet Raporu')
@section('page_description', 'AI görsel + text üretim maliyetlerinin aylık raporu, bütçe takibi ve en pahalı çağrılar.')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">AI Maliyet</li>
        </ol>
    </nav>

    {{-- Page Header + Ay seçici --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4">
        <div>
            <h1 class="page-title">AI Maliyet · {{ $monthLabel }}</h1>
            <p class="page-subtitle">Görsel + text üretim maliyeti aylık özet ve detaylı dağılım.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.ai-costs.index', ['month' => $prevMonth]) }}" class="btn-glass btn-glass-sm" title="Önceki ay">
                <i class="bi bi-chevron-left"></i>
            </a>
            <span class="btn-glass btn-glass-sm" style="cursor:default;">{{ $monthLabel }}</span>
            <a href="{{ route('admin.ai-costs.index', ['month' => $nextMonth]) }}" class="btn-glass btn-glass-sm" title="Sonraki ay">
                <i class="bi bi-chevron-right"></i>
            </a>
            <a href="{{ route('admin.settings.index') }}#ai" class="btn-glass btn-glass-sm" title="Bütçe ayarları">
                <i class="bi bi-gear me-1"></i> Ayarlar
            </a>
        </div>
    </div>

    {{-- Bütçe banner --}}
    <div class="card-dark mb-4 ai-cost-widget ai-cost-widget--{{ $budgetStatus['state'] }}">
        <div class="card-body-custom">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <strong>
                    <i class="bi bi-cash-coin text-success me-1"></i>
                    Bütçe Durumu:
                    <span class="ms-1">${{ number_format($budgetStatus['used_image'], 2) }} / ${{ number_format($budgetStatus['limit'], 2) }}</span>
                </strong>
                <span class="badge bg-{{ $budgetStatus['state'] === 'exceeded' ? 'danger' : ($budgetStatus['state'] === 'warning' ? 'warning' : 'success') }}-subtle text-{{ $budgetStatus['state'] === 'exceeded' ? 'danger' : ($budgetStatus['state'] === 'warning' ? 'warning' : 'success') }}-emphasis">
                    {{ $budgetStatus['percent'] }}%
                </span>
            </div>
            <div class="ai-cost-progress">
                <div class="ai-cost-progress-bar ai-cost-progress-bar--{{ $budgetStatus['state'] }}" style="width: {{ min(100, $budgetStatus['percent']) }}%"></div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2 small">
                <span class="text-muted">
                    Kalan: <strong>${{ number_format($budgetStatus['remaining'], 2) }}</strong>
                    @if($budgetStatus['used_text'] > 0)
                        · Text gen (bütçe dışı): ${{ number_format($budgetStatus['used_text'], 2) }}
                    @endif
                </span>
                @if($budgetStatus['exceeded'])
                    <span class="text-danger">
                        <i class="bi bi-exclamation-octagon-fill me-1"></i>
                        Bütçe aşıldı! @if($budgetStatus['block_enabled'])AI görsel üretimi durduruldu.@endif
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- 4 Stats Card --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="ai-cost-stat-card">
                <span class="ai-cost-stat-card__label">{{ $monthLabel }}</span>
                <span class="ai-cost-stat-card__value">${{ number_format($monthlyTotal['total'], 2) }}</span>
                <span class="ai-cost-stat-card__sub">
                    {{ $monthlyTotal['count_image'] }} görsel · {{ $monthlyTotal['count_text'] }} text
                </span>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="ai-cost-stat-card">
                <span class="ai-cost-stat-card__label">Sadece Görsel</span>
                <span class="ai-cost-stat-card__value">${{ number_format($monthlyTotal['image'], 2) }}</span>
                <span class="ai-cost-stat-card__sub">Bütçeye dahil</span>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="ai-cost-stat-card">
                <span class="ai-cost-stat-card__label">Sadece Text</span>
                <span class="ai-cost-stat-card__value">${{ number_format($monthlyTotal['text'], 2) }}</span>
                <span class="ai-cost-stat-card__sub">Rapor amaçlı</span>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="ai-cost-stat-card">
                <span class="ai-cost-stat-card__label">Yıllık Toplam</span>
                <span class="ai-cost-stat-card__value">${{ number_format($yearlyTotal, 2) }}</span>
                <span class="ai-cost-stat-card__sub">{{ count($yearlyMonths) }} ay verisi</span>
            </div>
        </div>
    </div>

    {{-- Charts --}}
    <div class="row g-3 mb-4">
        {{-- Günlük cost line chart --}}
        <div class="col-lg-7">
            <div class="ai-cost-chart-wrap">
                <h6 class="mb-3"><i class="bi bi-graph-up me-1"></i> Günlük Maliyet ({{ $monthLabel }})</h6>
                <canvas id="aiCostDailyChart"></canvas>
            </div>
        </div>

        {{-- Provider pie chart --}}
        <div class="col-lg-5">
            <div class="ai-cost-chart-wrap">
                <h6 class="mb-3"><i class="bi bi-pie-chart me-1"></i> Provider Dağılımı</h6>
                <canvas id="aiCostProviderChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        {{-- Source breakdown bar --}}
        <div class="col-lg-6">
            <div class="ai-cost-chart-wrap">
                <h6 class="mb-3"><i class="bi bi-bar-chart me-1"></i> Kaynak Bazında</h6>
                <canvas id="aiCostSourceChart"></canvas>
            </div>
        </div>

        {{-- Yıllık toplam bar --}}
        <div class="col-lg-6">
            <div class="ai-cost-chart-wrap">
                <h6 class="mb-3"><i class="bi bi-calendar-range me-1"></i> Yıllık Trend ({{ now()->year }})</h6>
                <canvas id="aiCostYearlyChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Top 10 en pahalı çağrı --}}
    @if(! empty($topExpensive))
    <div class="card-dark mb-4">
        <div class="card-header-custom">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-orange"><i class="bi bi-fire"></i></div>
                <div>
                    <h6 class="mb-0">Top 10 En Pahalı Çağrı ({{ $monthLabel }})</h6>
                    <small class="text-muted">Anomaly tespiti — beklenmedik yüksek maliyet kontrolü</small>
                </div>
            </div>
        </div>
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tip</th>
                            <th>Model</th>
                            <th class="text-end">Maliyet</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topExpensive as $idx => $row)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>
                                    <span class="usr-status-badge {{ $row['type'] !== 'text' ? 'active' : '' }}">
                                        @if($row['type'] === 'image')
                                            <i class="bi bi-image-fill me-1"></i> Görsel
                                        @elseif($row['type'] === 'vertex')
                                            <i class="bi bi-gpu-card me-1"></i> Vertex
                                        @else
                                            <i class="bi bi-chat-text me-1"></i> Text
                                        @endif
                                    </span>
                                </td>
                                <td><code>{{ $row['model'] ?: '—' }}</code></td>
                                <td class="text-end">
                                    <strong>${{ number_format($row['cost'], 4) }}</strong>
                                </td>
                                <td><small>{{ $row['created_at'] }}</small></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- JS data taşıyıcısı (Chart.js inputları) --}}
    @php
        $aiCostChartData = [
            'daily'    => $dailyBreakdown,
            'provider' => $providerBreakdown,
            'source'   => $sourceBreakdown,
            'yearly'   => $yearlyMonths,
        ];
    @endphp
    <script type="application/json" id="aiCostData">@json($aiCostChartData)</script>

@endsection

@push('scripts')
<script src="{{ asset('assets/vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
(function () {
    'use strict';
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js yüklenemedi.');
        return;
    }

    var dataEl = document.getElementById('aiCostData');
    if (! dataEl) return;
    var d;
    try { d = JSON.parse(dataEl.textContent); } catch (e) { return; }

    // Tema: dark
    Chart.defaults.color = 'rgba(255, 255, 255, 0.7)';
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.08)';
    Chart.defaults.font.family = 'inherit';

    // ─── 1) Günlük cost line chart ───
    var dailyCanvas = document.getElementById('aiCostDailyChart');
    if (dailyCanvas && d.daily) {
        var labels = d.daily.map(function (x) { return x.date.slice(8) + '.' + x.date.slice(5, 7); });
        var imageData = d.daily.map(function (x) { return parseFloat(x.image); });
        var textData = d.daily.map(function (x) { return parseFloat(x.text); });

        new Chart(dailyCanvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Görsel ($)',
                        data: imageData,
                        borderColor: '#14b8a6',
                        backgroundColor: 'rgba(20, 184, 166, 0.15)',
                        fill: true,
                        tension: 0.3,
                    },
                    {
                        label: 'Text ($)',
                        data: textData,
                        borderColor: '#a78bfa',
                        backgroundColor: 'rgba(167, 139, 250, 0.1)',
                        fill: true,
                        tension: 0.3,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) { return ctx.dataset.label + ': $' + ctx.parsed.y.toFixed(4); },
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: function (v) { return '$' + v.toFixed(2); } },
                    },
                },
            },
        });
    }

    // ─── 2) Provider pie chart ───
    var providerCanvas = document.getElementById('aiCostProviderChart');
    if (providerCanvas && d.provider) {
        var pLabels = Object.keys(d.provider);
        var pValues = pLabels.map(function (k) { return parseFloat(d.provider[k].cost); });
        var pColors = ['#14b8a6', '#a78bfa', '#f59e0b', '#ef4444', '#3b82f6', '#10b981'];

        new Chart(providerCanvas, {
            type: 'doughnut',
            data: {
                labels: pLabels,
                datasets: [{
                    data: pValues,
                    backgroundColor: pColors.slice(0, pLabels.length),
                    borderColor: 'rgba(0, 0, 0, 0.3)',
                    borderWidth: 1,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var key = ctx.label;
                                var info = d.provider[key];
                                return ctx.label + ': $' + ctx.parsed.toFixed(4) + ' (' + info.count + ' çağrı)';
                            },
                        },
                    },
                },
            },
        });
    }

    // ─── 3) Source bar chart ───
    var sourceCanvas = document.getElementById('aiCostSourceChart');
    if (sourceCanvas && d.source) {
        var sLabels = Object.keys(d.source);
        var sValues = sLabels.map(function (k) { return parseFloat(d.source[k].cost); });

        new Chart(sourceCanvas, {
            type: 'bar',
            data: {
                labels: sLabels,
                datasets: [{
                    label: 'Maliyet ($)',
                    data: sValues,
                    backgroundColor: 'rgba(20, 184, 166, 0.6)',
                    borderColor: '#14b8a6',
                    borderWidth: 1,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var key = ctx.label;
                                var info = d.source[key];
                                return '$' + ctx.parsed.x.toFixed(4) + ' (' + info.count + ' çağrı)';
                            },
                        },
                    },
                },
                scales: {
                    x: { ticks: { callback: function (v) { return '$' + v.toFixed(2); } } },
                },
            },
        });
    }

    // ─── 4) Yıllık trend chart ───
    var yearlyCanvas = document.getElementById('aiCostYearlyChart');
    if (yearlyCanvas && d.yearly) {
        new Chart(yearlyCanvas, {
            type: 'bar',
            data: {
                labels: d.yearly.map(function (x) { return x.label; }),
                datasets: [
                    {
                        label: 'Görsel',
                        data: d.yearly.map(function (x) { return parseFloat(x.image); }),
                        backgroundColor: 'rgba(20, 184, 166, 0.7)',
                    },
                    {
                        label: 'Text',
                        data: d.yearly.map(function (x) { return parseFloat(x.text); }),
                        backgroundColor: 'rgba(167, 139, 250, 0.7)',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    x: { stacked: true },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: { callback: function (v) { return '$' + v.toFixed(2); } },
                    },
                },
            },
        });
    }
})();
</script>
@endpush
