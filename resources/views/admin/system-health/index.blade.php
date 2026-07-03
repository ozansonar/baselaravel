@extends('layouts.admin')

@section('title', 'Sistem Sağlık')

@section('content')
<div class="container-fluid py-4 sh-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-heart-pulse text-warning me-2"></i> Sistem Sağlık
            </h2>
            <small class="text-muted">10 kritik sistem kontrolü — DB, queue, disk, IG token, AI, Telegram, storage, PHP, son paylaşım, son yedek</small>
        </div>
        <button type="button" class="btn-teal" id="shRefreshBtn">
            <i class="bi bi-arrow-clockwise me-1"></i> Yeniden Kontrol Et
        </button>
    </div>

    {{-- Genel durum kartı --}}
    @php
        $overall = $health['summary']['overall'];
        $overallLabel = match($overall) {
            'ok'       => 'Tüm Sistem Sağlıklı',
            'warning'  => 'Bazı Uyarılar Var',
            'critical' => 'Kritik Sorunlar Var',
            default    => 'Bilinmeyen',
        };
        $overallIcon = match($overall) {
            'ok'       => 'bi-check-circle-fill',
            'warning'  => 'bi-exclamation-triangle-fill',
            'critical' => 'bi-x-octagon-fill',
            default    => 'bi-question-circle',
        };
    @endphp
    <div class="card-dark mb-4 sh-overall sh-overall--{{ $overall }}">
        <div class="card-body-custom d-flex align-items-center gap-3 flex-wrap">
            <div class="sh-overall__icon">
                <i class="bi {{ $overallIcon }}"></i>
            </div>
            <div class="flex-grow-1">
                <h5 class="mb-1">{{ $overallLabel }}</h5>
                <small class="text-muted">
                    Son kontrol: <span id="shCheckedAt">{{ \Carbon\Carbon::parse($health['checked_at'])->format('d.m.Y H:i:s') }}</span>
                </small>
            </div>
            <div class="d-flex gap-3 flex-wrap">
                <div class="sh-counter sh-counter--ok">
                    <strong>{{ $health['summary']['ok'] }}</strong>
                    <small>Sağlıklı</small>
                </div>
                <div class="sh-counter sh-counter--warning">
                    <strong>{{ $health['summary']['warning'] }}</strong>
                    <small>Uyarı</small>
                </div>
                <div class="sh-counter sh-counter--critical">
                    <strong>{{ $health['summary']['critical'] }}</strong>
                    <small>Kritik</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Kontrol kartları --}}
    <div class="row g-3" id="shChecksGrid">
        @foreach($health['checks'] as $check)
            <div class="col-md-6 col-xl-4">
                <div class="sh-check-card sh-check-card--{{ $check['status'] }}">
                    <div class="sh-check-card__header">
                        <span class="sh-check-card__icon">
                            @if($check['status'] === 'ok')
                                <i class="bi bi-check-circle-fill"></i>
                            @elseif($check['status'] === 'warning')
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            @else
                                <i class="bi bi-x-octagon-fill"></i>
                            @endif
                        </span>
                        <span class="sh-check-card__label">{{ $check['label'] }}</span>
                    </div>
                    <div class="sh-check-card__message">{{ $check['message'] }}</div>
                    @if($check['detail'])
                        <div class="sh-check-card__detail">{{ $check['detail'] }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('styles')
<style>
.sh-overall {
    border-left: 4px solid #6b7280;
    transition: border-color 0.2s;
}
.sh-overall--ok       { border-left-color: #10b981; }
.sh-overall--warning  { border-left-color: #f59e0b; }
.sh-overall--critical { border-left-color: #ef4444; }
.sh-overall__icon {
    width: 56px; height: 56px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem;
    background: rgba(255,255,255,0.05);
    border-radius: 12px;
}
.sh-overall--ok .sh-overall__icon       { background: rgba(16,185,129,0.12); color: #10b981; }
.sh-overall--warning .sh-overall__icon  { background: rgba(245,158,11,0.12); color: #f59e0b; }
.sh-overall--critical .sh-overall__icon { background: rgba(239,68,68,0.12);  color: #ef4444; }

.sh-counter {
    text-align: center;
    padding: 8px 14px;
    border-radius: 8px;
    background: rgba(0,0,0,0.3);
    min-width: 70px;
}
.sh-counter strong { display: block; font-size: 1.4rem; color: #e5e7eb; }
.sh-counter small  { color: #9ca3af; font-size: 0.7rem; text-transform: uppercase; }
.sh-counter--ok       strong { color: #6ee7b7; }
.sh-counter--warning  strong { color: #fcd34d; }
.sh-counter--critical strong { color: #fca5a5; }

/* Check kartları */
.sh-check-card {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.08);
    border-left: 3px solid #6b7280;
    border-radius: 8px;
    padding: 14px 16px;
    height: 100%;
    transition: all 0.15s;
}
.sh-check-card:hover { background: rgba(0,0,0,0.4); }
.sh-check-card--ok       { border-left-color: #10b981; }
.sh-check-card--warning  { border-left-color: #f59e0b; }
.sh-check-card--critical { border-left-color: #ef4444; background: rgba(239,68,68,0.05); }

.sh-check-card__header {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 6px;
}
.sh-check-card__icon { font-size: 1rem; }
.sh-check-card--ok       .sh-check-card__icon { color: #10b981; }
.sh-check-card--warning  .sh-check-card__icon { color: #f59e0b; }
.sh-check-card--critical .sh-check-card__icon { color: #ef4444; }
.sh-check-card__label   { color: #e5e7eb; font-weight: 600; font-size: 0.9rem; }
.sh-check-card__message { color: #d1d5db; font-size: 0.85rem; line-height: 1.4; }
.sh-check-card__detail  { color: #6b7280; font-size: 0.75rem; margin-top: 4px; font-family: 'JetBrains Mono', monospace; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';
    var btn = document.getElementById('shRefreshBtn');
    if (! btn) return;
    var defaultLabel = btn.innerHTML;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Kontrol ediliyor...';

        fetch('{{ route('admin.system-health.json') }}', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function () {
            // Yeni JSON geldi — sayfayı yenile (kart'ları yeniden render etmek yerine)
            window.location.reload();
        })
        .catch(function (e) {
            AdminModal.status({
                title: 'Kontrol Başarısız',
                message: e.message,
                type: 'danger',
            });
            btn.disabled = false;
            btn.innerHTML = defaultLabel;
        });
    });
})();
</script>
@endpush
