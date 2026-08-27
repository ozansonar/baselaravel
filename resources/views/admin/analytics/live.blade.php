@extends('layouts.admin')

@section('title', 'Canlı Ziyaretçiler')
@section('page_title', 'Canlı Ziyaretçiler')
@section('page_description', 'Sitede şu anda kim var, hangi sayfada')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.analytics.index') }}" class="breadcrumb-link">Analitik</a></li>
            <li class="breadcrumb-item active text-teal">Canlı</li>
        </ol>
    </nav>

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">
                <span class="live-dot" id="liveDot" aria-hidden="true"></span>
                Canlı Ziyaretçiler
            </h1>
            <p class="page-subtitle">
                Son <strong id="windowLabel">{{ $windowMinutes }}</strong> dakikada hareket eden ziyaretçiler ·
                sunucu saati <span id="serverTime">—</span>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <select class="cl-filter-select" id="windowSelect" aria-label="Zaman aralığı" data-fv-ignore>
                @foreach([1 => '1 dakika', 5 => '5 dakika', 15 => '15 dakika', 30 => '30 dakika', 60 => '1 saat'] as $value => $label)
                    <option value="{{ $value }}" {{ $windowMinutes === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <label class="stg-switch-inline d-flex align-items-center gap-2 mb-0">
                <input type="checkbox" id="includeBots" {{ $includeBots ? 'checked' : '' }} data-fv-ignore>
                <span class="text-clr-secondary small">Botları göster</span>
            </label>
            <button type="button" class="btn-glass" id="pauseBtn" title="Duraklat">
                <i class="bi bi-pause-fill"></i> <span id="pauseLabel">Duraklat</span>
            </button>
            <a href="{{ route('admin.analytics.visits') }}" class="btn-glass">
                <i class="bi bi-list-ul"></i> Tüm Kayıtlar
            </a>
        </div>
    </div>

    {{-- STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-broadcast"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Şu An Sitede</span>
                    <h3 class="usr-stat-value" id="onlineCount">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-file-earmark-text-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Açık Sayfa Sayısı</span>
                    <h3 class="usr-stat-value" id="pageCount">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple"><i class="bi bi-person-check-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Üye Girişli</span>
                    <h3 class="usr-stat-value" id="memberCount">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-phone-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Mobil</span>
                    <h3 class="usr-stat-value" id="mobileCount">0</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Active visitors --}}
        <div class="col-xl-8">
            <div class="card-dark mb-4" data-aos="fade-up">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h6><i class="bi bi-people-fill me-2 text-teal"></i>Aktif Ziyaretçiler</h6>
                    <span class="text-clr-secondary small">Her ziyaretçi için son görüntülediği sayfa</span>
                </div>
                <div class="card-body-custom p-0">
                    <div class="table-responsive">
                        <table class="cl-table">
                            <thead>
                                <tr>
                                    <th>Ziyaretçi</th>
                                    <th>Bulunduğu Sayfa</th>
                                    <th class="d-none d-lg-table-cell">Cihaz</th>
                                    <th class="d-none d-xl-table-cell">Kaynak</th>
                                    <th class="d-none d-md-table-cell">Süre</th>
                                    <th>Son Hareket</th>
                                </tr>
                            </thead>
                            <tbody id="visitorRows">
                                <tr><td colspan="6" class="text-center py-5 text-clr-secondary">Yükleniyor…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Side: active pages + feed --}}
        <div class="col-xl-4">
            <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="50">
                <div class="card-header-custom"><h6><i class="bi bi-fire me-2 text-teal"></i>Şu An Bakılan Sayfalar</h6></div>
                <div class="card-body-custom" id="activePages">
                    <p class="text-clr-secondary mb-0">Yükleniyor…</p>
                </div>
            </div>

            <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h6><i class="bi bi-activity me-2 text-teal"></i>Akış</h6>
                    <span class="text-clr-secondary small">Sayfa açıldıkça düşer</span>
                </div>
                <div class="card-body-custom analytics-feed" id="liveFeed">
                    <p class="text-clr-secondary mb-0">Yükleniyor…</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    /* Pulsing dot that shows polling is alive; freezes when paused. */
    .live-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--neon-green, #2ee6a8);
        margin-right: 6px;
        vertical-align: middle;
        animation: live-pulse 1.6s ease-in-out infinite;
    }
    .live-dot--paused { animation: none; opacity: .35; }

    @keyframes live-pulse {
        0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(46, 230, 168, .5); }
        50%      { opacity: .55; box-shadow: 0 0 0 6px rgba(46, 230, 168, 0); }
    }
    @media (prefers-reduced-motion: reduce) {
        .live-dot { animation: none; }
    }

    .analytics-feed { max-height: 420px; overflow-y: auto; }
    .analytics-feed__row {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        padding: 6px 0;
        border-bottom: 1px solid rgba(255, 255, 255, .06);
        font-size: .85rem;
    }
    .analytics-feed__row:last-child { border-bottom: 0; }
    .analytics-feed__row--new { animation: feed-in .6s ease-out; }
    @keyframes feed-in { from { background: rgba(46, 230, 168, .12); } to { background: transparent; } }

    .visitor-session { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; }
</style>
@endpush

@push('scripts')
<script>
    window.analyticsLive = {
        url: @js(route('admin.analytics.live.data')),
        window: {{ $windowMinutes }},
        includeBots: {{ $includeBots ? 'true' : 'false' }},
        intervalMs: 10000
    };
</script>
<script src="{{ versioned_asset('assets/admin/js/analytics-live.js') }}"></script>
@endpush
