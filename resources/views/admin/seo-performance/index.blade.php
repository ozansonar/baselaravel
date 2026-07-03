@extends('layouts.admin')

@section('title', 'SEO Performans')
@section('page_title', 'SEO Performans')
@section('page_description', 'Google Search Console verileri — arama sorguları, sayfa performansı, indeksleme durumu')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">SEO Performans</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-google me-2"></i>SEO Performans</h1>
            <p class="page-subtitle">
                Google Search Console verileri — kim arattı, hangi sayfan trafiği aldı, hangi içerik indexlendi.
                @if($lastFetch)
                    Son güncelleme: <strong>{{ $lastFetch->created_at->translatedFormat('d M Y H:i') }}</strong>
                @endif
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($configured)
                <form method="POST" action="{{ route('admin.seo-performance.fetch-now') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn-glass">
                        <i class="bi bi-arrow-clockwise me-1"></i> Şimdi Veri Çek
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Setup uyarısı (Service Account JSON yoksa) --}}
    @if(! $configured)
        <div class="card-dark mb-4 topic-pool-alert topic-pool-alert--warning">
            <div class="card-body-custom">
                <div class="d-flex align-items-start gap-3 flex-wrap">
                    <div class="topic-pool-alert__icon"><i class="bi bi-shield-exclamation"></i></div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1 topic-pool-alert__title">Google Search Console bağlanmamış</h6>
                        <p class="mb-2 small text-muted">
                            Veri çekmek için Service Account JSON dosyasını yüklemen gerek.
                            Tek tıkla yükleyebileceğin bir sayfa hazırladık — SCP veya cPanel'e gerek yok.
                        </p>
                        <a href="{{ route('admin.google-integration.index') }}" class="btn-teal mt-2 d-inline-flex">
                            <i class="bi bi-key-fill me-1"></i> Google Entegrasyonu Sayfasına Git
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="card-dark mb-4">
            <div class="card-body-custom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 small text-muted">
                    <i class="bi bi-shield-check text-success"></i>
                    <span>Service Account bağlandı: <code>{{ $serviceAccountEmail }}</code></span>
                </div>
                <button type="button" id="testConnectionBtn" class="usr-action-btn topic-convert-btn">
                    <i class="bi bi-wifi"></i> Bağlantıyı Test Et
                </button>
            </div>
        </div>
    @endif

    {{-- Flash --}}
    @if(session('success'))
        <div class="alert alert-success mb-4" data-aos="fade-up">
            <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-4" data-aos="fade-up">
            <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
        </div>
    @endif

    {{-- SECTION 1: STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-search"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Sorgu</span>
                    <h3 class="usr-stat-value" data-count="{{ $totalQueries }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-teal">
                    <i class="bi bi-file-earmark-text-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Sayfa Metriği</span>
                    <h3 class="usr-stat-value" data-count="{{ $totalPages }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">İndekslenmeyi Bekleyen</span>
                    <h3 class="usr-stat-value" data-count="{{ $pendingIndex }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Son Çekim</span>
                    <h3 class="usr-stat-value">
                        @if($lastFetch)
                            {{ $lastFetch->created_at->diffForHumans(['short' => true]) }}
                        @else
                            —
                        @endif
                    </h3>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 2: TABS --}}
    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        <a href="{{ route('admin.seo-performance.index', ['tab' => 'queries']) }}"
           class="cl-status-tab {{ $tab === 'queries' ? 'active' : '' }}">
            <i class="bi bi-search"></i>
            <span>Aramalar</span>
            <span class="cl-tab-count">{{ $totalQueries }}</span>
        </a>
        <a href="{{ route('admin.seo-performance.index', ['tab' => 'pages']) }}"
           class="cl-status-tab {{ $tab === 'pages' ? 'active' : '' }}">
            <i class="bi bi-file-earmark-text"></i>
            <span>Sayfalar</span>
            <span class="cl-tab-count">{{ $totalPages }}</span>
        </a>
        <a href="{{ route('admin.seo-performance.index', ['tab' => 'indexing']) }}"
           class="cl-status-tab {{ $tab === 'indexing' ? 'active' : '' }}">
            <i class="bi bi-arrow-up-right-square"></i>
            <span>İndeksleme</span>
            <span class="cl-tab-count">{{ $indexingRequests->count() }}</span>
        </a>
        <a href="{{ route('admin.seo-performance.index', ['tab' => 'logs']) }}"
           class="cl-status-tab {{ $tab === 'logs' ? 'active' : '' }}">
            <i class="bi bi-bug"></i>
            <span>API Logları</span>
            <span class="cl-tab-count">{{ $apiLogs->count() }}</span>
        </a>
    </div>

    {{-- TAB: QUERIES --}}
    @if($tab === 'queries')
        @include('admin.seo-performance.partials.queries', ['queries' => $queries, 'search' => $search])

    {{-- TAB: PAGES --}}
    @elseif($tab === 'pages')
        @include('admin.seo-performance.partials.pages', ['pages' => $pages, 'lowCtrPages' => $lowCtrPages, 'type' => $type, 'search' => $search])

    {{-- TAB: INDEXING --}}
    @elseif($tab === 'indexing')
        @include('admin.seo-performance.partials.indexing', ['indexingRequests' => $indexingRequests])

    {{-- TAB: LOGS --}}
    @elseif($tab === 'logs')
        @include('admin.seo-performance.partials.logs', ['apiLogs' => $apiLogs])
    @endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const testBtn = document.getElementById('testConnectionBtn');
    if (testBtn) {
        testBtn.addEventListener('click', async () => {
            testBtn.disabled = true;
            const origHtml = testBtn.innerHTML;
            testBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Test ediliyor...';

            try {
                const res = await fetch(@json(route('admin.seo-performance.test-connection')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': @json(csrf_token()),
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();

                if (window.AdminModal && typeof AdminModal.status === 'function') {
                    AdminModal.status({
                        title: data.success ? 'Bağlantı Başarılı' : 'Bağlantı Hatası',
                        message: data.message,
                        type: data.success ? 'success' : 'danger',
                    });
                }

                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (e) {
                if (window.AdminModal && typeof AdminModal.status === 'function') {
                    AdminModal.status({ title: 'Hata', message: 'İstek hatası: ' + e.message, type: 'danger' });
                }
            } finally {
                testBtn.disabled = false;
                testBtn.innerHTML = origHtml;
            }
        });
    }
});
</script>
@endpush
