@extends('layouts.admin')

@section('title', 'Vertex ile Planla — Instagram')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.instagram-posts.index') }}" class="breadcrumb-link"><i class="bi bi-instagram me-1"></i>Instagram</a></li>
            <li class="breadcrumb-item active text-teal">Vertex ile Planla</li>
        </ol>
    </nav>

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-calendar2-range text-teal me-2"></i>Vertex ile Planla</h1>
            <p class="page-subtitle mb-0">Vertex galerisindeki görselleri kullanarak Instagram paylaşımlarını otomatik planla.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn-glass" data-bs-toggle="collapse" data-bs-target="#vtxHowTo" aria-expanded="false">
                <i class="bi bi-question-circle me-1"></i> Nasıl Çalışır?
            </button>
            <a href="{{ route('admin.instagram-posts.index') }}" class="btn-glass">
                <i class="bi bi-arrow-left me-1"></i> Gönderilere Dön
            </a>
        </div>
    </div>

    {{-- Nasıl Çalışır Rehberi --}}
    <div class="collapse mb-4" id="vtxHowTo" data-aos="fade-down">
        <div class="card-dark">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-green"><i class="bi bi-book"></i></div>
                    <div><h6 class="mb-0">Vertex ile Aylık Plan Oluşturma Rehberi</h6></div>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h6 class="text-teal mb-3"><i class="bi bi-1-circle me-2"></i>Hazırlık: Görselleri Üret</h6>
                        <ol class="text-clr-secondary mb-0 ps-3">
                            <li class="mb-2">
                                <strong>Admin &rarr; Vertex &rarr; Şablonlar</strong>'a git.
                                Prompt oluştur (ör: "Çiftlik Ürün Fotoğrafı"). Tag ekle (ör: <code>peynir, süt</code>).
                            </li>
                            <li class="mb-2">
                                <strong>Instagram İçerik Şablonları</strong> bölümüne caption ve hashtag template'lerini yaz.
                                Değişkenler kullanabilirsin: <code>@{{ozel_gun_adi}}</code>, <code>@{{urun_adi}}</code> vb.
                            </li>
                            <li class="mb-2">
                                <strong>Admin &rarr; Vertex</strong>'e dön. Prompt'u seç, adet gir (ör: 60 adet), <strong>"Üret"</strong>.
                                Cron arka planda görselleri üretir.
                            </li>
                            <li class="mb-2">
                                <strong>Feed (1:1 veya 4:5)</strong> ve <strong>Story (9:16)</strong> için ayrı prompt oluşturman önerilir.
                                Her birinden en az planladığın gün kadar görsel üret.
                            </li>
                        </ol>
                    </div>
                    <div class="col-lg-6">
                        <h6 class="text-teal mb-3"><i class="bi bi-2-circle me-2"></i>Planlama: Bu Sayfayı Kullan</h6>
                        <ol class="text-clr-secondary mb-0 ps-3">
                            <li class="mb-2">
                                <strong>Başlangıç/Bitiş tarihi</strong> seç (ör: bugünden 60 gün sonrasına).
                            </li>
                            <li class="mb-2">
                                <strong>Feed ve/veya Story</strong> tikle. Her biri günde 1 paylaşım oluşturur.
                            </li>
                            <li class="mb-2">
                                <strong>Saat</strong> belirle. Feed ve Story için farklı saatler ayarlanabilir.
                            </li>
                            <li class="mb-2">
                                <strong>Şablon filtresi</strong>: Belirli prompt şablonlarına ait görselleri mi kullanmak istiyorsun? Şablonları çoklu seç.
                                Hiçbirini seçmezsen tüm Vertex galerisinden rastgele (en az kullanılmış öncelikli) seçer.
                            </li>
                            <li class="mb-2">
                                <strong>"Önizle"</strong> tıkla &rarr; Tablo ile planı gör: tarih, görsel, caption, kullanım sayısı.
                            </li>
                            <li class="mb-2">
                                <strong>"Onayla ve Planla"</strong> &rarr; Gönderiler <span class="status-badge info">Planlanmış</span> olarak kaydedilir.
                                Zamanı geldiğinde cron otomatik paylaşır.
                            </li>
                        </ol>
                    </div>
                </div>

                <hr class="my-3 border-secondary">

                <div class="row g-4">
                    <div class="col-lg-6">
                        <h6 class="text-clr-secondary mb-2"><i class="bi bi-lightbulb text-warning me-2"></i>İpuçları</h6>
                        <ul class="text-clr-secondary mb-0 ps-3 small">
                            <li class="mb-1">Sistem <strong>en az kullanılmış</strong> görseli öncelikli seçer &mdash; tekrar minimumda kalır.</li>
                            <li class="mb-1">Tüm görseller en az 1 kez kullanıldığında, en düşük kullanımlı tekrar döner.</li>
                            <li class="mb-1">Caption/hashtag görselle birlikte kayıtlıysa otomatik gelir. Yoksa boş kalır (panelden düzenlersin).</li>
                            <li class="mb-1">Aynı tarih+saat+tip'te zaten planlanmış gönderi varsa o gün atlanır (çakışma koruması).</li>
                        </ul>
                    </div>
                    <div class="col-lg-6">
                        <h6 class="text-clr-secondary mb-2"><i class="bi bi-diagram-3 text-info me-2"></i>Alternatif Yöntemler</h6>
                        <ul class="text-clr-secondary mb-0 ps-3 small">
                            <li class="mb-1"><strong>Excel ile Toplu Plan:</strong> <a href="{{ route('admin.instagram-posts.bulk.form') }}" class="text-teal">Toplu Plan</a> sayfasından <code>gorsel_kaynagi=vertex</code> ile detaylı kontrol.</li>
                            <li class="mb-1"><strong>Özel Gün Görselleri:</strong> <a href="{{ route('admin.vertex.special-days.index') }}" class="text-teal">Vertex &rarr; Özel Günler</a>'den tarih bazlı otomatik üretim.</li>
                            <li class="mb-1"><strong>Tek Gönderi:</strong> <a href="{{ route('admin.instagram-posts.create') }}" class="text-teal">Yeni Gönderi</a>'de "Vertex'ten Seç" butonu ile manuel seçim.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="row g-3 mb-4" data-aos="fade-up">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-card-icon bg-icon-blue"><i class="bi bi-image"></i></div>
                <div class="stat-card-content">
                    <span class="stat-card-value">{{ number_format($stats['feed_available']) }}</span>
                    <span class="stat-card-label">Feed Görseli (1:1, 4:5)</span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-card-icon bg-icon-purple"><i class="bi bi-phone"></i></div>
                <div class="stat-card-content">
                    <span class="stat-card-value">{{ number_format($stats['story_available']) }}</span>
                    <span class="stat-card-label">Story Görseli (9:16)</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Plan Formu --}}
    <div class="card-dark mb-4" data-aos="fade-up">
        <div class="card-header-custom">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-teal"><i class="bi bi-sliders2"></i></div>
                <div><h6 class="mb-0">Plan Ayarları</h6></div>
            </div>
        </div>
        <div class="card-body-custom">
            <form id="vtxPlanForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="vtxFrom" class="form-label text-clr-secondary">Başlangıç Tarihi</label>
                        <input type="date" class="form-control form-control-theme" id="vtxFrom" name="from"
                               value="{{ now()->format('Y-m-d') }}" min="{{ now()->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="vtxTo" class="form-label text-clr-secondary">Bitiş Tarihi</label>
                        <input type="date" class="form-control form-control-theme" id="vtxTo" name="to"
                               value="{{ now()->addDays(29)->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="vtxFeedTime" class="form-label text-clr-secondary">Feed Saati</label>
                        <input type="time" class="form-control form-control-theme" id="vtxFeedTime" name="feed_time" value="09:00">
                    </div>
                    <div class="col-md-3">
                        <label for="vtxStoryTime" class="form-label text-clr-secondary">Story Saati</label>
                        <input type="time" class="form-control form-control-theme" id="vtxStoryTime" name="story_time" value="18:00">
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label class="form-label text-clr-secondary">Gönderi Tipi</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="vtxIncFeed" name="include_feed" checked>
                                <label class="form-check-label text-clr-secondary" for="vtxIncFeed">
                                    <i class="bi bi-image text-info me-1"></i> Feed
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="vtxIncStory" name="include_story" checked>
                                <label class="form-check-label text-clr-secondary" for="vtxIncStory">
                                    <i class="bi bi-phone text-purple me-1"></i> Story
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-clr-secondary">Şablon Filtresi</label>
                        <div class="vtx-prompt-dropdown" id="vtxPromptDropdown">
                            <button type="button" class="vtx-prompt-toggle form-control form-control-theme" id="vtxPromptToggle">
                                <span class="vtx-prompt-toggle-text">Tümü (tüm şablonlar)</span>
                                <i class="bi bi-chevron-down vtx-prompt-toggle-icon"></i>
                            </button>
                            <div class="vtx-prompt-menu" id="vtxPromptMenu">
                                @foreach($prompts as $prompt)
                                    <label class="vtx-prompt-option">
                                        <input type="checkbox" class="form-check-input vtx-prompt-cb" value="{{ $prompt->id }}" data-name="{{ $prompt->name }}">
                                        <span class="vtx-prompt-option-name">{{ $prompt->name }}</span>
                                        <span class="vtx-prompt-option-count">{{ $prompt->generations_count }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn-teal w-100" id="vtxPreviewBtn">
                            <i class="bi bi-eye me-1"></i> Önizle
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Warnings --}}
    <div id="vtxWarnings" class="d-none mb-3"></div>

    {{-- Preview --}}
    <div id="vtxPreviewSection" class="d-none" data-aos="fade-up">
        <div class="card-dark mb-4">
            <div class="card-header-custom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-green"><i class="bi bi-list-check"></i></div>
                    <div>
                        <h6 class="mb-0">Önizleme</h6>
                        <small class="text-muted" id="vtxPreviewSummary"></small>
                    </div>
                </div>
                <button type="button" class="btn-teal" id="vtxConfirmBtn">
                    <i class="bi bi-check-lg me-1"></i> Onayla ve Planla
                </button>
            </div>
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="table-dark-custom mb-0">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Saat</th>
                                <th>Tip</th>
                                <th>Görsel</th>
                                <th>Caption</th>
                                <th>Kullanım</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="vtxPreviewBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Loading --}}
    <div id="vtxLoading" class="d-none text-center py-5">
        <div class="spinner-border text-teal" role="status"></div>
        <p class="text-clr-secondary mt-2">Plan oluşturuluyor...</p>
    </div>

    {{-- Image Picker Modal --}}
    <div class="vtx-picker-overlay" id="vtxPickerOverlay">
        <div class="vtx-picker-container">
            <div class="vtx-picker-header">
                <h6 class="mb-0"><i class="bi bi-images text-teal me-2"></i>Görsel Seç</h6>
                <button type="button" class="vtx-detail-close" id="vtxPickerClose"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="vtx-picker-body">
                <div class="vtx-picker-grid" id="vtxPickerGrid"></div>
                <div class="vtx-picker-loading d-none" id="vtxPickerLoading">
                    <div class="spinner-border spinner-border-sm text-teal"></div>
                </div>
                <div class="vtx-picker-empty d-none" id="vtxPickerEmpty">
                    <i class="bi bi-inbox"></i>
                    <p>Görsel bulunamadı.</p>
                </div>
            </div>
            <div class="vtx-picker-footer" id="vtxPickerFooter">
                <button type="button" class="btn-glass btn-sm" id="vtxPickerLoadMore">
                    <i class="bi bi-arrow-down-circle me-1"></i>Daha Fazla
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/mlib-lightbox.js') }}" defer></script>
<script>
(function () {
    'use strict';

    var form = document.getElementById('vtxPlanForm');
    var previewBtn = document.getElementById('vtxPreviewBtn');
    var confirmBtn = document.getElementById('vtxConfirmBtn');
    var previewSection = document.getElementById('vtxPreviewSection');
    var previewBody = document.getElementById('vtxPreviewBody');
    var previewSummary = document.getElementById('vtxPreviewSummary');
    var warningsDiv = document.getElementById('vtxWarnings');
    var loadingDiv = document.getElementById('vtxLoading');
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    var currentPlan = null;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadPreview();
    });

    confirmBtn.addEventListener('click', function () {
        if (!currentPlan || currentPlan.length === 0) return;
        confirmPlan();
    });

    function loadPreview() {
        previewSection.classList.add('d-none');
        warningsDiv.classList.add('d-none');
        loadingDiv.classList.remove('d-none');
        previewBtn.disabled = true;

        var selectedIds = [];
        document.querySelectorAll('.vtx-prompt-cb:checked').forEach(function(cb) {
            selectedIds.push(parseInt(cb.value, 10));
        });

        var body = {
            from: document.getElementById('vtxFrom').value,
            to: document.getElementById('vtxTo').value,
            include_feed: document.getElementById('vtxIncFeed').checked ? 1 : 0,
            include_story: document.getElementById('vtxIncStory').checked ? 1 : 0,
            feed_time: document.getElementById('vtxFeedTime').value,
            story_time: document.getElementById('vtxStoryTime').value,
            prompt_ids: selectedIds.length > 0 ? selectedIds : null
        };

        fetch('{{ route("admin.instagram-posts.vertex-plan.preview") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(body)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            loadingDiv.classList.add('d-none');
            previewBtn.disabled = false;

            if (!data.success) {
                showWarning(data.message || 'Bir hata oluştu.', 'danger');
                return;
            }

            currentPlan = data.posts;
            renderPreview(data);
        })
        .catch(function (err) {
            loadingDiv.classList.add('d-none');
            previewBtn.disabled = false;
            showWarning('Bağlantı hatası.', 'danger');
        });
    }

    function renderPreview(data) {
        previewBody.innerHTML = '';
        previewSummary.textContent = data.feed_count + ' feed + ' + data.story_count + ' story = ' + data.total + ' gönderi';

        if (data.warnings && data.warnings.length > 0) {
            var html = '';
            data.warnings.forEach(function (w) {
                html += '<div class="alert alert-warning d-flex align-items-center gap-2 mb-2"><i class="bi bi-exclamation-triangle"></i> ' + w + '</div>';
            });
            warningsDiv.innerHTML = html;
            warningsDiv.classList.remove('d-none');
        }

        var prevDate = '';
        data.posts.forEach(function (post, idx) {
            var tr = document.createElement('tr');

            var captionShort = (post.ig_caption || '').substring(0, 80);
            if ((post.ig_caption || '').length > 80) captionShort += '…';

            var isImage = post.media_type === 'image';
            var typeBadge = isImage
                ? '<span class="status-badge info"><i class="bi bi-image me-1"></i>Feed</span>'
                : '<span class="status-badge pending"><i class="bi bi-phone me-1"></i>Story</span>';

            var thumbHtml = '<span class="text-clr-muted">—</span>';
            if (post.thumb_url && post.full_url) {
                thumbHtml =
                    '<a href="' + post.full_url + '" data-mlib-lightbox data-caption="' + post.date + ' ' + post.day_name + ' — ' + (isImage ? 'Feed' : 'Story') + '">' +
                        '<img src="' + post.thumb_url + '" class="vtx-plan-thumb" width="56" height="56" loading="lazy" alt="' + post.date + '">' +
                    '</a>';
            }

            var usageBadge = post.usage_count === 0
                ? '<span class="status-badge active"><i class="bi bi-star-fill"></i> Yeni</span>'
                : '<span class="status-badge pending"><i class="bi bi-arrow-repeat"></i> ' + post.usage_count + 'x</span>';

            var showDateDivider = post.date !== prevDate;
            prevDate = post.date;

            if (showDateDivider && idx > 0) {
                tr.classList.add('vtx-plan-date-divider');
            }

            var changeBtn = '<button type="button" class="vtx-plan-change-btn" data-plan-idx="' + idx + '" title="Görseli Değiştir"><i class="bi bi-arrow-repeat me-1"></i>Değiştir</button>';

            tr.dataset.planIdx = idx;
            tr.innerHTML =
                '<td class="text-nowrap">' +
                    '<div class="fw-medium text-clr-white">' + post.date + '</div>' +
                    '<small class="text-clr-muted">' + post.day_name + '</small>' +
                '</td>' +
                '<td class="text-nowrap">' +
                    '<i class="bi bi-clock text-clr-muted me-1"></i>' + post.time +
                '</td>' +
                '<td>' + typeBadge + '</td>' +
                '<td>' + thumbHtml + '</td>' +
                '<td>' +
                    (captionShort
                        ? '<div class="text-clr-secondary vtx-plan-caption">' + captionShort + '</div>'
                        : '<em class="text-clr-muted">Caption yok</em>') +
                '</td>' +
                '<td>' + usageBadge + '</td>' +
                '<td>' + changeBtn + '</td>';

            previewBody.appendChild(tr);
        });

        previewSection.classList.remove('d-none');

        if (window.mlibLightbox) {
            mlibLightbox.refresh();
        }
    }

    function confirmPlan() {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Planlanıyor...';

        fetch('{{ route("admin.instagram-posts.vertex-plan.confirm") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ posts: currentPlan })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Onayla ve Planla';

            if (data.success) {
                if (window.AdminModal && AdminModal.status) {
                    AdminModal.status({
                        title: 'Plan Oluşturuldu',
                        message: data.message,
                        type: 'success'
                    });
                }
                previewSection.classList.add('d-none');
                currentPlan = null;
            } else {
                showWarning(data.message || 'Hata oluştu.', 'danger');
            }
        })
        .catch(function () {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Onayla ve Planla';
            showWarning('Bağlantı hatası.', 'danger');
        });
    }

    function showWarning(msg, type) {
        warningsDiv.innerHTML = '<div class="alert alert-' + type + ' d-flex align-items-center gap-2"><i class="bi bi-exclamation-triangle"></i> ' + msg + '</div>';
        warningsDiv.classList.remove('d-none');
    }

    // Prompt multi-select dropdown
    var promptDropdown = document.getElementById('vtxPromptDropdown');
    var promptToggle = document.getElementById('vtxPromptToggle');
    var promptMenu = document.getElementById('vtxPromptMenu');
    var promptToggleText = promptToggle.querySelector('.vtx-prompt-toggle-text');

    promptToggle.addEventListener('click', function() {
        promptDropdown.classList.toggle('vtx-prompt-open');
    });

    document.addEventListener('click', function(e) {
        if (!promptDropdown.contains(e.target)) {
            promptDropdown.classList.remove('vtx-prompt-open');
        }
    });

    promptMenu.addEventListener('change', function() {
        var checked = promptMenu.querySelectorAll('.vtx-prompt-cb:checked');
        if (checked.length === 0) {
            promptToggleText.textContent = 'Tümü (tüm şablonlar)';
        } else if (checked.length === 1) {
            promptToggleText.textContent = checked[0].dataset.name;
        } else {
            promptToggleText.textContent = checked.length + ' şablon seçili';
        }
    });

    // ─── Image Picker ───
    var pickerOverlay = document.getElementById('vtxPickerOverlay');
    var pickerGrid = document.getElementById('vtxPickerGrid');
    var pickerLoading = document.getElementById('vtxPickerLoading');
    var pickerEmpty = document.getElementById('vtxPickerEmpty');
    var pickerFooter = document.getElementById('vtxPickerFooter');
    var pickerLoadMore = document.getElementById('vtxPickerLoadMore');
    var pickerPage = 1;
    var pickerLastPage = 1;
    var pickerAspect = '1:1';
    var pickerPlanIdx = -1;

    previewBody.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-plan-idx]');
        if (!btn || !btn.classList.contains('vtx-plan-change-btn')) return;

        pickerPlanIdx = parseInt(btn.dataset.planIdx, 10);
        var post = currentPlan[pickerPlanIdx];
        if (!post) return;

        pickerAspect = post.media_type === 'image' ? '1:1,4:5' : '9:16';
        pickerPage = 1;
        pickerGrid.innerHTML = '';
        pickerOverlay.classList.add('vtx-picker-open');
        document.body.classList.add('overflow-hidden');
        loadPickerPage();
    });

    document.getElementById('vtxPickerClose').addEventListener('click', closePicker);
    pickerOverlay.addEventListener('click', function(e) {
        if (e.target === pickerOverlay) closePicker();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && pickerOverlay.classList.contains('vtx-picker-open')) closePicker();
    });

    function closePicker() {
        pickerOverlay.classList.remove('vtx-picker-open');
        document.body.classList.remove('overflow-hidden');
        pickerPlanIdx = -1;
    }

    pickerLoadMore.addEventListener('click', function() {
        if (pickerPage < pickerLastPage) {
            pickerPage++;
            loadPickerPage();
        }
    });

    function loadPickerPage() {
        pickerLoading.classList.remove('d-none');
        pickerEmpty.classList.add('d-none');

        var url = '{{ route("admin.vertex.gallery-api") }}?aspect_ratio=' + encodeURIComponent(pickerAspect) + '&page=' + pickerPage;

        fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            pickerLoading.classList.add('d-none');
            pickerLastPage = data.last_page || 1;

            if (data.items.length === 0 && pickerPage === 1) {
                pickerEmpty.classList.remove('d-none');
                pickerFooter.classList.add('d-none');
                return;
            }

            data.items.forEach(function(item) {
                var card = document.createElement('div');
                card.className = 'vtx-picker-card';
                card.dataset.genId = item.id;

                var captionPreview = item.ig_caption ? item.ig_caption.substring(0, 60) : '';
                if (captionPreview.length >= 60) captionPreview += '…';

                card.innerHTML =
                    '<img src="' + item.thumb_url + '" class="vtx-picker-card-img" loading="lazy" alt="">' +
                    '<div class="vtx-picker-card-info">' +
                        (item.ig_title ? '<div class="vtx-picker-card-title">' + escHtml(item.ig_title) + '</div>' : '') +
                        (captionPreview ? '<div class="vtx-picker-card-caption">' + escHtml(captionPreview) + '</div>' : '<div class="vtx-picker-card-caption text-clr-muted">Caption yok</div>') +
                        '<div class="vtx-picker-card-meta">' + escHtml(item.prompt_name || '') + '</div>' +
                    '</div>';

                card.addEventListener('click', function() {
                    selectPickerImage(item);
                });

                pickerGrid.appendChild(card);
            });

            pickerFooter.classList.toggle('d-none', pickerPage >= pickerLastPage);
        })
        .catch(function() {
            pickerLoading.classList.add('d-none');
        });
    }

    function selectPickerImage(item) {
        if (pickerPlanIdx < 0 || !currentPlan[pickerPlanIdx]) return;

        var post = currentPlan[pickerPlanIdx];
        post.vertex_generation_id = item.id;
        post.image_path = item.image_path;
        post.thumb_url = item.thumb_url;
        post.full_url = item.full_url || item.image_url;
        post.ig_caption = item.ig_caption || '';
        post.ig_title = item.ig_title || '';
        post.ig_hashtags = item.ig_hashtags || '';
        post.prompt_name = item.prompt_name;
        post.usage_count = item.usage_count || 0;

        updatePlanRow(pickerPlanIdx, post);
        closePicker();
    }

    function updatePlanRow(idx, post) {
        var tr = previewBody.querySelector('tr[data-plan-idx="' + idx + '"]');
        if (!tr) return;

        var isImage = post.media_type === 'image';
        var captionShort = (post.ig_caption || '').substring(0, 80);
        if ((post.ig_caption || '').length > 80) captionShort += '…';

        var thumbTd = tr.querySelectorAll('td')[3];
        if (thumbTd && post.thumb_url) {
            thumbTd.innerHTML =
                '<a href="' + post.full_url + '" data-mlib-lightbox data-caption="' + post.date + '">' +
                    '<img src="' + post.thumb_url + '" class="vtx-plan-thumb" width="56" height="56" loading="lazy" alt="">' +
                '</a>';
        }

        var captionTd = tr.querySelectorAll('td')[4];
        if (captionTd) {
            captionTd.innerHTML = captionShort
                ? '<div class="text-clr-secondary vtx-plan-caption">' + escHtml(captionShort) + '</div>'
                : '<em class="text-clr-muted">Caption yok</em>';
        }

        var usageTd = tr.querySelectorAll('td')[5];
        if (usageTd) {
            usageTd.innerHTML = post.usage_count === 0
                ? '<span class="status-badge active"><i class="bi bi-star-fill"></i> Yeni</span>'
                : '<span class="status-badge pending"><i class="bi bi-arrow-repeat"></i> ' + post.usage_count + 'x</span>';
        }

        if (window.mlibLightbox) mlibLightbox.refresh();
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
})();
</script>
@endpush
