@extends('layouts.admin')

@section('title', 'Bildirimler')

@section('content')
<div class="container-fluid py-4 nt-page">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-bell-fill text-warning me-2"></i> Bildirim Merkezi
                @if($unreadCount > 0)
                    <span class="badge bg-danger ms-2">{{ $unreadCount }} okunmamış</span>
                @endif
            </h2>
            <small class="text-muted">Sistem olayları + admin uyarıları — Telegram'a giden bildirimler de buraya düşer</small>
        </div>
        @if($unreadCount > 0)
            <button type="button" class="btn-glass" id="ntMarkAllRead">
                <i class="bi bi-check-all me-1"></i> Tümünü Okundu İşaretle
            </button>
        @endif
    </div>

    {{-- Filtre --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted">Seviye</label>
                    <select name="level" class="form-select form-select-sm form-control-theme">
                        <option value="">Hepsi</option>
                        <option value="info" @selected(request('level') === 'info')>Info</option>
                        <option value="success" @selected(request('level') === 'success')>Başarı</option>
                        <option value="warning" @selected(request('level') === 'warning')>Uyarı</option>
                        <option value="error" @selected(request('level') === 'error')>Hata</option>
                        <option value="critical" @selected(request('level') === 'critical')>Kritik</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-4">
                        <input type="checkbox" name="unread_only" value="1" id="ntUnreadOnly" class="form-check-input" @checked(request()->boolean('unread_only'))>
                        <label class="form-check-label small" for="ntUnreadOnly">Sadece okunmamışlar</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn-teal btn-sm w-100"><i class="bi bi-funnel"></i> Filtrele</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Liste --}}
    <div class="card-dark">
        <div class="card-body-custom p-0">
            @if($notifications->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bell-slash display-3 d-block mb-2 opacity-25"></i>
                    <small>Henüz bildirim yok.</small>
                </div>
            @else
                <ul class="nt-list">
                    @foreach($notifications as $notif)
                        <li class="nt-item nt-item--{{ $notif->level }} {{ $notif->isUnread() ? 'is-unread' : '' }}">
                            <div class="nt-item__icon">
                                <i class="bi {{ $notif->levelIcon() }}"></i>
                            </div>
                            <div class="nt-item__body">
                                <div class="nt-item__header">
                                    <strong>{{ $notif->title }}</strong>
                                    <small class="text-muted">{{ $notif->created_at->diffForHumans() }}</small>
                                </div>
                                @if($notif->message)
                                    <div class="nt-item__message">{{ $notif->message }}</div>
                                @endif
                                @if($notif->action_url)
                                    <a href="{{ $notif->action_url }}" class="nt-item__action">
                                        Detay <i class="bi bi-arrow-right"></i>
                                    </a>
                                @endif
                            </div>
                            <div class="nt-item__actions">
                                @if($notif->isUnread())
                                    <button type="button" class="btn-glass btn-glass-sm nt-mark-read" data-id="{{ $notif->id }}" title="Okundu işaretle">
                                        <i class="bi bi-check"></i>
                                    </button>
                                @endif
                                <form method="POST" action="{{ route('admin.notifications.destroy', $notif->id) }}" class="d-inline nt-delete-form">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-glass btn-glass-sm text-danger" title="Sil">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="p-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted">{{ $notifications->total() }} bildirim — sayfa {{ $notifications->currentPage() }}/{{ $notifications->lastPage() }}</small>
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.nt-list { list-style: none; padding: 0; margin: 0; }
.nt-item {
    display: flex; gap: 14px; padding: 14px 18px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    transition: background 0.15s;
}
.nt-item:hover { background: rgba(255,255,255,0.02); }
.nt-item.is-unread { background: rgba(245,158,11,0.04); border-left: 3px solid #f59e0b; }

.nt-item__icon {
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.nt-item--info     .nt-item__icon { background: rgba(59,130,246,0.15);  color: #93c5fd; }
.nt-item--success  .nt-item__icon { background: rgba(16,185,129,0.15);  color: #6ee7b7; }
.nt-item--warning  .nt-item__icon { background: rgba(245,158,11,0.15);  color: #fcd34d; }
.nt-item--error    .nt-item__icon { background: rgba(239,68,68,0.15);   color: #fca5a5; }
.nt-item--critical .nt-item__icon { background: rgba(239,68,68,0.25);   color: #fff; }

.nt-item__body { flex: 1; min-width: 0; }
.nt-item__header { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; margin-bottom: 4px; }
.nt-item__header strong { color: #e5e7eb; }
.nt-item__message { color: #d1d5db; font-size: 0.85rem; line-height: 1.4; }
.nt-item__action {
    display: inline-block; margin-top: 6px;
    color: #fcd34d; font-size: 0.82rem; text-decoration: none;
}
.nt-item__action:hover { color: #fef3c7; text-decoration: underline; }
.nt-item__actions { display: flex; gap: 4px; align-items: flex-start; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    document.querySelectorAll('.nt-mark-read').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.dataset.id;
            fetch('{{ url('admin/bildirimler') }}/' + id + '/okundu', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    var item = btn.closest('.nt-item');
                    if (item) item.classList.remove('is-unread');
                    btn.remove();
                }
            });
        });
    });

    // Tek tek silme — AdminModal onayı
    document.querySelectorAll('.nt-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed === '1') return;
            e.preventDefault();
            AdminModal.confirm({
                title: 'Bildirimi Sil',
                message: 'Bu bildirim kalıcı olarak silinecek.',
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

    var allBtn = document.getElementById('ntMarkAllRead');
    if (allBtn) {
        allBtn.addEventListener('click', function () {
            AdminModal.confirm({
                title: 'Tümünü Okundu İşaretle',
                message: 'Tüm bildirimler okundu olarak işaretlenecek.',
                type: 'warning',
                confirmText: 'Onayla',
                confirmIcon: 'bi bi-check-all',
            }).then(function (confirmed) {
                if (! confirmed) return;
                fetch('{{ route('admin.notifications.mark-all-read') }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                })
                .then(function (r) { return r.json(); })
                .then(function () { window.location.reload(); });
            });
        });
    }
})();
</script>
@endpush
