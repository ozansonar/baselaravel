@extends('layouts.admin')

@section('title', 'YouTube Videoları')
@section('page_title', 'YouTube Videoları')
@section('page_description', 'YouTube kanal videolarını yönetin')

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">YouTube Videoları</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">YouTube Videoları</h1>
            <p class="page-subtitle">YouTube kanalınızdan otomatik çekilen videolar</p>
        </div>
        <form method="POST" action="{{ route('admin.youtube-videos.sync') }}" id="syncForm">
            @csrf
            <button type="submit" class="btn-glass">
                <i class="bi bi-arrow-repeat me-1"></i> Senkronize Et
            </button>
        </form>
    </div>

    <!-- Stat Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-4 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-youtube"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Video</span>
                    <h3 class="usr-stat-value" data-count="{{ $videos->count() }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-4 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-eye-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Görünür</span>
                    <h3 class="usr-stat-value" data-count="{{ $videos->where('is_visible', true)->count() }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-4 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-play-circle-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Görüntülenme</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total_views'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>Video</th>
                            <th class="d-none d-md-table-cell">Süre</th>
                            <th class="d-none d-lg-table-cell">Görüntülenme</th>
                            <th class="d-none d-lg-table-cell">Yayın Tarihi</th>
                            <th>Görünürlük</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($videos as $video)
                            <tr>
                                <td data-label="Video">
                                    <div class="d-flex align-items-center gap-3">
                                        @if($video->thumbnail_url)
                                        <div class="cl-thumb-wrap" style="width:96px;height:54px;flex-shrink:0;border-radius:8px;overflow:hidden;">
                                            <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" class="img-fluid" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
                                        </div>
                                        @endif
                                        <div class="cl-content-info">
                                            <span class="cl-content-title">{{ Str::limit($video->title, 60) }}</span>
                                            <a href="{{ $video->watch_url }}" target="_blank" rel="noopener" class="cl-content-meta text-decoration-none">
                                                <i class="bi bi-box-arrow-up-right me-1"></i>YouTube'da Aç
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Süre" class="d-none d-md-table-cell">
                                    <span class="usr-meta">{{ $video->formatted_duration ?: '—' }}</span>
                                </td>
                                <td data-label="Görüntülenme" class="d-none d-lg-table-cell">
                                    <span class="usr-meta">{{ $video->formatted_view_count }}</span>
                                </td>
                                <td data-label="Yayın Tarihi" class="d-none d-lg-table-cell">
                                    @if($video->published_at)
                                        <div class="cl-content-info">
                                            <span class="usr-meta">{{ $video->published_at->format('d.m.Y') }}</span>
                                            <span class="cl-content-meta">{{ $video->published_at->format('H:i') }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="Görünürlük">
                                    @if($video->trashed())
                                        <span class="usr-status-badge inactive">Silinmiş</span>
                                    @elseif($video->is_visible)
                                        <span class="usr-status-badge active">Görünür</span>
                                    @else
                                        <span class="usr-status-badge inactive">Gizli</span>
                                    @endif
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        @if($video->trashed())
                                            <form method="POST" action="{{ route('admin.youtube-videos.restore', $video->id) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="usr-action-btn success" title="Geri Yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.youtube-videos.toggle', $video) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="usr-action-btn {{ $video->is_visible ? 'warning' : 'success' }}" title="{{ $video->is_visible ? 'Gizle' : 'Göster' }}">
                                                    <i class="bi bi-{{ $video->is_visible ? 'eye-slash' : 'eye' }}"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.youtube-videos.destroy', $video) }}" class="d-inline" id="deleteForm-{{ $video->id }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="usr-action-btn danger" title="Sil" onclick="confirmDelete({{ $video->id }}, {{ json_encode($video->title) }})"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-youtube d-block fs-1 mb-2"></i>
                                    YouTube videosu bulunamadı. "Senkronize Et" butonuna tıklayarak videoları çekebilirsiniz.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    function confirmDelete(id, title) {
        AdminModal.confirm({
            title: 'Silme Onayı',
            message: 'Bu videoyu silmek istediğinizden emin misiniz?',
            type: 'danger',
            confirmText: 'Evet, Sil',
            confirmIcon: 'bi bi-trash3',
            detailTitle: title,
            detailMeta: 'YouTube Video'
        }).then(function (confirmed) {
            if (confirmed) {
                document.getElementById('deleteForm-' + id).submit();
            }
        });
    }
</script>
@endpush
