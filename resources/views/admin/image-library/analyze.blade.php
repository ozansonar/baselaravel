@extends('layouts.admin')

@section('title', 'Görsel Kütüphanesi Analiz')

@section('content')
<div class="container-fluid py-4 il-analyze">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-graph-up text-warning me-2"></i> Kütüphane Analizi
            </h2>
            <small class="text-muted">Hangi tip/tema/mevsim için ne kadar görsel eksik — AI'dan çekim konusu önerisi al</small>
        </div>
        <a href="{{ route('admin.image-library.index') }}" class="btn-glass">
            <i class="bi bi-arrow-left me-1"></i> Listeye Dön
        </a>
    </div>

    {{-- Genel Özet --}}
    @php $s = $analysis['summary']; @endphp
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="il-stat il-stat--total">
                <span class="il-stat__label">Toplam</span>
                <span class="il-stat__value">{{ $s['total'] }}</span>
                <small class="il-stat__sub">{{ $s['completion_pct'] }}% yıllık hedefe</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="il-stat">
                <span class="il-stat__label">Yıllık Hedef</span>
                <span class="il-stat__value text-warning">{{ $s['annual_target'] }}</span>
                <small class="il-stat__sub">365 gün × 1 post/gün</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="il-stat">
                <span class="il-stat__label">Hiç Kullanılmamış</span>
                <span class="il-stat__value">{{ $s['never_used'] }}</span>
                <small class="il-stat__sub">{{ $s['used_count'] }} görsel kullanıldı</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="il-stat">
                <span class="il-stat__label">Ortalama Kullanım</span>
                <span class="il-stat__value">{{ $s['avg_usage'] }}</span>
                <small class="il-stat__sub">Toplam {{ $s['total_usage'] }} kullanım</small>
            </div>
        </div>
    </div>

    {{-- Plan Simulasyonu --}}
    @php $sim = $analysis['plan_simulation']; @endphp
    <div class="card-dark mb-4">
        <div class="card-header-custom">
            <div class="form-section-header mb-0">
                <div class="form-section-icon {{ $sim['can_fulfill'] ? 'bg-icon-green' : 'bg-icon-purple' }}">
                    <i class="bi {{ $sim['can_fulfill'] ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' }}"></i>
                </div>
                <div>
                    <h6 class="mb-0">{{ $sim['days'] }} Günlük Plan Simülasyonu</h6>
                    <small class="text-muted">
                        @if($sim['can_fulfill'])
                            ✓ Kütüphane şu anda {{ $sim['days'] }} günlük plan üretimini karşılayabilir
                        @else
                            ⚠ {{ $sim['total_short'] }} görsel eksik — plan eksikliklerle tamamlanır
                        @endif
                    </small>
                </div>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">
                @foreach(['feed' => 'Feed Post', 'story' => 'Story', 'reels' => 'Reels'] as $type => $label)
                    <div class="col-md-4">
                        <div class="il-sim-card {{ $sim['shortfall'][$type] > 0 ? 'is-short' : 'is-ok' }}">
                            <div class="il-sim-label">{{ $label }}</div>
                            <div class="il-sim-numbers">
                                <span class="il-sim-have">{{ $sim['available'][$type] }}</span>
                                <span class="il-sim-sep">/</span>
                                <span class="il-sim-need">{{ $sim['needs'][$type] }}</span>
                            </div>
                            @if($sim['shortfall'][$type] > 0)
                                <div class="il-sim-short">
                                    <i class="bi bi-exclamation-circle"></i> {{ $sim['shortfall'][$type] }} eksik
                                </div>
                            @else
                                <div class="il-sim-ok"><i class="bi bi-check-circle"></i> Yeterli</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Tip × Tema Matrisi --}}
    <div class="card-dark mb-4">
        <div class="card-header-custom">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-blue"><i class="bi bi-grid-3x3"></i></div>
                <div>
                    <h6 class="mb-0">Tip × Tema Stok Matrisi</h6>
                    <small class="text-muted">Yıllık hedef dağılıma göre eksik kalemler kırmızı işaretli</small>
                </div>
            </div>
        </div>
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="table il-matrix mb-0">
                    <thead>
                        <tr>
                            <th>Tema</th>
                            @foreach(['feed' => '📷 Feed', 'story' => '📍 Story', 'reels' => '🎬 Reels'] as $type => $label)
                                <th class="text-center">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(\App\Models\MediaLibraryImage::THEMES as $theme)
                            <tr>
                                <td><strong>{{ (new \App\Models\MediaLibraryImage(['theme' => $theme]))->themeLabel() }}</strong></td>
                                @foreach(['feed', 'story', 'reels'] as $type)
                                    @php $cell = $analysis['by_theme_type'][$type][$theme] ?? ['target' => 0, 'current' => 0, 'missing' => 0, 'pct' => 0]; @endphp
                                    <td class="text-center il-cell {{ $cell['missing'] > 0 ? 'is-short' : 'is-ok' }}">
                                        <div class="il-cell-numbers">{{ $cell['current'] }} / {{ $cell['target'] }}</div>
                                        <div class="il-cell-bar">
                                            <div class="il-cell-fill" style="width: {{ $cell['pct'] }}%"></div>
                                        </div>
                                        @if($cell['missing'] > 0)
                                            <small class="text-danger">{{ $cell['missing'] }} eksik</small>
                                        @else
                                            <small class="text-success">✓</small>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- AI Öneri Bölümü --}}
    <div class="card-dark mb-4 il-ai-suggest">
        <div class="card-header-custom">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-purple"><i class="bi bi-stars"></i></div>
                <div>
                    <h6 class="mb-0">AI'dan Çekim Konusu Önerisi</h6>
                    <small class="text-muted">Eksik kategoriler için neyi çekmen gerektiğini AI önerir (~$0.001 maliyet)</small>
                </div>
            </div>
        </div>
        <div class="card-body-custom">
            <button type="button" class="btn-teal" id="ilFetchAiBtn">
                <i class="bi bi-stars me-1"></i>
                <span data-default>AI'dan Çekim Önerileri Al</span>
                <span data-loading class="d-none"><i class="bi bi-hourglass-split me-1"></i> AI düşünüyor (~10sn)...</span>
            </button>
            <div id="ilAiSuggestionsResult" class="mt-3"></div>
        </div>
    </div>

    {{-- Disk Kullanım Raporu --}}
    @php $disk = $analysis['disk_usage']; @endphp
    <div class="card-dark mb-4">
        <div class="card-header-custom">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-blue"><i class="bi bi-hdd-fill"></i></div>
                <div>
                    <h6 class="mb-0">Disk Kullanım Raporu</h6>
                    <small class="text-muted">Toplam {{ $disk['total_human'] }} · {{ $disk['count_with_files'] }} dosya</small>
                </div>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="row g-3 mb-3">
                <div class="col-md-3 col-6">
                    <div class="il-stat il-stat--total">
                        <span class="il-stat__label">Toplam Boyut</span>
                        <span class="il-stat__value">{{ $disk['total_human'] }}</span>
                        <small class="il-stat__sub">{{ $disk['count_with_files'] }} dosya</small>
                    </div>
                </div>
                @foreach(['feed' => 'Feed', 'reels' => 'Reels', 'story' => 'Story'] as $type => $label)
                    <div class="col-md-3 col-6">
                        <div class="il-stat">
                            <span class="il-stat__label">{{ $label }}</span>
                            <span class="il-stat__value">{{ $disk['by_type_bytes'][$type] ?? '0 B' }}</span>
                            <small class="il-stat__sub">
                                @php $pct = $disk['total_bytes'] > 0 ? round(($disk['by_type_raw'][$type] / $disk['total_bytes']) * 100) : 0; @endphp
                                %{{ $pct }} oran
                            </small>
                        </div>
                    </div>
                @endforeach
            </div>

            @if(! empty($disk['top_largest']))
                <h6 class="text-muted mb-2 mt-3"><i class="bi bi-arrow-down me-1"></i> En Büyük 10 Dosya</h6>
                <div class="table-responsive">
                    <table class="table table-sm" style="color:#e5e7eb;">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Görsel</th>
                                <th style="width: 100px;">Tip</th>
                                <th style="width: 120px;">Boyut</th>
                                <th>Açıklama</th>
                                <th style="width: 100px;" class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($disk['top_largest'] as $i => $row)
                                <tr>
                                    <td class="text-muted">{{ $i + 1 }}</td>
                                    <td>
                                        <img src="{{ upload_url($row['path'], 'thumb') }}"
                                             style="width:48px;height:48px;object-fit:cover;border-radius:4px;" loading="lazy">
                                    </td>
                                    <td>
                                        @if($row['is_video'])
                                            <span class="badge bg-purple">🎬 {{ ucfirst($row['media_type']) }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($row['media_type']) }}</span>
                                        @endif
                                    </td>
                                    <td><strong class="text-warning">{{ $row['size_human'] }}</strong></td>
                                    <td><small class="text-muted">{{ $row['description'] ?? '—' }}</small></td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.image-library.edit', $row['id']) }}" class="btn-glass btn-glass-sm" title="Düzenle">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Çok büyük dosyalar disk doluluğunu artırır. Optimize etmek istersen düzenleyip yeniden yükleyebilirsin.
                </small>
            @endif
        </div>
    </div>

    {{-- Kullanılmayan Görsel Raporu --}}
    @php $unused = $analysis['unused']; @endphp
    @if($unused['count'] > 0)
        <div class="card-dark mb-4">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-purple"><i class="bi bi-clock-history"></i></div>
                    <div>
                        <h6 class="mb-0">Kullanılmayan Görseller</h6>
                        <small class="text-muted">Hiç kullanılmamış {{ $unused['count'] }} görsel</small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="il-stat">
                            <span class="il-stat__label">Son hafta yüklenen</span>
                            <span class="il-stat__value text-success">{{ $unused['by_age']['last_week'] }}</span>
                            <small class="il-stat__sub">Henüz kullanılma şansı bulamadı</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="il-stat">
                            <span class="il-stat__label">Son ay yüklenen</span>
                            <span class="il-stat__value text-warning">{{ $unused['by_age']['last_month'] }}</span>
                            <small class="il-stat__sub">Tag'leri kontrol et — eşleşme almıyor olabilir</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="il-stat">
                            <span class="il-stat__label">1+ ay önce yüklenen</span>
                            <span class="il-stat__value text-danger">{{ $unused['by_age']['older'] }}</span>
                            <small class="il-stat__sub">Etiketleme gözden geçirilmeli veya silinmeli</small>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="{{ route('admin.image-library.index', ['usage' => 'never']) }}" class="btn-glass">
                        <i class="bi bi-eye me-1"></i> Kullanılmamış Görselleri Listele
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('styles')
<style>
.il-stat { background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 14px 16px; display: flex; flex-direction: column; height: 100%; }
.il-stat__label { font-size: 0.78rem; color: #9ca3af; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.il-stat__value { font-size: 2rem; font-weight: 700; color: #e5e7eb; line-height: 1.2; margin: 4px 0; }
.il-stat__sub   { font-size: 0.72rem; color: #6b7280; }
.il-stat--total { border-color: rgba(245,158,11,0.4); background: rgba(245,158,11,0.04); }
.il-stat--total .il-stat__value { color: #fcd34d; }

/* Plan simulasyonu */
.il-sim-card {
    padding: 16px; border-radius: 10px;
    border: 2px solid rgba(255,255,255,0.08);
    background: rgba(0,0,0,0.3);
    text-align: center;
}
.il-sim-card.is-ok    { border-color: rgba(34,197,94,0.4); background: rgba(34,197,94,0.05); }
.il-sim-card.is-short { border-color: rgba(239,68,68,0.4); background: rgba(239,68,68,0.05); }
.il-sim-label   { font-size: 0.85rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
.il-sim-numbers { font-size: 1.5rem; font-weight: 700; margin: 6px 0; }
.il-sim-have    { color: #fcd34d; }
.il-sim-sep     { color: #6b7280; margin: 0 4px; }
.il-sim-need    { color: #9ca3af; }
.il-sim-short   { color: #fca5a5; font-size: 0.85rem; }
.il-sim-ok      { color: #86efac; font-size: 0.85rem; }

/* Matris */
.il-matrix { color: #e5e7eb; }
.il-matrix thead th { background: rgba(0,0,0,0.3); font-size: 0.85rem; padding: 12px; }
.il-matrix tbody td { vertical-align: middle; padding: 14px 12px; }
.il-cell { min-width: 120px; }
.il-cell.is-short { background: rgba(239,68,68,0.05); }
.il-cell.is-ok    { background: rgba(34,197,94,0.05); }
.il-cell-numbers { font-size: 1rem; font-weight: 600; margin-bottom: 4px; }
.il-cell-bar { height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden; }
.il-cell-fill { height: 100%; background: linear-gradient(90deg, #fbbf24, #f59e0b); transition: width 0.3s; }
.il-cell.is-ok .il-cell-fill { background: linear-gradient(90deg, #34d399, #10b981); }

/* AI öneri */
.il-suggest-card {
    background: rgba(168,85,247,0.06);
    border: 1px solid rgba(168,85,247,0.2);
    border-radius: 8px; padding: 14px; margin-bottom: 12px;
}
.il-suggest-card h6 { color: #c4b5fd; margin-bottom: 8px; }
.il-suggest-card ul { margin: 0; padding-left: 20px; color: #e5e7eb; }
.il-suggest-card ul li { margin-bottom: 4px; line-height: 1.5; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    var btn = document.getElementById('ilFetchAiBtn');
    var resultEl = document.getElementById('ilAiSuggestionsResult');
    if (! btn) return;

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    btn.addEventListener('click', function () {
        var defaultLbl = btn.querySelector('[data-default]');
        var loadLbl    = btn.querySelector('[data-loading]');
        btn.disabled = true;
        if (defaultLbl) defaultLbl.classList.add('d-none');
        if (loadLbl) loadLbl.classList.remove('d-none');
        resultEl.innerHTML = '';

        fetch('{{ route('admin.image-library.ai-suggestions') }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            body: '{}',
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (r) {
            if (! r.ok || ! r.data.success) {
                resultEl.innerHTML = '<div class="alert alert-danger">' + escapeHtml(r.data.message || 'AI öneri alınamadı') + '</div>';
                return;
            }
            if (! r.data.suggestions || ! r.data.suggestions.length) {
                resultEl.innerHTML = '<div class="alert alert-success">✓ ' + escapeHtml(r.data.message) + '</div>';
                return;
            }

            var html = '<div class="alert alert-success mb-3"><i class="bi bi-check-circle me-1"></i> ' + escapeHtml(r.data.message) + '</div>';
            r.data.suggestions.forEach(function (sug, sidx) {
                html += '<div class="il-suggest-card">';
                html += '<h6>🎬 ' + escapeHtml(sug.type.toUpperCase()) + ' — ' + escapeHtml(sug.theme) + ' <small class="text-muted">(' + sug.missing + ' eksik)</small></h6>';
                html += '<ul>';
                (sug.topics || []).forEach(function (t) {
                    html += '<li>' + escapeHtml(t) + '</li>';
                });
                html += '</ul>';
                html += '</div>';
            });
            // Tüm önerileri çek listesine ekle butonu
            html += '<div class="d-flex justify-content-end mt-3">';
            html += '<button type="button" class="btn-teal" id="ilAddToShotListBtn" data-payload=\'' + escapeHtml(JSON.stringify(r.data.suggestions)) + '\'>';
            html += '<i class="bi bi-camera2 me-1"></i> Tümünü Çek Listesine Ekle</button>';
            html += '</div>';
            resultEl.innerHTML = html;

            // Çek listesine ekle handler
            var addBtn = document.getElementById('ilAddToShotListBtn');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    var payloadRaw = this.dataset.payload;
                    var payload;
                    try { payload = JSON.parse(payloadRaw); } catch (e) { alert('Veri parse hatası'); return; }

                    // AI önerilerini flatten: her topic ayrı bir item
                    var items = [];
                    payload.forEach(function (sug) {
                        (sug.topics || []).forEach(function (topic) {
                            items.push({ topic: topic, media_type: sug.type, theme: sug.theme || null });
                        });
                    });

                    if (items.length === 0) { alert('Eklenecek öneri yok'); return; }

                    addBtn.disabled = true;
                    addBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Ekleniyor...';

                    fetch('{{ route('admin.shot-list.bulk-from-ai') }}', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ items: items }),
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        alert(d.message);
                        if (d.success) {
                            window.location.href = '{{ route('admin.shot-list.index') }}';
                        }
                    })
                    .catch(function (e) { alert('Hata: ' + e.message); })
                    .finally(function () {
                        addBtn.disabled = false;
                        addBtn.innerHTML = '<i class="bi bi-camera2 me-1"></i> Tümünü Çek Listesine Ekle';
                    });
                });
            }
        })
        .catch(function (e) {
            resultEl.innerHTML = '<div class="alert alert-danger">İstek başarısız: ' + escapeHtml(e.message) + '</div>';
        })
        .finally(function () {
            btn.disabled = false;
            if (defaultLbl) defaultLbl.classList.remove('d-none');
            if (loadLbl) loadLbl.classList.add('d-none');
        });
    });
})();
</script>
@endpush
