@extends('layouts.admin')

@section('title', 'Vertex Şablonları')
@section('page_title', 'Vertex Şablonları')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.vertex.index') }}" class="breadcrumb-link">Vertex</a></li>
            <li class="breadcrumb-item active text-teal">Şablonlar</li>
        </ol>
    </nav>

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-collection-fill text-teal me-2"></i>Vertex Şablonları</h1>
            <p class="page-subtitle">Prompt + generation config + referans görsel — yeniden kullanılabilir şablonlar.</p>
        </div>
        <a href="{{ route('admin.vertex.prompts.create') }}" class="btn-teal">
            <i class="bi bi-plus-lg me-1"></i> Yeni Şablon
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card-dark mb-4" data-aos="fade-up">
        <div class="card-body-custom">
            @if($prompts->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size:3rem; opacity:0.3;"></i>
                    <p class="text-clr-muted mt-3 mb-2">Henüz şablon eklenmedi.</p>
                    <a href="{{ route('admin.vertex.prompts.create') }}" class="btn-teal">
                        <i class="bi bi-plus-lg me-1"></i> İlk Şablonu Ekle
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table cl-table align-middle">
                        <thead>
                            <tr>
                                <th>Ad</th>
                                <th>Model</th>
                                <th>Oran</th>
                                <th>Boyut</th>
                                <th>Referans</th>
                                <th>Durum</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($prompts as $p)
                                <tr>
                                    <td>
                                        @if($p->is_pinned)
                                            <i class="bi bi-pin-fill text-warning me-1" title="Sabitlenmiş"></i>
                                        @endif
                                        <strong>{{ $p->name }}</strong>
                                        @if($p->versions_count > 0)
                                            <span class="badge bg-dark ms-1" title="{{ $p->versions_count }} versiyon"><i class="bi bi-clock-history me-1"></i>{{ $p->versions_count }}</span>
                                        @endif
                                        @if($p->description)
                                            <small class="d-block text-clr-muted">{{ $p->description }}</small>
                                        @endif
                                    </td>
                                    <td><small class="badge bg-dark">{{ $p->model }}</small></td>
                                    <td><small class="badge bg-dark">{{ $p->aspect_ratio }}</small></td>
                                    <td><small class="badge bg-dark">{{ $p->image_size }}</small></td>
                                    <td>
                                        @php
                                            $refs = is_array($p->reference_images) ? $p->reference_images : [];
                                            $firstRef = $refs[0]['path'] ?? null;
                                            $refCount = count($refs);
                                        @endphp
                                        @if($firstRef)
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <a href="{{ upload_url($firstRef) }}" target="_blank" rel="noopener" title="{{ basename($firstRef) }}">
                                                    <img src="{{ upload_url($firstRef) }}" alt="Ref" style="width:48px;height:48px;object-fit:cover;border-radius:4px;">
                                                </a>
                                                @if($refCount > 1)
                                                    <span class="badge bg-teal-soft">+{{ $refCount - 1 }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <small class="text-clr-muted">—</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($p->is_active)
                                            <span class="badge bg-success">Aktif</span>
                                        @else
                                            <span class="badge bg-secondary">Pasif</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <button type="button" class="usr-action-btn js-toggle-pin {{ $p->is_pinned ? 'active' : '' }}"
                                                    data-prompt-id="{{ $p->id }}"
                                                    title="{{ $p->is_pinned ? 'Sabitlemeyi Kaldır' : 'Sabitle' }}">
                                                <i class="bi bi-pin{{ $p->is_pinned ? '-fill' : '' }}"></i>
                                            </button>
                                            <a href="{{ route('admin.vertex.prompts.edit', $p) }}" class="usr-action-btn" title="Düzenle">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.vertex.prompts.destroy', $p) }}" class="d-inline js-delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="usr-action-btn danger" title="Sil"
                                                        data-confirm="Bu şablonu silmek istediğine emin misin?">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @include('partials.admin.pagination', ['paginator' => $prompts, 'itemLabel' => 'şablon'])
@endsection

@push('scripts')
<script>
document.querySelectorAll('.js-toggle-pin').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var promptId = btn.dataset.promptId;
        btn.disabled = true;

        fetch('{{ url("admin/vertex/prompts") }}/' + promptId + '/toggle-pin', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false;
            if (data.success) {
                var icon = btn.querySelector('i');
                if (data.is_pinned) {
                    btn.classList.add('active');
                    btn.title = 'Sabitlemeyi Kaldır';
                    icon.className = 'bi bi-pin-fill';
                    var nameCell = btn.closest('tr').querySelector('td:first-child');
                    if (nameCell && !nameCell.querySelector('.bi-pin-fill')) {
                        var pinIcon = document.createElement('i');
                        pinIcon.className = 'bi bi-pin-fill text-warning me-1';
                        pinIcon.title = 'Sabitlenmiş';
                        nameCell.insertBefore(pinIcon, nameCell.firstChild);
                    }
                } else {
                    btn.classList.remove('active');
                    btn.title = 'Sabitle';
                    icon.className = 'bi bi-pin';
                    var nameCell = btn.closest('tr').querySelector('td:first-child');
                    var pinIcon = nameCell ? nameCell.querySelector('.bi-pin-fill') : null;
                    if (pinIcon) pinIcon.remove();
                }
            }
        })
        .catch(function () {
            btn.disabled = false;
        });
    });
});

document.querySelectorAll('.js-delete-form').forEach(function (formEl) {
    formEl.addEventListener('submit', function (e) {
        var btn = formEl.querySelector('button[data-confirm]');
        var msg = btn ? btn.dataset.confirm : 'Silmek istediğinize emin misiniz?';
        e.preventDefault();
        if (window.AdminModal && typeof AdminModal.confirm === 'function') {
            AdminModal.confirm({
                title: 'Onay',
                message: msg,
                type: 'danger',
                confirmText: 'Sil',
                confirmIcon: 'bi bi-trash3',
            }).then(function (confirmed) {
                if (confirmed) formEl.submit();
            });
        } else {
            formEl.submit();
        }
    });
});
</script>
@endpush
