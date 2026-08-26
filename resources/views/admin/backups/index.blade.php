@extends('layouts.admin')

@section('title', 'Sistem Yedekleri')

@section('content')
<div class="container-fluid py-4 bk-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-cloud-download text-warning me-2"></i> Sistem Yedekleri
            </h2>
            <small class="text-muted">DB + uploads klasörü ZIP formatında — günlük otomatik veya manuel</small>
        </div>
        <button type="button" class="btn-teal" id="bkRunBtn">
            <i class="bi bi-play-fill me-1"></i>
            <span data-default>Şimdi Yedek Al</span>
            <span data-loading class="d-none"><i class="bi bi-hourglass-split me-1"></i> Alınıyor...</span>
        </button>
    </div>

    {{-- Bilgilendirme --}}
    <div class="alert alert-info bk-help">
        <strong><i class="bi bi-info-circle me-1"></i> Yedek Sistemi nasıl çalışır?</strong>
        <ol class="mb-0 mt-2 ps-4 small">
            <li><strong>Otomatik:</strong> Cron her gece 03:00'te yeni yedek alır</li>
            <li><strong>İçerik:</strong> Tüm DB + public/uploads klasörü tek ZIP'te</li>
            <li><strong>Saklama:</strong> Settings'teki <code>backup_retention_days</code> kadar (default 14 gün)</li>
            <li><strong>Restore:</strong> ZIP'i indir → public/uploads çıkar + database.sql'i import et</li>
            <li><strong>Bildirim:</strong> Yedek alındıktan/başarısızlıktan sonra Telegram'a özet düşer</li>
        </ol>
    </div>

    <div id="bkResult" class="d-none mb-3"></div>

    {{-- Liste --}}
    <div class="card-dark">
        <div class="card-body-custom p-0">
            @if(empty($backups))
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-cloud display-3 d-block mb-2 opacity-25"></i>
                    <p class="mb-2">Henüz yedek yok.</p>
                    <small>Yukarıdaki "Şimdi Yedek Al" butonuyla ilk yedeğini oluşturabilirsin.</small>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table mb-0" style="color:#e5e7eb;">
                        <thead>
                            <tr>
                                <th>Dosya</th>
                                <th style="width:140px;">Boyut</th>
                                <th style="width:200px;">Tarih</th>
                                <th style="width:200px;" class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($backups as $b)
                                <tr>
                                    <td>
                                        <i class="bi bi-file-earmark-zip text-warning me-1"></i>
                                        <code>{{ $b['name'] }}</code>
                                    </td>
                                    <td><strong>{{ $b['size_human'] }}</strong></td>
                                    <td>
                                        <small>{{ $b['created_at']->format('d.m.Y H:i:s') }}</small><br>
                                        <small class="text-muted">{{ $b['age'] }}</small>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.backups.download', $b['name']) }}" class="btn-teal btn-sm">
                                            <i class="bi bi-download me-1"></i> İndir
                                        </a>
                                        <form method="POST" action="{{ route('admin.backups.destroy', $b['name']) }}" class="d-inline bk-delete-form" data-filename="{{ $b['name'] }}">
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
                    Toplam <strong>{{ count($backups) }}</strong> yedek dosyası ·
                    Toplam boyut: <strong>{{ collect($backups)->sum('size') > 0 ? \Illuminate\Support\Number::fileSize(collect($backups)->sum('size')) : '—' }}</strong>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.bk-help { background: rgba(13,202,240,0.06); border: 1px solid rgba(13,202,240,0.2); color: #cfe9f7; }
.bk-help strong { color: #93c5fd; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    var btn = document.getElementById('bkRunBtn');
    var result = document.getElementById('bkResult');
    if (! btn) return;

    btn.addEventListener('click', function () {
        AdminModal.confirm({
            title: 'Yedek Al',
            message: 'Yedek alma işlemi başlatılacak. Büyük dosyalar varsa birkaç dakika sürebilir.',
            type: 'warning',
            confirmText: 'Başlat',
            confirmIcon: 'bi bi-play-fill',
        }).then(function (confirmed) {
            if (! confirmed) return;
            runBackup();
        });
    });

    function runBackup() {
        var defLbl  = btn.querySelector('[data-default]');
        var loadLbl = btn.querySelector('[data-loading]');
        btn.disabled = true;
        if (defLbl) defLbl.classList.add('d-none');
        if (loadLbl) loadLbl.classList.remove('d-none');
        result.classList.add('d-none');
        result.innerHTML = '';

        fetch('{{ route('admin.backups.create') }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (r) {
            if (r.ok && r.data.success) {
                result.className = 'alert alert-success';
                result.classList.remove('d-none');
                result.innerHTML = '<i class="bi bi-check-circle me-1"></i> ' +
                    '<strong>' + r.data.file + '</strong> oluşturuldu (' + r.data.size_human + ')';
                setTimeout(function () { window.location.reload(); }, 1500);
            } else {
                result.className = 'alert alert-danger';
                result.classList.remove('d-none');
                result.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> ' + (r.data.message || 'Yedek alınamadı');
            }
        })
        .catch(function (e) {
            result.className = 'alert alert-danger';
            result.classList.remove('d-none');
            result.innerHTML = 'İstek başarısız: ' + e.message;
        })
        .finally(function () {
            btn.disabled = false;
            if (defLbl) defLbl.classList.remove('d-none');
            if (loadLbl) loadLbl.classList.add('d-none');
        });
    }

    // Silme onayı — AdminModal kullan
    document.querySelectorAll('.bk-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed === '1') return; // ikinci submit
            e.preventDefault();
            var fileName = form.dataset.filename || '';
            AdminModal.confirm({
                title: 'Yedek Sil',
                message: 'Bu yedek dosyası kalıcı olarak silinecek.',
                detailTitle: fileName,
                warning: 'Bu işlem geri alınamaz.',
                type: 'danger',
                confirmText: 'Evet, Sil',
                confirmIcon: 'bi bi-trash3',
            }).then(function (confirmed) {
                if (! confirmed) return;
                form.dataset.confirmed = '1';
                form.submit();
            });
        });
    });
})();
</script>
@endpush
