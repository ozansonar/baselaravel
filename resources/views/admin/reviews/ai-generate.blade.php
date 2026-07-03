@extends('layouts.admin')

@section('title', 'AI Yorum Üretici')
@section('page_title', 'AI Yorum Üretici')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.reviews.index') }}" class="breadcrumb-link">Değerlendirmeler</a>
            </li>
            <li class="breadcrumb-item active text-teal">AI Yorum Üretici</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-robot text-teal me-2"></i>AI Yorum Üretici</h1>
            <p class="page-subtitle">Ürün seç, sayı belirle — AI farklı müşteri profillerinden gerçekçi yorumlar üretsin</p>
        </div>
        <a href="{{ route('admin.reviews.index') }}" class="btn-glass">
            <i class="bi bi-arrow-left me-1"></i> Değerlendirmeler
        </a>
    </div>

    <div class="row g-4">
        {{-- Sol: Form --}}
        <div class="col-lg-5" data-aos="fade-up">
            <div class="card-dark">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-teal"><i class="bi bi-stars"></i></div>
                        <div>
                            <h6 class="mb-0">Yorum Üretim Formu</h6>
                            <small class="text-muted">Ürün ve sayı seçip üretin</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="mb-3">
                        <label for="productSelect" class="form-label text-clr-secondary">
                            <i class="bi bi-box-seam me-1 text-teal"></i>Ürün <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-theme" id="productSelect" required>
                            <option value="">— Ürün Seçin —</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">
                                    {{ $product->name }} ({{ $product->category?->name ?? 'Kategorisiz' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="reviewCount" class="form-label text-clr-secondary">
                            <i class="bi bi-hash me-1 text-teal"></i>Yorum Sayısı <span class="text-danger">*</span>
                        </label>
                        <input type="number"
                               class="form-control form-control-theme"
                               id="reviewCount"
                               value="5"
                               min="1"
                               max="20"
                               required>
                        <small class="form-text text-clr-muted">1 ile 20 arası. Farklı müşteri profilleri otomatik oluşturulur.</small>
                    </div>

                    <div class="d-grid">
                        <button type="button" class="btn-teal" id="generateBtn">
                            <i class="bi bi-magic me-1"></i>
                            <span id="generateBtnText">Yorumları Üret</span>
                        </button>
                    </div>

                    <div class="mt-3 p-3" style="background: rgba(13,202,240,0.06); border-radius: 8px; border-left: 3px solid rgba(13,202,240,0.4);">
                        <small class="text-clr-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Üretilen yorumlar <strong>"Beklemede"</strong> statüsünde oluşturulur.
                            Tarihler ürünün eklenme tarihinden bugüne kadar rastgele dağıtılır.
                            <br><br>
                            <i class="bi bi-key me-1"></i>
                            Text API key (<code>gemini_api_key</code>) kullanılır — görsel key'den bağımsız, free tier'da çalışır.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sağ: Sonuçlar --}}
        <div class="col-lg-7" data-aos="fade-up" data-aos-delay="100">
            <div class="card-dark">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-green"><i class="bi bi-chat-square-text"></i></div>
                        <div>
                            <h6 class="mb-0">Üretilen Yorumlar</h6>
                            <small class="text-muted">Üretim sonrası burada görünür — onay/red/sil yapabilirsiniz</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div id="reviewsEmpty" class="text-center py-5">
                        <i class="bi bi-chat-square-dots" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-3 text-clr-muted mb-0">Henüz yorum üretilmedi. Sol panelden ürün seçip üretim başlatın.</p>
                    </div>

                    <div id="reviewsHeader" class="d-none d-flex align-items-center justify-content-between mb-3">
                        <span class="text-clr-secondary">
                            <i class="bi bi-check-circle text-success me-1"></i>
                            <strong id="reviewsCount">0</strong> yorum üretildi
                        </span>
                        <a href="{{ route('admin.reviews.index', ['status' => 'pending']) }}" class="btn-glass btn-sm">
                            <i class="bi bi-arrow-right me-1"></i> Onaya Git
                        </a>
                    </div>

                    <div id="reviewsList" class="d-flex flex-column gap-3"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var generateUrl = @json(route('admin.ai-reviews.generate'));
    var btn = document.getElementById('generateBtn');
    var btnText = document.getElementById('generateBtnText');
    var productSelect = document.getElementById('productSelect');
    var countInput = document.getElementById('reviewCount');
    var reviewsList = document.getElementById('reviewsList');
    var reviewsEmpty = document.getElementById('reviewsEmpty');
    var reviewsHeader = document.getElementById('reviewsHeader');
    var reviewsCount = document.getElementById('reviewsCount');
    var statusColors = { pending: 'warning', approved: 'success', rejected: 'danger' };
    var statusLabels = { pending: 'Beklemede', approved: 'Onaylı', rejected: 'Reddedildi' };

    btn.addEventListener('click', function () {
        var productId = productSelect.value;
        var count = parseInt(countInput.value, 10);

        if (!productId) {
            notify('Lütfen bir ürün seçin.', 'danger');
            return;
        }
        if (!count || count < 1 || count > 20) {
            notify('Yorum sayısı 1-20 arası olmalı.', 'danger');
            return;
        }

        btn.disabled = true;
        btnText.textContent = 'Üretiliyor...';

        if (window.AdminModal && AdminModal.loading) {
            AdminModal.loading.show({
                title: 'AI Yorum Üretiliyor...',
                message: count + ' adet yorum oluşturuluyor, bu işlem 30-60 saniye sürebilir.'
            });
        }

        fetch(generateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ product_id: productId, count: count })
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            btn.disabled = false;
            btnText.textContent = 'Yorumları Üret';
            if (window.AdminModal && AdminModal.loading) AdminModal.loading.hide();

            if (res.data.success && res.data.reviews && res.data.reviews.length > 0) {
                notify(res.data.message, 'success');
                renderReviews(res.data.reviews);
            } else {
                notify(res.data.message || 'Üretim başarısız.', 'danger');
            }
        })
        .catch(function (err) {
            btn.disabled = false;
            btnText.textContent = 'Yorumları Üret';
            if (window.AdminModal && AdminModal.loading) AdminModal.loading.hide();
            notify('Bağlantı hatası: ' + (err.message || 'bilinmeyen hata'), 'danger');
        });
    });

    function renderReviews(reviews) {
        reviewsEmpty.classList.add('d-none');
        reviewsHeader.classList.remove('d-none');
        reviewsCount.textContent = String(reviews.length);
        reviewsList.innerHTML = '';

        reviews.forEach(function (review) {
            var card = document.createElement('div');
            card.className = 'p-3 review-item';
            card.id = 'review-' + review.id;
            card.style.cssText = 'background:rgba(255,255,255,0.03); border-radius:10px; border:1px solid rgba(255,255,255,0.06);';

            var initial = review.name.charAt(0).toUpperCase();
            var stars = '';
            for (var i = 1; i <= 5; i++) {
                stars += '<i class="bi bi-star' + (i <= review.rating ? '-fill' : '') + '"></i>';
            }

            card.innerHTML =
                '<div class="d-flex align-items-start justify-content-between mb-2 flex-wrap gap-2">' +
                    '<div class="d-flex align-items-center gap-2">' +
                        '<div class="prd-detail-avatar">' + initial + '</div>' +
                        '<div>' +
                            '<strong class="text-clr-primary" style="font-size:0.9rem;">' + escHtml(review.name) + '</strong>' +
                            ' <span class="review-status-label badge bg-' + review.status_color + ' ms-1" style="font-size:0.65rem;">' + review.status_label + '</span>' +
                            '<small class="text-clr-muted d-block">' + escHtml(review.created_at) + '</small>' +
                        '</div>' +
                    '</div>' +
                    '<div class="d-flex align-items-center gap-2">' +
                        '<div class="text-warning" style="font-size:0.8rem;">' + stars + '</div>' +
                        '<div class="d-flex gap-1 ms-1">' +
                            '<button class="usr-action-btn js-rv-approve" data-id="' + review.id + '" data-url="' + review.approve_url + '" title="Onayla"><i class="bi bi-check-lg"></i></button>' +
                            '<button class="usr-action-btn js-rv-reject" data-id="' + review.id + '" data-url="' + review.reject_url + '" title="Reddet"><i class="bi bi-x-lg"></i></button>' +
                            '<button class="usr-action-btn danger js-rv-delete" data-id="' + review.id + '" data-url="' + review.delete_url + '" title="Sil"><i class="bi bi-trash"></i></button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<p class="mb-0 text-clr-secondary" style="font-size:0.9rem; line-height:1.6;">' + escHtml(review.comment) + '</p>';

            reviewsList.appendChild(card);
        });

        bindActionButtons();
    }

    function bindActionButtons() {
        reviewsList.querySelectorAll('.js-rv-approve').forEach(function (b) {
            b.onclick = function () {
                if (window.AdminModal && typeof AdminModal.confirm === 'function') {
                    AdminModal.confirm({
                        title: 'Yorumu Onayla',
                        message: 'Bu yorumu onaylamak istediğinize emin misiniz?',
                        type: 'success',
                        confirmText: 'Onayla',
                        confirmIcon: 'bi bi-check-circle-fill'
                    }).then(function (ok) {
                        if (ok) reviewAction(b.dataset.url, 'PATCH', b.dataset.id, 'approve');
                    });
                } else {
                    reviewAction(b.dataset.url, 'PATCH', b.dataset.id, 'approve');
                }
            };
        });
        reviewsList.querySelectorAll('.js-rv-reject').forEach(function (b) {
            b.onclick = function () {
                if (window.AdminModal && typeof AdminModal.confirm === 'function') {
                    AdminModal.confirm({
                        title: 'Yorumu Reddet',
                        message: 'Bu yorumu reddetmek istediğinize emin misiniz?',
                        type: 'warning',
                        confirmText: 'Reddet',
                        confirmIcon: 'bi bi-x-circle-fill'
                    }).then(function (ok) {
                        if (ok) reviewAction(b.dataset.url, 'PATCH', b.dataset.id, 'reject');
                    });
                } else {
                    reviewAction(b.dataset.url, 'PATCH', b.dataset.id, 'reject');
                }
            };
        });
        reviewsList.querySelectorAll('.js-rv-delete').forEach(function (b) {
            b.onclick = function () {
                if (window.AdminModal && typeof AdminModal.confirm === 'function') {
                    AdminModal.confirm({
                        title: 'Yorumu Sil',
                        message: 'Bu yorumu silmek istediğinize emin misiniz?',
                        type: 'danger',
                        confirmText: 'Sil',
                        confirmIcon: 'bi bi-trash3'
                    }).then(function (ok) {
                        if (ok) reviewAction(b.dataset.url, 'DELETE', b.dataset.id, 'delete');
                    });
                } else {
                    reviewAction(b.dataset.url, 'DELETE', b.dataset.id, 'delete');
                }
            };
        });
    }

    function reviewAction(url, method, reviewId, action) {
        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (r) { return r.ok ? r : Promise.reject(r); })
        .then(function () {
            var row = document.getElementById('review-' + reviewId);
            if (!row) return;

            if (action === 'delete') {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(function () { row.remove(); }, 300);
                notify('Yorum silindi.', 'success');
                return;
            }

            var badge = row.querySelector('.review-status-label');
            if (badge) {
                badge.className = 'review-status-label badge bg-' + statusColors[action === 'approve' ? 'approved' : 'rejected'] + ' ms-1';
                badge.style.fontSize = '0.65rem';
                badge.textContent = statusLabels[action === 'approve' ? 'approved' : 'rejected'];
            }

            if (action === 'approve') {
                var ab = row.querySelector('.js-rv-approve');
                if (ab) ab.remove();
            }
            if (action === 'reject') {
                var rb = row.querySelector('.js-rv-reject');
                if (rb) rb.remove();
            }

            notify('Yorum ' + (action === 'approve' ? 'onaylandı' : 'reddedildi') + '.', 'success');
        })
        .catch(function () { notify('İşlem başarısız.', 'danger'); });
    }

    function notify(message, type) {
        if (window.AdminModal && typeof AdminModal.status === 'function') {
            AdminModal.status({ title: type === 'success' ? 'Başarılı' : 'Hata', message: message, type: type });
        }
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
})();
</script>
@endpush
