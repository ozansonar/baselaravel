@extends('layouts.admin')

@section('title', 'Caption A/B Test')

@push('styles')
<style>
    .cab-segment { transition: transform .15s; }
    .cab-segment:hover { transform: translateY(-2px); }
    .cab-winner { border-color: #198754 !important; box-shadow: 0 0 0 .15rem rgba(25, 135, 84, .25); }
    .cab-bar { height: 8px; border-radius: 4px; background: #e9ecef; overflow: hidden; }
    .cab-bar > div { height: 100%; background: linear-gradient(to right, #4dabf7, #1971c2); }
</style>
@endpush

@section('content')
<div class="container-fluid py-4 cab-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-clipboard-data text-success me-2"></i> Caption A/B Test
            </h2>
            <small class="text-muted">
                {{ number_format($report['total_posts']) }} yayınlanmış post — caption özelliklerine göre segmentlere ayrılıp
                ortalama engagement (like + 2×yorum) karşılaştırması.
            </small>
        </div>
        <form method="GET" class="d-flex gap-2">
            <select name="days" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ([30, 60, 90, 180, 365] as $d)
                    <option value="{{ $d }}" @selected($days === $d)>Son {{ $d }} gün</option>
                @endforeach
            </select>
        </form>
    </div>

    @if ($report['total_posts'] < 5)
        <div class="alert alert-warning mb-4">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Anlamlı karşılaştırma için en az 5 yayınlanmış post gerekiyor. Şu an: {{ $report['total_posts'] }}.
        </div>
    @endif

    @php
        $sections = [
            ['key' => 'length',   'title' => 'Caption Uzunluğu',    'icon' => 'bi-text-paragraph'],
            ['key' => 'emoji',    'title' => 'Emoji Kullanımı',      'icon' => 'bi-emoji-smile'],
            ['key' => 'hashtag',  'title' => 'Hashtag Sayısı',       'icon' => 'bi-hash'],
            ['key' => 'question', 'title' => 'Soru Cümlesi',         'icon' => 'bi-question-circle'],
            ['key' => 'cta',      'title' => 'Çağrı (CTA)',          'icon' => 'bi-megaphone'],
        ];
    @endphp

    @foreach ($sections as $section)
        @php $segments = $report[$section['key']]; @endphp
        @php $maxEng = max(array_column($segments, 'avg_engagement')) ?: 1; @endphp

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi {{ $section['icon'] }} me-2"></i> {{ $section['title'] }}
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach ($segments as $seg)
                        <div class="col-md-{{ count($segments) >= 4 ? 3 : (count($segments) === 3 ? 4 : 6) }}">
                            <div class="cab-segment border rounded p-3 h-100 {{ $seg['is_winner'] ? 'cab-winner' : '' }}">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <strong class="small">{{ $seg['label'] }}</strong>
                                    @if ($seg['is_winner'])
                                        <span class="badge bg-success">
                                            <i class="bi bi-trophy-fill"></i> Kazanan
                                        </span>
                                    @endif
                                </div>

                                <div class="text-muted small">Post sayısı</div>
                                <div class="h5 mb-2">{{ number_format($seg['post_count']) }}</div>

                                <div class="text-muted small">Ort. engagement</div>
                                <div class="h5 mb-2 text-primary">{{ number_format($seg['avg_engagement'], 1) }}</div>

                                <div class="cab-bar mb-2">
                                    <div style="width: {{ $maxEng > 0 ? ($seg['avg_engagement'] / $maxEng) * 100 : 0 }}%"></div>
                                </div>

                                <div class="text-muted small">
                                    Ort. reach: <strong>{{ number_format($seg['avg_reach'], 0) }}</strong>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

    <div class="alert alert-info mb-0 small">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Not:</strong> Instagram'da gerçek A/B test yapılamadığı için yayınlanmış post'lar caption özelliklerine
        göre gruplandırılır ve gruplar arası engagement karşılaştırılır. "Kazanan" işareti en az 2 post içeren
        segmentler arasından seçilir. Bu bir korelasyondur — kesin nedensellik için kontrollü test gerekir.
    </div>
</div>
@endsection
