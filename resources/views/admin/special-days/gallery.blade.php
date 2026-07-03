@extends('layouts.admin')

@section('title', 'Galeri — ' . $day->name)

@section('content')
<div class="container-fluid py-4 sd-gallery-page">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-images text-warning me-2"></i> {{ $day->name }} — Galeri
            </h2>
            <small class="text-muted">
                <code>{{ $day->date->format('d.m.Y') }} {{ $day->turkishWeekday() }}</code>
                · <span class="sd-badge {{ $day->categoryBadgeClass() }}">{{ $day->categoryLabel() }}</span>
                @if($day->theme) · <em>{{ Str::limit($day->theme, 80) }}</em> @endif
            </small>
        </div>
        <a href="{{ route('admin.special-days.index', ['year' => $day->date->year]) }}" class="btn-glass">
            <i class="bi bi-arrow-left me-1"></i> Listeye Dön
        </a>
    </div>

    {{-- Kullanım rehberi --}}
    <div class="alert alert-info sd-help">
        <strong><i class="bi bi-info-circle me-1"></i> Galeri nasıl çalışır?</strong>
        <ol class="mb-0 mt-2 ps-4 small">
            <li>Bu güne özel hazırladığın görselleri yükle (Story / Feed Post / Reels)</li>
            <li>Bulk import sırasında "Yıllık Özel Günler" preset'i bu galeriden <strong>en az kullanılan</strong> görseli otomatik seçer</li>
            <li>Tüm görseller en az 1 kez kullanıldıysa <strong>recycle</strong> başlar — Telegram'a "yeni görsel ekle" bildirimi atılır</li>
            <li>Story için 1080×1920, Feed için 1080×1080, Reels için 1080×1920 görsel yükle</li>
            <li>Her tip için minimum 5 görsel önerilir (yıllık paylaşım çeşitliliği için)</li>
        </ol>
    </div>

    {{-- Upload widget --}}
    <div class="card-dark mb-4">
        <div class="card-header-custom">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-purple"><i class="bi bi-cloud-upload"></i></div>
                <div>
                    <h6 class="mb-0">Yeni Görsel Yükle</h6>
                    <small class="text-muted">JPG/PNG/WebP — max 8 MB / dosya · max 20 dosya / batch</small>
                </div>
            </div>
        </div>
        <div class="card-body-custom">
            <form id="sdUploadForm" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Medya Tipi <span class="text-danger">*</span></label>
                        <select name="media_type" class="form-select form-control-theme" required>
                            <option value="story">📍 Story (1080×1920)</option>
                            <option value="feed">📷 Feed Post (1080×1080)</option>
                            <option value="reels">🎬 Reels (1080×1920 video poster)</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Alt Text (opsiyonel — SEO + erişilebilirlik)</label>
                        <input type="text" name="alt_text" class="form-control form-control-theme"
                               maxlength="250" placeholder="Anneler Günü kahvaltı sofrası">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Görseller <span class="text-danger">*</span></label>
                        <input type="file" name="images[]" id="sdImageInput" class="form-control form-control-theme"
                               accept=".jpg,.jpeg,.png,.webp" multiple required>
                        <small class="form-text text-muted">Birden fazla seçim için Ctrl/Cmd basılı tut</small>
                    </div>
                </div>
                <div class="mt-3 d-flex justify-content-end">
                    <button type="submit" class="btn-teal" id="sdUploadBtn">
                        <i class="bi bi-cloud-upload me-1"></i>
                        <span data-default>Yükle</span>
                        <span data-loading class="d-none"><i class="bi bi-hourglass-split me-1"></i> Yükleniyor...</span>
                    </button>
                </div>
                <div id="sdUploadStatus" class="mt-2 small"></div>
            </form>
        </div>
    </div>

    {{-- Mevcut görseller — tip bazında gruplu --}}
    @php
        $grouped = $images->groupBy('media_type');
        $types   = [
            'story' => ['label' => 'Story', 'icon' => 'bi-circle-fill', 'size' => '1080×1920'],
            'feed'  => ['label' => 'Feed Post', 'icon' => 'bi-image-fill', 'size' => '1080×1080'],
            'reels' => ['label' => 'Reels', 'icon' => 'bi-camera-reels-fill', 'size' => '1080×1920'],
        ];
    @endphp

    @foreach($types as $key => $meta)
        @php $list = $grouped[$key] ?? collect(); @endphp
        <div class="card-dark mb-4">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-blue"><i class="bi {{ $meta['icon'] }}"></i></div>
                    <div>
                        <h6 class="mb-0">{{ $meta['label'] }} <small class="text-muted">({{ $meta['size'] }})</small></h6>
                        <small class="text-muted">
                            {{ $list->count() }} görsel ·
                            @if($list->isNotEmpty())
                                {{ $list->sum('usage_count') }} toplam kullanım
                            @else
                                <span class="text-warning">Henüz görsel yok — bulk import bu tipte fail eder</span>
                            @endif
                        </small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">
                @if($list->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-images display-4 d-block mb-2 opacity-25"></i>
                        <small>Bu tipte görsel yok. Yukarıdaki form ile <strong>{{ $meta['label'] }}</strong> seçip yükle.</small>
                    </div>
                @else
                    <div class="sd-img-grid">
                        @foreach($list as $img)
                            <div class="sd-img-card" data-id="{{ $img->id }}">
                                <div class="sd-img-thumb-wrap">
                                    <img src="{{ upload_url($img->image_path, 'md') }}" alt="{{ $img->alt_text ?? '' }}" loading="lazy">
                                </div>
                                <div class="sd-img-meta">
                                    <span class="sd-img-usage" title="Kullanım sayısı">
                                        <i class="bi bi-eye"></i> {{ $img->usage_count }}
                                    </span>
                                    @if($img->last_used_at)
                                        <small class="text-muted" title="Son kullanım">
                                            {{ $img->last_used_at->diffForHumans() }}
                                        </small>
                                    @else
                                        <small class="text-success">Hiç kullanılmadı</small>
                                    @endif
                                    <button type="button" class="sd-img-delete" data-id="{{ $img->id }}" title="Sil">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endsection

@push('styles')
<style>
.sd-help { background: rgba(13,202,240,0.06); border: 1px solid rgba(13,202,240,0.2); color: #cfe9f7; }
.sd-help strong { color: #93c5fd; }

.sd-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; }
.sd-badge.badge-ulusal   { background: rgba(239,68,68,0.15); color: #fca5a5; }
.sd-badge.badge-dini     { background: rgba(34,197,94,0.15); color: #86efac; }
.sd-badge.badge-ozel     { background: rgba(236,72,153,0.15); color: #f9a8d4; }
.sd-badge.badge-sezonsal { background: rgba(245,158,11,0.15); color: #fcd34d; }
.sd-badge.badge-genel    { background: rgba(156,163,175,0.15); color: #d1d5db; }

.sd-img-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
}
.sd-img-card {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s ease;
}
.sd-img-card:hover { border-color: rgba(245,158,11,0.4); transform: translateY(-2px); }
.sd-img-thumb-wrap {
    aspect-ratio: 1 / 1;
    background: #000;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
}
.sd-img-thumb-wrap img { width: 100%; height: 100%; object-fit: cover; }
.sd-img-meta {
    padding: 8px 10px;
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap;
    font-size: 0.78rem;
}
.sd-img-usage {
    background: rgba(34,197,94,0.12);
    color: #86efac;
    padding: 2px 8px; border-radius: 10px;
    font-weight: 600;
}
.sd-img-delete {
    background: none; border: none;
    color: #fca5a5; cursor: pointer;
    margin-left: auto; padding: 2px 6px;
    border-radius: 4px;
    transition: background 0.15s;
}
.sd-img-delete:hover { background: rgba(239,68,68,0.15); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    // Upload form
    var form = document.getElementById('sdUploadForm');
    var btn  = document.getElementById('sdUploadBtn');
    var status = document.getElementById('sdUploadStatus');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var defaultLbl = btn.querySelector('[data-default]');
            var loadLbl    = btn.querySelector('[data-loading]');

            btn.disabled = true;
            if (defaultLbl) defaultLbl.classList.add('d-none');
            if (loadLbl) loadLbl.classList.remove('d-none');
            status.innerHTML = '';

            var fd = new FormData(form);

            fetch('{{ route('admin.special-days.gallery.upload', $day->id) }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: fd,
            })
            .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
            .then(function (r) {
                if (r.ok && r.data.success) {
                    status.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i> ' + r.data.message + ' Sayfa yenileniyor...</span>';
                    setTimeout(function () { window.location.reload(); }, 1000);
                } else {
                    var msg = r.data.message || 'Yükleme başarısız';
                    if (r.data.errors) {
                        msg += '<br>' + Object.values(r.data.errors).flat().join('<br>');
                    }
                    status.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i> ' + msg + '</span>';
                }
            })
            .catch(function (e) {
                status.innerHTML = '<span class="text-danger">İstek başarısız: ' + e.message + '</span>';
            })
            .finally(function () {
                btn.disabled = false;
                if (defaultLbl) defaultLbl.classList.remove('d-none');
                if (loadLbl) loadLbl.classList.add('d-none');
            });
        });
    }

    // Görsel sil
    document.querySelectorAll('.sd-img-delete').forEach(function (b) {
        b.addEventListener('click', function () {
            if (! confirm('Bu görseli silmek istediğine emin misin?')) return;
            var id = this.dataset.id;
            var card = this.closest('.sd-img-card');

            fetch('{{ url('admin/ozel-gunler/galeri-image') }}/' + id, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            })
            .then(function (res) { return res.json(); })
            .then(function (d) {
                if (d.success) {
                    card.style.opacity = '0';
                    setTimeout(function () { card.remove(); }, 300);
                } else {
                    alert(d.message || 'Silme başarısız');
                }
            });
        });
    });
})();
</script>
@endpush
