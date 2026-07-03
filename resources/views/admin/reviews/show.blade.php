@extends('layouts.admin')

@section('title', 'Değerlendirme Detayı')
@section('page_title', 'Değerlendirme Detayı')
@section('page_description', $review->product?->name ?? 'Ürün Yorumu')

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.reviews.index') }}" class="breadcrumb-link">Değerlendirmeler</a>
            </li>
            <li class="breadcrumb-item active text-teal">Detay</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.reviews.index') }}" class="btn-glass btn-sm">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title">Değerlendirme Detayı</h1>
                <p class="page-subtitle">{{ $review->name }} tarafından yapılan değerlendirme</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn-teal btn-sm" data-bs-toggle="modal" data-bs-target="#editReviewModal">
                <i class="bi bi-pencil me-1"></i> Düzenle
            </button>
            @if($review->status !== \App\Enums\ReviewStatus::Approved)
                <form method="POST" action="{{ route('admin.reviews.approve', $review) }}" id="approveForm">
                    @csrf
                    @method('PATCH')
                    <button type="button" class="btn-teal btn-sm" onclick="confirmAction('approve')">
                        <i class="bi bi-check-lg me-1"></i> Onayla
                    </button>
                </form>
            @endif
            @if($review->status !== \App\Enums\ReviewStatus::Rejected)
                <form method="POST" action="{{ route('admin.reviews.reject', $review) }}" id="rejectForm">
                    @csrf
                    @method('PATCH')
                    <button type="button" class="btn-glass btn-sm" onclick="confirmAction('reject')">
                        <i class="bi bi-x-lg me-1"></i> Reddet
                    </button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" id="deleteForm">
                @csrf
                @method('DELETE')
                <button type="button" class="btn-glass btn-sm text-neon-red" onclick="confirmAction('delete')">
                    <i class="bi bi-trash3 me-1"></i> Sil
                </button>
            </form>
        </div>
    </div>

    <div class="row g-4" data-aos="fade-up" data-aos-delay="100">

        <!-- Left Column: Main Content -->
        <div class="col-lg-8">

            <!-- Review Content Card -->
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <h6><i class="bi bi-chat-quote-fill me-2 text-neon-orange"></i>Değerlendirme</h6>
                    @php
                        $statusColors = ['pending' => 'ord-status-pending', 'approved' => 'ord-status-delivered', 'rejected' => 'ord-status-cancelled'];
                    @endphp
                    <span class="ord-status-badge {{ $statusColors[$review->status->value] ?? '' }}">
                        <i class="bi bi-{{ $review->status === \App\Enums\ReviewStatus::Approved ? 'check-circle' : ($review->status === \App\Enums\ReviewStatus::Rejected ? 'x-circle' : 'clock') }}"></i>
                        {{ $review->status->label() }}
                    </span>
                </div>
                <div class="card-body-custom">
                    <!-- Star Rating -->
                    <div class="rv-rating-display mb-4">
                        <div class="rv-stars">
                            @for($i = 1; $i <= 5; $i++)
                                <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }}"></i>
                            @endfor
                        </div>
                        <span class="rv-rating-text">{{ $review->rating }}/5 Puan</span>
                    </div>

                    <!-- Comment -->
                    <div class="rv-comment-text">{{ $review->comment }}</div>

                    <!-- Timestamp -->
                    <div class="rv-timestamp mt-4">
                        <i class="bi bi-clock me-1"></i>
                        {{ $review->created_at->translatedFormat('d M Y H:i') }}
                        <span class="text-clr-muted ms-1">({{ $review->created_at->diffForHumans() }})</span>
                    </div>
                </div>
            </div>

            <!-- Product Info Card -->
            @if($review->product)
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <h6><i class="bi bi-box-seam-fill me-2 text-neon-blue"></i>Ürün Bilgisi</h6>
                </div>
                <div class="card-body-custom p-0">
                    <div class="rv-info-list">
                        <div class="rv-info-row">
                            <span class="rv-info-label">Ürün Adı</span>
                            <span class="rv-info-value fw-semibold">{{ $review->product->name }}</span>
                        </div>
                        @if($review->product->category)
                        <div class="rv-info-row">
                            <span class="rv-info-label">Kategori</span>
                            <span class="rv-info-value">{{ $review->product->category->name }}</span>
                        </div>
                        @endif
                        <div class="rv-info-row">
                            <span class="rv-info-label">Fiyat</span>
                            <span class="rv-info-value fw-semibold text-teal">{{ number_format($review->product->currentPrice(), 2, ',', '.') }} &#8378;</span>
                        </div>
                        @if($review->product->sku)
                        <div class="rv-info-row">
                            <span class="rv-info-label">SKU</span>
                            <span class="rv-info-value">
                                <code class="rv-sku-code">{{ $review->product->sku }}</code>
                            </span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

        </div>

        <!-- Right Column: Sidebar -->
        <div class="col-lg-4">

            <!-- Reviewer Info Card -->
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <h6><i class="bi bi-person-fill me-2 text-neon-green"></i>Kişi Bilgileri</h6>
                </div>
                <div class="card-body-custom">
                    <!-- Avatar + Name -->
                    <div class="rv-reviewer-header mb-3">
                        <div class="rv-reviewer-avatar">{{ $review->initials() }}</div>
                        <div>
                            <div class="fw-semibold">{{ $review->name }}</div>
                            <div class="text-clr-muted small">{{ $review->email }}</div>
                        </div>
                    </div>

                    <div class="rv-info-list">
                        <div class="rv-info-row">
                            <span class="rv-info-label"><i class="bi bi-envelope me-1"></i> E-posta</span>
                            <a href="mailto:{{ $review->email }}" class="rv-info-value text-teal">{{ $review->email }}</a>
                        </div>
                        @if($review->phone)
                        <div class="rv-info-row">
                            <span class="rv-info-label"><i class="bi bi-telephone me-1"></i> Telefon</span>
                            <a href="tel:{{ $review->phone }}" class="rv-info-value text-teal">{{ $review->phone }}</a>
                        </div>
                        @endif
                        <div class="rv-info-row">
                            <span class="rv-info-label"><i class="bi bi-globe me-1"></i> IP Adresi</span>
                            <span class="rv-info-value">{{ $review->ip_address ?? '-' }}</span>
                        </div>
                        <div class="rv-info-row">
                            <span class="rv-info-label"><i class="bi bi-calendar3 me-1"></i> Tarih</span>
                            <span class="rv-info-value">{{ $review->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card-dark">
                <div class="card-header-custom">
                    <h6><i class="bi bi-lightning-fill me-2 text-neon-orange"></i>Hızlı İşlemler</h6>
                </div>
                <div class="card-body-custom">
                    <div class="d-grid gap-2">
                        <a href="mailto:{{ $review->email }}?subject=Re: {{ urlencode($review->product?->name ?? 'Değerlendirme') }} Hakkında"
                           class="btn-glass btn-sm text-center">
                            <i class="bi bi-reply me-1"></i> E-posta Gönder
                        </a>
                        @if($review->phone)
                        <a href="tel:{{ $review->phone }}" class="btn-glass btn-sm text-center">
                            <i class="bi bi-telephone me-1"></i> Telefon ile Ara
                        </a>
                        @endif
                        @if($review->product)
                        <a href="{{ route('admin.products.edit', $review->product) }}" class="btn-glass btn-sm text-center">
                            <i class="bi bi-box-seam me-1"></i> Ürüne Git
                        </a>
                        @endif
                    </div>
                </div>
            </div>

        </div>

    </div>

    {{-- Edit Review Modal --}}
    <div class="modal fade" id="editReviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-theme">
            <div class="modal-content modal-content-theme">
                <form method="POST" action="{{ route('admin.reviews.update', $review) }}" id="editReviewForm">
                    @csrf
                    @method('PUT')
                    <div class="modal-header modal-header-theme">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil-square text-teal me-2"></i>Değerlendirme Düzenle
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                    </div>
                    <div class="modal-body modal-body-theme">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-clr-secondary">İsim <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-theme" name="name"
                                       value="{{ $review->name }}" required maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-clr-secondary">E-posta <span class="text-danger">*</span></label>
                                <input type="email" class="form-control form-control-theme" name="email"
                                       value="{{ $review->email }}" required maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-clr-secondary">Telefon</label>
                                <input type="text" class="form-control form-control-theme" name="phone"
                                       value="{{ $review->phone }}" maxlength="20">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-clr-secondary">Puan <span class="text-danger">*</span></label>
                                <select class="form-select form-select-theme" name="rating" required>
                                    @for($s = 5; $s >= 1; $s--)
                                        <option value="{{ $s }}" {{ $review->rating === $s ? 'selected' : '' }}>
                                            {{ $s }} {{ str_repeat('★', $s) }}{{ str_repeat('☆', 5 - $s) }}
                                        </option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-clr-secondary">Yorum <span class="text-danger">*</span></label>
                                <textarea class="form-control form-control-theme" name="comment" rows="5"
                                          required minlength="5" maxlength="2000">{{ $review->comment }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-clr-secondary">Yorum Tarihi <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control form-control-theme" name="created_at"
                                       value="{{ $review->created_at->format('Y-m-d\TH:i') }}" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer modal-footer-theme">
                        <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                        <button type="submit" class="btn-teal">
                            <i class="bi bi-check-lg me-1"></i> Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
function confirmAction(type) {
    var configs = {
        approve: {
            title: 'Onay',
            message: 'Bu değerlendirmeyi onaylamak istediğinize emin misiniz? Yorum sitede yayınlanacaktır.',
            type: 'success',
            confirmText: 'Evet, Onayla',
            confirmIcon: 'bi bi-check-lg',
            formId: 'approveForm'
        },
        reject: {
            title: 'Reddetme Onayı',
            message: 'Bu değerlendirmeyi reddetmek istediğinize emin misiniz?',
            type: 'warning',
            confirmText: 'Evet, Reddet',
            confirmIcon: 'bi bi-x-lg',
            formId: 'rejectForm'
        },
        delete: {
            title: 'Silme Onayı',
            message: 'Bu değerlendirmeyi silmek istediğinize emin misiniz?',
            type: 'danger',
            confirmText: 'Evet, Sil',
            confirmIcon: 'bi bi-trash3',
            warning: 'Bu işlem geri alınabilir.',
            formId: 'deleteForm'
        }
    };

    var config = configs[type];
    if (!config) return;

    AdminModal.confirm({
        title: config.title,
        message: config.message,
        type: config.type,
        confirmText: config.confirmText,
        confirmIcon: config.confirmIcon,
        warning: config.warning || ''
    }).then(function (confirmed) {
        if (confirmed) {
            document.getElementById(config.formId).submit();
        }
    });
}
</script>
@endpush
