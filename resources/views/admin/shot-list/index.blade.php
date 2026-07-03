@extends('layouts.admin')

@section('title', 'Çekim Listesi')

@section('content')
<div class="container-fluid py-4 sl-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-camera2 text-warning me-2"></i> Çekim Listesi
            </h2>
            <small class="text-muted">Fotoğraf/video çekim yapılacaklar — AI gap analizinden gelen + manuel eklenen</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn-teal" data-bs-toggle="modal" data-bs-target="#slAddModal">
                <i class="bi bi-plus-lg me-1"></i> Yeni Çekim Ekle
            </button>
        </div>
    </div>

    <div class="alert alert-info">
        <strong><i class="bi bi-info-circle me-1"></i> Çekim Listesi nasıl çalışır?</strong>
        <ol class="mb-0 mt-2 ps-4 small">
            <li>Çekmen gereken konuları buraya kaydet (manuel veya AI öneri ile)</li>
            <li>Çekim öncesi listeyi kontrol et, hedeflerine odaklan</li>
            <li>Çektikçe "Yapıldı" olarak işaretle (otomatik kim/ne zaman tutulur)</li>
            <li><strong>AI Önerileri:</strong> <a href="{{ route('admin.image-library.analyze') }}" class="text-teal">Görsel kütüphanesi analizinde</a> "Çek Listesine Ekle" butonu ile direkt aktarılır</li>
        </ol>
    </div>

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="sl-stat sl-stat--pending">
                <span class="sl-stat__label">Bekleyen</span>
                <span class="sl-stat__value">{{ $stats['pending'] }}</span>
                @if($stats['high_priority_pending'] > 0)
                    <small class="sl-stat__sub text-danger"><i class="bi bi-exclamation-triangle"></i> {{ $stats['high_priority_pending'] }} yüksek öncelik</small>
                @endif
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sl-stat">
                <span class="sl-stat__label">Planlandı</span>
                <span class="sl-stat__value text-info">{{ $stats['planned'] }}</span>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sl-stat">
                <span class="sl-stat__label">Tamamlanan</span>
                <span class="sl-stat__value text-success">{{ $stats['done'] }}</span>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sl-stat">
                <span class="sl-stat__label">Toplam</span>
                <span class="sl-stat__value">{{ $stats['pending'] + $stats['planned'] + $stats['done'] }}</span>
            </div>
        </div>
    </div>

    {{-- Filtre + Liste --}}
    <div class="card-dark">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.shot-list.index') }}" class="row g-2 mb-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted">Durum</label>
                    <select name="status" class="form-select form-select-sm form-control-theme">
                        <option value="">Hepsi</option>
                        @foreach(\App\Models\ShotListItem::STATUSES as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Tip</label>
                    <select name="media_type" class="form-select form-select-sm form-control-theme">
                        <option value="">Hepsi</option>
                        @foreach(\App\Models\MediaLibraryImage::MEDIA_TYPES as $t)
                            <option value="{{ $t }}" @selected(request('media_type') === $t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn-teal btn-sm w-100"><i class="bi bi-funnel"></i> Filtrele</button>
                </div>
            </form>

            @if($items->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-camera display-3 d-block mb-2 opacity-25"></i>
                    <p class="mb-2">Henüz çekim listende öğe yok.</p>
                    <button type="button" class="btn-teal" data-bs-toggle="modal" data-bs-target="#slAddModal">
                        <i class="bi bi-plus-lg me-1"></i> İlk çekimi ekle
                    </button>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table mb-0" style="color:#e5e7eb;">
                        <thead>
                            <tr>
                                <th>Konu</th>
                                <th style="width:90px;">Tip</th>
                                <th style="width:130px;">Tema</th>
                                <th style="width:90px;">Öncelik</th>
                                <th style="width:90px;">Durum</th>
                                <th style="width:120px;">Eklenme</th>
                                <th style="width:160px;" class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                <tr data-id="{{ $item->id }}" class="sl-row sl-row--{{ $item->status }}">
                                    <td>
                                        <strong>{{ $item->topic }}</strong>
                                        @if($item->ai_generated)
                                            <span class="badge bg-info ms-1" title="AI tarafından önerildi"><i class="bi bi-stars"></i></span>
                                        @endif
                                        @if($item->notes)
                                            <small class="d-block text-muted">{{ Str::limit($item->notes, 80) }}</small>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-secondary">{{ ucfirst($item->media_type) }}</span></td>
                                    <td><small class="text-muted">{{ $item->theme ? (new \App\Models\MediaLibraryImage(['theme' => $item->theme]))->themeLabel() : '—' }}</small></td>
                                    <td><span class="badge {{ $item->priorityBadgeClass() }}">{{ $item->priorityLabel() }}</span></td>
                                    <td><span class="badge {{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span></td>
                                    <td><small class="text-muted">{{ $item->created_at->diffForHumans() }}</small></td>
                                    <td class="text-end">
                                        <select class="form-select form-select-sm sl-status-select" data-id="{{ $item->id }}" style="width:auto; display:inline-block;">
                                            @foreach(\App\Models\ShotListItem::STATUSES as $s)
                                                <option value="{{ $s }}" @selected($item->status === $s)>{{ ucfirst($s) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" class="btn-glass btn-glass-sm sl-delete-btn" data-id="{{ $item->id }}" title="Sil">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted">{{ $items->total() }} öğe</small>
                    {{ $items->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Add Modal --}}
<div class="modal fade" id="slAddModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-lg me-1"></i> Yeni Çekim Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="slAddForm">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Konu <span class="text-danger">*</span></label>
                        <input type="text" name="topic" class="form-control form-control-theme" required minlength="3" maxlength="250" placeholder="Örn: Yayık tereyağı yapımı (timelapse)">
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Tip <span class="text-danger">*</span></label>
                            <select name="media_type" class="form-select form-control-theme" required>
                                <option value="feed">Feed</option>
                                <option value="story">Story</option>
                                <option value="reels">Reels</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tema</label>
                            <select name="theme" class="form-select form-control-theme">
                                <option value="">— yok —</option>
                                @foreach(\App\Models\MediaLibraryImage::THEMES as $t)
                                    <option value="{{ $t }}">{{ (new \App\Models\MediaLibraryImage(['theme' => $t]))->themeLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Öncelik</label>
                            <select name="priority" class="form-select form-control-theme">
                                <option value="normal">Normal</option>
                                <option value="high">Yüksek</option>
                                <option value="low">Düşük</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Notlar (opsiyonel)</label>
                        <textarea name="notes" class="form-control form-control-theme" rows="2" maxlength="2000"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-glass" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn-teal" id="slAddBtn">Ekle</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.sl-stat { background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 14px 16px; }
.sl-stat__label { font-size: 0.78rem; color: #9ca3af; font-weight: 600; text-transform: uppercase; }
.sl-stat__value { font-size: 2rem; font-weight: 700; color: #e5e7eb; line-height: 1.2; margin: 4px 0; display: block; }
.sl-stat__sub   { font-size: 0.72rem; color: #6b7280; }
.sl-stat--pending { border-color: rgba(245,158,11,0.3); }
.sl-stat--pending .sl-stat__value { color: #fcd34d; }

.sl-row--done { opacity: 0.55; }
.sl-row--done strong { text-decoration: line-through; }
.sl-row--cancelled { opacity: 0.4; }
.sl-status-select { background: rgba(0,0,0,0.5); color: #e5e7eb; border: 1px solid rgba(255,255,255,0.15); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    // Yeni ekle
    var addBtn = document.getElementById('slAddBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            var form = document.getElementById('slAddForm');
            var fd = new FormData(form);
            var data = {};
            fd.forEach(function (v, k) { if (k !== '_token') data[k] = v; });

            addBtn.disabled = true;
            fetch('{{ route('admin.shot-list.store') }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(data),
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (r) {
                if (r.ok && r.data.success) {
                    window.location.reload();
                } else {
                    alert(r.data.message || 'Eklenemedi');
                }
            })
            .catch(function (e) { alert('Hata: ' + e.message); })
            .finally(function () { addBtn.disabled = false; });
        });
    }

    // Status değişikliği inline
    document.querySelectorAll('.sl-status-select').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var id = this.dataset.id;
            var status = this.value;
            fetch('{{ route('admin.shot-list.update', ['item' => '__ID__']) }}'.replace('__ID__', id), {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ status: status }),
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    setTimeout(function () { window.location.reload(); }, 300);
                } else {
                    alert(d.message || 'Güncellenemedi');
                }
            });
        });
    });

    // Sil
    document.querySelectorAll('.sl-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (! confirm('Bu çekim öğesini silmek istediğinden emin misin?')) return;
            var id = this.dataset.id;
            var row = this.closest('tr');
            fetch('{{ route('admin.shot-list.destroy', ['item' => '__ID__']) }}'.replace('__ID__', id), {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success && row) {
                    row.style.opacity = '0';
                    setTimeout(function () { row.remove(); }, 300);
                } else {
                    alert(d.message || 'Silinemedi');
                }
            });
        });
    });
})();
</script>
@endpush
