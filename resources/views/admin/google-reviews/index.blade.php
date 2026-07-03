@extends('layouts.admin')

@section('title', 'Google Yorumları')
@section('page_title', 'Google Yorumları')
@section('page_description', 'Google Maps işletme yorumlarını yönetin')

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Google Yorumları</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Google Yorumları</h1>
            <p class="page-subtitle">Google Maps üzerinden gelen işletme yorumları</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn-glass" onclick="openAddReviewModal()">
                <i class="bi bi-plus-lg me-1"></i> Manuel Ekle
            </button>
            <form method="POST" action="{{ route('admin.google-reviews.sync') }}" id="syncForm">
                @csrf
                <button type="submit" class="btn-glass">
                    <i class="bi bi-arrow-repeat me-1"></i> Senkronize Et
                </button>
            </form>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-4 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-google"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Yorum</span>
                    <h3 class="usr-stat-value" data-count="{{ $reviews->count() }}">0</h3>
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
                    <h3 class="usr-stat-value" data-count="{{ $reviews->where('is_visible', true)->count() }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-4 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-star-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Ortalama Puan</span>
                    <h3 class="usr-stat-value">{{ $stats['average'] }}</h3>
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
                            <th>Puan</th>
                            <th>Yazar</th>
                            <th class="d-none d-md-table-cell">Yorum</th>
                            <th class="d-none d-lg-table-cell">Tarih</th>
                            <th>Görünürlük</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reviews as $review)
                            <tr>
                                <td data-label="Puan">
                                    <span class="text-warning">
                                        @for($i = 1; $i <= 5; $i++)
                                            <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }}"></i>
                                        @endfor
                                    </span>
                                </td>
                                <td data-label="Yazar">
                                    <div class="cl-content-info">
                                        <span class="cl-content-title">{{ $review->author_name }}</span>
                                        @if($review->relative_time)
                                            <span class="cl-content-meta">{{ $review->relative_time }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Yorum" class="d-none d-md-table-cell">
                                    <span class="cl-content-meta">{{ Str::limit($review->text, 80) }}</span>
                                </td>
                                <td data-label="Tarih" class="d-none d-lg-table-cell">
                                    @if($review->review_time)
                                        <div class="cl-content-info">
                                            <span class="usr-meta">{{ $review->review_time->format('d.m.Y') }}</span>
                                            <span class="cl-content-meta">{{ $review->review_time->format('H:i') }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="Görünürlük">
                                    @if($review->trashed())
                                        <span class="usr-status-badge inactive">Silinmiş</span>
                                    @elseif($review->is_visible)
                                        <span class="usr-status-badge active">Görünür</span>
                                    @else
                                        <span class="usr-status-badge inactive">Gizli</span>
                                    @endif
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        <button type="button" class="usr-action-btn info" title="Detay" onclick="showReviewDetail({{ $review->id }})">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        @if($review->trashed())
                                            <form method="POST" action="{{ route('admin.google-reviews.restore', $review->id) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="usr-action-btn success" title="Geri Yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.google-reviews.toggle', $review) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="usr-action-btn {{ $review->is_visible ? 'warning' : 'success' }}" title="{{ $review->is_visible ? 'Gizle' : 'Göster' }}">
                                                    <i class="bi bi-{{ $review->is_visible ? 'eye-slash' : 'eye' }}"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.google-reviews.destroy', $review) }}" class="d-inline" id="deleteForm-{{ $review->id }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="usr-action-btn danger" title="Sil" onclick="confirmDelete({{ $review->id }}, {{ json_encode($review->author_name) }})"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-google d-block fs-1 mb-2"></i>
                                    Google yorumu bulunamadı. "Senkronize Et" butonuna tıklayarak yorumları çekebilirsiniz.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection

<!-- Detail Modal -->
<div class="modal fade" id="reviewDetailModal" tabindex="-1" aria-labelledby="reviewDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reviewDetailModalLabel">
                    <i class="bi bi-chat-quote me-2"></i>Yorum Detayı
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="confirm-status-icon confirm-status-green" id="rdm-avatar">
                        <span class="confirm-icon-animated" id="rdm-initials"></span>
                    </div>
                    <div>
                        <h6 class="mb-0" id="rdm-author"></h6>
                        <small class="text-muted" id="rdm-date"></small>
                    </div>
                </div>
                <div class="mb-3" id="rdm-stars"></div>
                <p class="mb-0" id="rdm-text"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-teal" data-bs-dismiss="modal">
                    <i class="bi bi-check-lg me-1"></i> Tamam
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Review Modal -->
<div class="modal fade" id="addReviewModal" tabindex="-1" aria-labelledby="addReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.google-reviews.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addReviewModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>Manuel Yorum Ekle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="author_name" class="form-label">Yazar Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="author_name" name="author_name" required placeholder="Müşteri adı soyadı" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label for="rating" class="form-label">Puan <span class="text-danger">*</span></label>
                        <select class="form-select" id="rating" name="rating" required>
                            <option value="5">★★★★★ (5)</option>
                            <option value="4">★★★★☆ (4)</option>
                            <option value="3">★★★☆☆ (3)</option>
                            <option value="2">★★☆☆☆ (2)</option>
                            <option value="1">★☆☆☆☆ (1)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="text" class="form-label">Yorum <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="text" name="text" rows="4" required placeholder="Müşteri yorumu..." maxlength="5000"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="review_time" class="form-label">Yorum Tarihi</label>
                        <input type="date" class="form-control" id="review_time" name="review_time">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn-teal">
                        <i class="bi bi-check-lg me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    @php
        $reviewsJson = $reviews->keyBy('id')->map(fn ($r) => [
            'author_name' => $r->author_name,
            'initials' => $r->initials,
            'rating' => $r->rating,
            'text' => $r->text,
            'review_time' => $r->review_time?->format('d.m.Y H:i'),
            'relative_time' => $r->relative_time,
        ]);
    @endphp
    var reviewsData = @json($reviewsJson);

    function showReviewDetail(id) {
        var review = reviewsData[id];
        if (!review) return;

        document.getElementById('rdm-initials').textContent = review.initials;
        document.getElementById('rdm-author').textContent = review.author_name;
        document.getElementById('rdm-date').textContent = review.review_time || review.relative_time || '';

        var stars = '';
        for (var i = 1; i <= 5; i++) {
            stars += '<i class="bi bi-star' + (i <= review.rating ? '-fill' : '') + ' text-warning"></i> ';
        }
        document.getElementById('rdm-stars').innerHTML = stars;
        document.getElementById('rdm-text').textContent = review.text;

        var modal = new bootstrap.Modal(document.getElementById('reviewDetailModal'));
        modal.show();
    }

    function confirmDelete(id, authorName) {
        AdminModal.confirm({
            title: 'Silme Onayı',
            message: 'Bu yorumu silmek istediğinizden emin misiniz?',
            type: 'danger',
            confirmText: 'Evet, Sil',
            confirmIcon: 'bi bi-trash3',
            detailTitle: authorName,
            detailMeta: 'Google Yorumu'
        }).then(function (confirmed) {
            if (confirmed) {
                document.getElementById('deleteForm-' + id).submit();
            }
        });
    }

    function openAddReviewModal() {
        var modal = new bootstrap.Modal(document.getElementById('addReviewModal'));
        modal.show();
    }
</script>
@endpush
