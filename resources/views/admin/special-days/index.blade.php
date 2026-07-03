@extends('layouts.admin')

@section('title', 'Özel Günler — ' . $year)

@section('content')
<div class="container-fluid py-4 sd-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-calendar-heart text-warning me-2"></i> Özel Günler
            </h2>
            <small class="text-muted">{{ $year }} yılı için Türk özel gün takvimi (sabit / kural / AI / manuel)</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.special-days.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg me-1"></i> Yeni Özel Gün
            </a>
        </div>
    </div>

    {{-- Kullanım rehberi --}}
    <div class="alert alert-info sd-help">
        <strong><i class="bi bi-info-circle me-1"></i> Bu sayfayı nasıl kullanırım?</strong>
        <ol class="mb-0 mt-2 ps-4 small">
            <li><strong>Sabit + Kural Günleri:</strong> Yıl seçildiğinde otomatik eklenir (Yılbaşı, 23 Nisan, Anneler/Babalar Günü vb.)</li>
            <li><strong>Hicri Günler (Ramazan, Bayramlar, Kandiller):</strong> "AI'dan Hicri Günleri Getir" butonuna bas — yıllık 1 kez yeterli</li>
            <li><strong>Manuel Ekleme:</strong> "Yeni Özel Gün" ile elle ekleyebilirsin (lokal/sektörel günler için)</li>
            <li><strong>Galeri:</strong> Her güne "Galeri" linkinden Story / Feed / Reels görselleri ekle. Bulk import'ta otomatik kullanılır (en az kullanılan önce)</li>
            <li><strong>Doğrulama:</strong> AI günlerini Diyanet takvimiyle kontrol et — yanlışsa "Düzenle" ile düzelt</li>
        </ol>
    </div>

    {{-- Toolbar: yıl seçici + butonlar --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-muted small">Yıl seç</label>
                    <select id="sdYearFilter" class="form-select form-control-theme">
                        @foreach($availableYears as $y)
                            <option value="{{ $y }}" {{ $y === $year ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8 d-flex gap-2 flex-wrap justify-content-end">
                    <button type="button" class="btn-glass" id="sdSeedFormulasBtn" data-year="{{ $year }}"
                            title="Sabit (Yılbaşı, 23 Nisan vb.) ve kural-bazlı (Anneler 2.Pzr, Babalar 3.Pzr) günleri seçili yıl için ekler">
                        <i class="bi bi-magic me-1"></i> Sabit + Kural Günleri Seed Et
                    </button>
                    <button type="button" class="btn-teal" id="sdFetchAiBtn" data-year="{{ $year }}"
                            title="Hicri/dini günleri AI'dan çekip kaydeder. Yıllık 1 kez yeterli.">
                        <i class="bi bi-stars me-1"></i> AI'dan Hicri Günleri Getir
                    </button>
                </div>
            </div>

            @if(! empty($missingHicriYears))
                <div class="alert alert-warning small mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Bu yıl(lar) için henüz AI hicri verisi yok:
                    <strong>{{ implode(', ', $missingHicriYears) }}</strong> —
                    yukarıdaki "AI'dan Hicri Günleri Getir" butonuna basarak ekleyebilirsin.
                </div>
            @endif
        </div>
    </div>

    {{-- Liste tablosu --}}
    <div class="card-dark">
        <div class="card-body-custom p-0">
            @if($days->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-calendar-x display-3 d-block mb-2"></i>
                    Bu yıl için kayıtlı özel gün yok. Yukarıdaki butonlardan biriyle başlayabilirsin.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table sd-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 130px;">Tarih</th>
                                <th style="width: 90px;">Gün</th>
                                <th>Ad</th>
                                <th style="width: 100px;">Kategori</th>
                                <th style="width: 90px;">Kaynak</th>
                                <th style="width: 200px;">Galeri (Feed/Reels/Story)</th>
                                <th style="width: 200px;" class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($days as $d)
                                @php $cov = $galleryCoverage[$d->id] ?? ['feed' => 0, 'reels' => 0, 'story' => 0]; @endphp
                                <tr>
                                    <td><code>{{ $d->date->format('d.m.Y') }}</code></td>
                                    <td><span class="text-muted small">{{ $d->turkishWeekday() }}</span></td>
                                    <td>
                                        <strong>{{ $d->name }}</strong>
                                        @if($d->theme)
                                            <small class="d-block text-muted">{{ Str::limit($d->theme, 80) }}</small>
                                        @endif
                                    </td>
                                    <td><span class="sd-badge {{ $d->categoryBadgeClass() }}">{{ $d->categoryLabel() }}</span></td>
                                    <td><span class="sd-badge sd-source-{{ $d->source }}">{{ $d->sourceLabel() }}</span></td>
                                    <td>
                                        <div class="sd-coverage">
                                            <span class="sd-cov sd-cov--feed {{ $cov['feed'] > 0 ? 'has' : 'empty' }}" title="Feed Post">
                                                <i class="bi bi-image"></i> {{ $cov['feed'] }}
                                            </span>
                                            <span class="sd-cov sd-cov--reels {{ $cov['reels'] > 0 ? 'has' : 'empty' }}" title="Reels">
                                                <i class="bi bi-camera-reels"></i> {{ $cov['reels'] }}
                                            </span>
                                            <span class="sd-cov sd-cov--story {{ $cov['story'] > 0 ? 'has' : 'empty' }}" title="Story">
                                                <i class="bi bi-circle-fill"></i> {{ $cov['story'] }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.special-days.gallery', $d->id) }}" class="btn-glass btn-glass-sm" title="Galeri">
                                            <i class="bi bi-images"></i> Galeri
                                        </a>
                                        <a href="{{ route('admin.special-days.edit', $d->id) }}" class="btn-glass btn-glass-sm" title="Düzenle">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.special-days.destroy', $d->id) }}" class="d-inline sd-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-glass btn-glass-sm text-danger" title="Sil">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3 text-muted small">
                    Toplam: <strong>{{ $days->count() }}</strong> özel gün
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.sd-help { background: rgba(13,202,240,0.06); border: 1px solid rgba(13,202,240,0.2); color: #cfe9f7; }
.sd-help strong { color: #93c5fd; }

.sd-table { color: #e5e7eb; }
.sd-table thead th {
    background: rgba(0,0,0,0.3);
    color: #9ca3af;
    font-weight: 600;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.sd-table tbody tr { border-bottom: 1px solid rgba(255,255,255,0.05); }
.sd-table tbody tr:hover { background: rgba(255,255,255,0.025); }
.sd-table td { vertical-align: middle; padding: 12px; }

.sd-badge {
    display: inline-block; padding: 3px 10px; border-radius: 12px;
    font-size: 0.72rem; font-weight: 600; line-height: 1.4;
}
.sd-badge.badge-ulusal   { background: rgba(239,68,68,0.15); color: #fca5a5; }
.sd-badge.badge-dini     { background: rgba(34,197,94,0.15); color: #86efac; }
.sd-badge.badge-ozel     { background: rgba(236,72,153,0.15); color: #f9a8d4; }
.sd-badge.badge-sezonsal { background: rgba(245,158,11,0.15); color: #fcd34d; }
.sd-badge.badge-genel    { background: rgba(156,163,175,0.15); color: #d1d5db; }

.sd-source-fixed  { background: rgba(59,130,246,0.15); color: #93c5fd; }
.sd-source-rule   { background: rgba(168,85,247,0.15); color: #c4b5fd; }
.sd-source-ai     { background: rgba(16,185,129,0.15); color: #6ee7b7; }
.sd-source-manual { background: rgba(245,158,11,0.15); color: #fcd34d; }

.sd-coverage { display: flex; gap: 4px; }
.sd-cov {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 8px; border-radius: 4px;
    font-size: 0.75rem; font-weight: 600;
    border: 1px solid rgba(255,255,255,0.1);
}
.sd-cov.empty { color: rgba(156,163,175,0.5); background: rgba(0,0,0,0.2); }
.sd-cov.has   { color: #86efac; background: rgba(34,197,94,0.12); border-color: rgba(34,197,94,0.3); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    // Yıl filtresi
    document.getElementById('sdYearFilter')?.addEventListener('change', function () {
        var u = new URL(window.location.href);
        u.searchParams.set('year', this.value);
        window.location.href = u.toString();
    });

    function ajaxAction(url, year, btn, defaultText) {
        if (! btn) return;
        var origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> İşleniyor...';

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ year: year }),
        })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (r) {
            if (window.AdminModal && AdminModal.status) {
                AdminModal.status({
                    title: r.data.success ? 'Başarılı' : 'Hata',
                    message: r.data.message || 'Bilinmeyen sonuç',
                    type: r.data.success ? 'success' : 'danger'
                });
            } else {
                alert(r.data.message || 'Bilinmeyen sonuç');
            }
            if (r.data.success) {
                setTimeout(function () { window.location.reload(); }, 1200);
            }
        })
        .catch(function (e) {
            alert('İstek başarısız: ' + (e.message || ''));
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = origText;
        });
    }

    var seedBtn = document.getElementById('sdSeedFormulasBtn');
    if (seedBtn) {
        seedBtn.addEventListener('click', function () {
            ajaxAction('{{ route('admin.special-days.seed-formulas') }}', this.dataset.year, this);
        });
    }

    var aiBtn = document.getElementById('sdFetchAiBtn');
    if (aiBtn) {
        aiBtn.addEventListener('click', function () {
            ajaxAction('{{ route('admin.special-days.fetch-ai') }}', this.dataset.year, this);
        });
    }

    // Silme onayı
    document.querySelectorAll('.sd-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (! confirm('Bu özel günü silmek istediğine emin misin?')) {
                e.preventDefault();
            }
        });
    });
})();
</script>
@endpush
