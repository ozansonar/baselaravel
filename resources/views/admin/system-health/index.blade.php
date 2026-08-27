@extends('layouts.admin')

@section('title', 'Sistem Sağlık')
@section('page_title', 'Sistem Sağlık')
@section('page_description', 'Sistemin kritik parçalarının durumu tek ekranda')

@section('content')
    @php
        $summary = $health['summary'];
        $overall = $summary['overall'];
        $overallLabel = match ($overall) {
            'ok'       => 'Tüm sistem sağlıklı',
            'warning'  => 'Dikkat isteyen noktalar var',
            'critical' => 'Kritik sorun var',
            default    => 'Bilinmiyor',
        };
        $overallHint = match ($overall) {
            'ok'       => 'Bütün kontroller beklenen sonucu verdi.',
            'warning'  => 'Sistem çalışıyor ama aşağıdaki başlıklar yakında sorun çıkarabilir.',
            'critical' => 'Aşağıdaki kırmızı başlık şu anda işleyişi bozuyor; önce onunla ilgilenin.',
            default    => '',
        };
        $overallIcon = match ($overall) {
            'ok'       => 'bi-shield-check',
            'warning'  => 'bi-exclamation-triangle-fill',
            'critical' => 'bi-x-octagon-fill',
            default    => 'bi-question-circle',
        };
        $checkedAt = \Illuminate\Support\Carbon::parse($health['checked_at']);
        $checkNames = collect($health['checks'])->pluck('label')->implode(', ');
    @endphp

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Sistem Sağlık</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Sistem Sağlık</h1>
            <p class="page-subtitle">{{ $summary['total'] }} kontrol çalıştırıldı — {{ $checkNames }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.system-health.json') }}" class="btn-glass" target="_blank" rel="noopener"
               title="İzleme araçları için ham çıktı">
                <i class="bi bi-braces"></i> JSON çıktısı
            </a>
            <button type="button" class="btn-teal" id="shRefreshBtn" data-url="{{ route('admin.system-health.json') }}">
                <i class="bi bi-arrow-clockwise"></i>
                <span data-default>Yeniden Kontrol Et</span>
                <span data-loading class="d-none"><i class="bi bi-arrow-repeat bk-spin"></i> Kontrol ediliyor…</span>
            </button>
        </div>
    </div>

    {{-- ==================== SECTION 1: GENEL DURUM ==================== --}}
    <div class="sh-overall sh-overall--{{ $overall }} mb-4" data-aos="fade-up">
        <div class="sh-overall__icon"><i class="bi {{ $overallIcon }}"></i></div>

        <div class="sh-overall__body">
            <h2 class="sh-overall__title">{{ $overallLabel }}</h2>
            <p class="sh-overall__hint">{{ $overallHint }}</p>
            <span class="sh-overall__time">
                <i class="bi bi-clock me-1"></i>Son kontrol: {{ $checkedAt->translatedFormat('d F Y, H:i:s') }}
                ({{ $checkedAt->diffForHumans() }})
            </span>
        </div>

        <div class="sh-counters">
            <div class="sh-counter sh-counter--ok">
                <strong>{{ $summary['ok'] }}</strong>
                <small>Sağlıklı</small>
            </div>
            <div class="sh-counter sh-counter--warning">
                <strong>{{ $summary['warning'] }}</strong>
                <small>Uyarı</small>
            </div>
            <div class="sh-counter sh-counter--critical">
                <strong>{{ $summary['critical'] }}</strong>
                <small>Kritik</small>
            </div>
        </div>
    </div>

    {{-- Sayfanın nasıl çalıştığı --}}
    <div class="nt-info-note mb-4" data-aos="fade-up" data-aos-delay="50">
        <i class="bi bi-info-circle-fill"></i>
        <div>
            <strong>Kontroller bu sayfayı her açtığınızda yeniden çalışır.</strong>
            Sonuçlar saklanmaz, gördüğünüz durum o anın fotoğrafıdır. Sorunlu kontroller
            listenin başında; her kartın altında ne işe yaradığı, sorun hâlinde ne
            yapılacağı ve varsa ilgili sayfanın bağlantısı yazar.
        </div>
    </div>

    {{-- ==================== SECTION 2: KONTROLLER ==================== --}}
    <div class="row g-4" id="shChecksGrid">
        @foreach($health['checks'] as $index => $check)
            @php
                $statusLabel = match ($check['status']) {
                    'ok'       => 'Sağlıklı',
                    'warning'  => 'Uyarı',
                    'critical' => 'Kritik',
                    default    => 'Bilinmiyor',
                };
                $statusIcon = match ($check['status']) {
                    'ok'       => 'bi-check-circle-fill',
                    'warning'  => 'bi-exclamation-triangle-fill',
                    'critical' => 'bi-x-octagon-fill',
                    default    => 'bi-question-circle',
                };
            @endphp
            <div class="col-xl-4 col-md-6" data-aos="fade-up" data-aos-delay="{{ min($index * 60, 300) }}">
                <div class="sh-check sh-check--{{ $check['status'] }}">
                    <div class="sh-check__head">
                        <div class="sh-check__icon"><i class="bi {{ $check['icon'] }}"></i></div>
                        <div class="sh-check__title">
                            <span class="sh-check__label">{{ $check['label'] }}</span>
                            <span class="sh-check__status">
                                <i class="bi {{ $statusIcon }}"></i> {{ $statusLabel }}
                            </span>
                        </div>
                    </div>

                    <p class="sh-check__message">{{ $check['message'] }}</p>

                    @if($check['detail'])
                        <p class="sh-check__detail">{{ $check['detail'] }}</p>
                    @endif

                    <div class="sh-check__foot">
                        @if($check['what'])
                            <p class="sh-check__what"><i class="bi bi-question-circle me-1"></i>{{ $check['what'] }}</p>
                        @endif

                        @if($check['hint'])
                            <p class="sh-check__hint"><i class="bi bi-lightbulb-fill me-1"></i>{{ $check['hint'] }}</p>
                        @endif

                        @if($check['url'] && $check['status'] !== 'ok')
                            <a href="{{ $check['url'] }}" class="sh-check__action">
                                İlgili sayfaya git <i class="bi bi-arrow-right"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/system-health.js') }}"></script>
@endpush
