@extends('layouts.admin')

@section('title', 'Vertex Görsel Üret')
@section('page_title', 'Vertex Görsel Üret')
@section('page_description', 'Google Vertex AI ile şablondan görsel üret.')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Vertex Görsel Üret</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-stars text-teal me-2"></i>Vertex Görsel Üret</h1>
            <p class="page-subtitle">Şablonunu seç, "Üret" bas — Google Vertex AI saniyeler içinde çizsin.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.vertex.special-days.index') }}" class="btn-glass">
                <i class="bi bi-calendar-heart"></i> Özel Gün Görselleri
            </a>
            <a href="{{ route('admin.vertex.prompts.index') }}" class="btn-glass">
                <i class="bi bi-collection-fill"></i> Şablonlar ({{ $stats['prompts'] }})
            </a>
            <a href="{{ route('admin.vertex.generations.index') }}" class="btn-glass">
                <i class="bi bi-clock-history"></i> Geçmiş
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
    @endif

    @if(! $apiKeySet)
        <div class="alert alert-danger d-flex align-items-center gap-3" role="alert">
            <i class="bi bi-key-fill fs-4"></i>
            <div class="flex-grow-1">
                <strong>Vertex API Key eksik</strong>
                <div class="small">
                    <a href="{{ route('admin.settings.index') }}#stg-ai" class="alert-link">Admin → Ayarlar → AI → Google Vertex AI</a>
                    bölümünden API key'i ekleyin. Mevcut Gemini key'leriyle bu modül bağlantılı değildir.
                </div>
            </div>
        </div>
    @endif

    {{-- STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-images"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Üretim</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Başarılı</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['completed'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Başarısız</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['failed'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-collection-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Aktif Şablon</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['prompts'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- SYSTEM STATUS --}}
    <div class="card-dark mb-4" data-aos="fade-up" id="vtxSystemStatus">
        <div class="card-header-custom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-teal"><i class="bi bi-activity"></i></div>
                <div>
                    <h6 class="mb-0">Üretim Durumu</h6>
                    <small class="text-muted">Canlı sistem takibi</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="vtx-sys-pulse" id="vtxSysPulse"></span>
                <span class="vtx-sys-status-label" id="vtxSysLabel">Yükleniyor…</span>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="vtx-sys-grid">
                {{-- Cron durumu --}}
                <div class="vtx-sys-cell">
                    <div class="vtx-sys-cell-icon vtx-sys-icon-blue"><i class="bi bi-cpu"></i></div>
                    <div class="vtx-sys-cell-body">
                        <span class="vtx-sys-cell-label">Cron Durumu</span>
                        <span class="vtx-sys-cell-value" id="vtxCronStatus">—</span>
                        <small class="vtx-sys-cell-sub" id="vtxCronLastRun"></small>
                    </div>
                </div>
                {{-- Kuyruk --}}
                <div class="vtx-sys-cell">
                    <div class="vtx-sys-cell-icon vtx-sys-icon-orange"><i class="bi bi-hourglass-split"></i></div>
                    <div class="vtx-sys-cell-body">
                        <span class="vtx-sys-cell-label">Kuyrukta Bekleyen</span>
                        <span class="vtx-sys-cell-value" id="vtxQueueCount">—</span>
                        <small class="vtx-sys-cell-sub" id="vtxQueueEta"></small>
                    </div>
                </div>
                {{-- Aktif batch --}}
                <div class="vtx-sys-cell">
                    <div class="vtx-sys-cell-icon vtx-sys-icon-green"><i class="bi bi-lightning-charge-fill"></i></div>
                    <div class="vtx-sys-cell-body">
                        <span class="vtx-sys-cell-label">Aktif Batch</span>
                        <span class="vtx-sys-cell-value" id="vtxActiveBatch">—</span>
                        <small class="vtx-sys-cell-sub" id="vtxActiveBatchSub"></small>
                    </div>
                </div>
                {{-- Bugün --}}
                <div class="vtx-sys-cell">
                    <div class="vtx-sys-cell-icon vtx-sys-icon-purple"><i class="bi bi-calendar-check"></i></div>
                    <div class="vtx-sys-cell-body">
                        <span class="vtx-sys-cell-label">Bugün</span>
                        <span class="vtx-sys-cell-value" id="vtxTodayCount">—</span>
                        <small class="vtx-sys-cell-sub" id="vtxTodaySub"></small>
                    </div>
                </div>
            </div>
            {{-- Aktif batch progress --}}
            <div class="vtx-sys-progress-wrap d-none" id="vtxSysProgressWrap">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-clr-secondary" id="vtxSysProgressLabel">Batch #—</small>
                    <small class="text-teal fw-semibold" id="vtxSysProgressPct">%0</small>
                </div>
                <div class="vtx-progress">
                    <div class="vtx-progress-bar" id="vtxSysProgressBar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="text-clr-muted" id="vtxSysProgressDetail"></small>
                    <small class="text-clr-muted" id="vtxSysProgressDuration"></small>
                </div>
            </div>
            {{-- Rate limit uyarısı --}}
            <div class="vtx-sys-alert d-none" id="vtxSysAlert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span id="vtxSysAlertText"></span>
            </div>
        </div>
    </div>

    {{-- ACTIVE BATCHES --}}
    @if($activeBatches->isNotEmpty())
        <div class="card-dark mb-4" data-aos="fade-up">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-orange"><i class="bi bi-lightning-charge-fill"></i></div>
                    <div>
                        <h6 class="mb-0">Aktif Batch Üretimler</h6>
                        <small class="text-muted">Cron tarafından sırayla işleniyor</small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">
                @foreach($activeBatches as $ab)
                    <div class="vtx-batch-card vtx-batch-active" data-batch-id="{{ $ab->id }}" data-status-url="{{ route('admin.vertex.batch-status', $ab->id) }}">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-bold text-clr-primary">Batch #{{ $ab->id }}</span>
                                <span class="badge vtx-status-processing">{{ ucfirst($ab->status) }}</span>
                                @if($ab->prompt)
                                    <span class="badge bg-teal-soft">{{ $ab->prompt->name }}</span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.vertex.batch-cancel', $ab->id) }}" class="d-inline vtx-batch-cancel-form">
                                @csrf
                                <button type="submit" class="vtx-cancel-btn vtx-batch-cancel-btn">
                                    <i class="bi bi-x-circle"></i>İptal Et
                                </button>
                            </form>
                        </div>
                        <div class="vtx-progress mb-2">
                            <div class="vtx-progress-bar vtx-batch-progress" role="progressbar"
                                 aria-valuenow="{{ $ab->progress() }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-3">
                                <small class="text-clr-secondary">
                                    <i class="bi bi-check-circle text-success me-1"></i><span class="vtx-batch-completed">{{ $ab->completed_count }}</span>/<span class="vtx-batch-total">{{ $ab->total_count }}</span>
                                </small>
                                <small class="text-clr-secondary">
                                    <i class="bi bi-x-circle text-danger me-1"></i><span class="vtx-batch-failed">{{ $ab->failed_count }}</span> hatalı
                                </small>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <small class="text-teal fw-semibold vtx-batch-percent">%{{ number_format($ab->progress(), 0) }}</small>
                                <small class="text-clr-muted vtx-batch-duration"><i class="bi bi-stopwatch me-1"></i>{{ $ab->formattedDuration() }}</small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- GENERATION PANEL --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-7" data-aos="fade-up">
            <div class="card-dark h-100">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-teal"><i class="bi bi-brush"></i></div>
                        <div>
                            <h6 class="mb-0">Görsel Üret</h6>
                            <small class="text-muted">Şablon seç, adet belirle, üret</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    @if($prompts->isEmpty())
                        <div class="text-center py-5">
                            <i class="bi bi-inbox vtx-empty-icon"></i>
                            <p class="text-clr-muted mt-3 mb-2">Henüz şablon eklenmedi.</p>
                            <a href="{{ route('admin.vertex.prompts.create') }}" class="btn-teal">
                                <i class="bi bi-plus-lg me-1"></i> İlk Şablonu Ekle
                            </a>
                        </div>
                    @else
                        <form id="vertexGenerateForm">
                            @csrf

                            {{-- Hızlı Erişim (Pinned) --}}
                            @php $pinnedPrompts = $prompts->where('is_pinned', true); @endphp
                            @if($pinnedPrompts->isNotEmpty())
                                <div class="vtx-pinned-bar mb-4">
                                    @foreach($pinnedPrompts as $pin)
                                        <button type="button" class="vtx-pinned-btn" data-select-prompt="{{ $pin->id }}">
                                            <i class="bi bi-pin-fill me-1"></i>{{ $pin->name }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Şablon seçimi --}}
                            <div class="mb-4">
                                <label for="vertexPromptId" class="form-label text-clr-secondary">
                                    <i class="bi bi-collection text-teal me-1"></i>Şablon <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="vertexPromptId" name="prompt_id" required>
                                    <option value="">— Şablon seçin —</option>
                                    @php $unpinnedPrompts = $prompts->where('is_pinned', false); @endphp
                                    @if($pinnedPrompts->isNotEmpty())
                                        <optgroup label="★ Sabitlenmiş">
                                            @foreach($pinnedPrompts as $tpl)
                                                @php
                                                    $refImages = is_array($tpl->reference_images) ? $tpl->reference_images : [];
                                                    $refImagesJson = [];
                                                    foreach ($refImages as $img) {
                                                        if (! empty($img['path'])) {
                                                            $refImagesJson[] = [
                                                                'url'  => upload_url($img['path']),
                                                                'name' => basename($img['path']),
                                                                'mime' => $img['mime'] ?? 'image/png',
                                                            ];
                                                        }
                                                    }
                                                @endphp
                                                <option value="{{ $tpl->id }}"
                                                        data-prompt="{{ $tpl->prompt }}"
                                                        data-model="{{ $tpl->model }}"
                                                        data-aspect-ratio="{{ $tpl->aspect_ratio }}"
                                                        data-image-size="{{ $tpl->image_size }}"
                                                        data-mime-type="{{ $tpl->mime_type }}"
                                                        data-ref-images='@json($refImagesJson)'
                                                        data-random-options='@json(is_array($tpl->random_options) ? $tpl->random_options : new \stdClass())'>
                                                    {{ $tpl->name }}@if($refImagesJson !== []) 🖼️ ({{ count($refImagesJson) }}) @endif
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if($unpinnedPrompts->isNotEmpty())
                                        @if($pinnedPrompts->isNotEmpty())
                                            <optgroup label="Diğer Şablonlar">
                                        @endif
                                        @foreach($unpinnedPrompts as $tpl)
                                            @php
                                                $refImages = is_array($tpl->reference_images) ? $tpl->reference_images : [];
                                                $refImagesJson = [];
                                                foreach ($refImages as $img) {
                                                    if (! empty($img['path'])) {
                                                        $refImagesJson[] = [
                                                            'url'  => upload_url($img['path']),
                                                            'name' => basename($img['path']),
                                                            'mime' => $img['mime'] ?? 'image/png',
                                                        ];
                                                    }
                                                }
                                            @endphp
                                            <option value="{{ $tpl->id }}"
                                                    data-prompt="{{ $tpl->prompt }}"
                                                    data-model="{{ $tpl->model }}"
                                                    data-aspect-ratio="{{ $tpl->aspect_ratio }}"
                                                    data-image-size="{{ $tpl->image_size }}"
                                                    data-mime-type="{{ $tpl->mime_type }}"
                                                    data-ref-images='@json($refImagesJson)'
                                                    data-random-options='@json(is_array($tpl->random_options) ? $tpl->random_options : new \stdClass())'>
                                                {{ $tpl->name }}@if($refImagesJson !== []) 🖼️ ({{ count($refImagesJson) }}) @endif
                                            </option>
                                        @endforeach
                                        @if($pinnedPrompts->isNotEmpty())
                                            </optgroup>
                                        @endif
                                    @endif
                                </select>
                            </div>

                            {{-- Selected template details (filled by JS) --}}
                            <div class="d-none" id="vertexTemplateDetails">

                                {{-- Meta chips --}}
                                <div class="vtx-meta-grid mb-4">
                                    <div class="vtx-meta-chip">
                                        <span class="vtx-meta-chip-label">Model</span>
                                        <span class="vtx-meta-chip-value" id="vtxModel">—</span>
                                    </div>
                                    <div class="vtx-meta-chip">
                                        <span class="vtx-meta-chip-label">Oran</span>
                                        <span class="vtx-meta-chip-value" id="vtxAspectRatio">—</span>
                                    </div>
                                    <div class="vtx-meta-chip">
                                        <span class="vtx-meta-chip-label">Boyut</span>
                                        <span class="vtx-meta-chip-value" id="vtxImageSize">—</span>
                                    </div>
                                    <div class="vtx-meta-chip">
                                        <span class="vtx-meta-chip-label">Format</span>
                                        <span class="vtx-meta-chip-value" id="vtxMimeType">—</span>
                                    </div>
                                </div>

                                {{-- Referans görseller --}}
                                <div class="mb-4 d-none" id="vtxRefImagesWrap">
                                    <label class="form-label text-clr-secondary mb-2">
                                        <i class="bi bi-images text-info me-1"></i>
                                        Referans Görseller <small class="text-clr-muted">(image-to-image — <span id="vtxRefImagesCount">0</span> görsel)</small>
                                    </label>
                                    <div class="row g-2" id="vtxRefImagesGrid"></div>
                                </div>

                                {{-- Değişkenler --}}
                                <div class="mb-4 d-none" id="vtxVariablesWrap">
                                    <label class="form-label text-clr-secondary mb-2">
                                        <i class="bi bi-braces text-warning me-1"></i>
                                        Değişkenler <small class="text-clr-muted">(<span id="vtxVariablesCount">0</span>)</small>
                                    </label>
                                    <div id="vtxVariablesGrid" class="d-flex flex-column gap-2"></div>
                                    <small class="form-text text-clr-secondary mt-2 d-block">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Şablon prompt'undaki <code>@{{degisken}}</code> alanları otomatik tespit edildi.
                                    </small>
                                </div>

                                {{-- Prompt önizleme --}}
                                <div class="mb-4">
                                    <details class="vtx-prompt-details">
                                        <summary class="form-label text-clr-secondary mb-1">
                                            <i class="bi bi-chevron-right me-1"></i>
                                            Final Prompt Önizleme
                                            <small class="text-clr-muted ms-1">(<span id="vtxPromptLen">0</span> karakter)</small>
                                        </summary>
                                        <pre class="ai-prompt-fulltext mt-2"><code id="vtxPromptPreview"></code></pre>
                                    </details>
                                </div>

                                {{-- Adet seçici --}}
                                <div class="mb-4" id="vtxCountWrap">
                                    <label class="form-label text-clr-secondary mb-2">
                                        <i class="bi bi-stack text-teal me-1"></i>Kaç adet üretilsin?
                                    </label>
                                    <div class="vtx-count-pills mb-2">
                                        <button type="button" class="vtx-count-pill active" data-count="1">1</button>
                                        <button type="button" class="vtx-count-pill" data-count="10">10</button>
                                        <button type="button" class="vtx-count-pill" data-count="20">20</button>
                                        <button type="button" class="vtx-count-pill" data-count="30">30</button>
                                        <button type="button" class="vtx-count-pill" data-count="40">40</button>
                                        <button type="button" class="vtx-count-pill" data-count="50">50</button>
                                        <button type="button" class="vtx-count-pill" data-count="100">100</button>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-clr-muted small">veya</span>
                                        <input type="number" id="vtxCountCustom" class="form-control form-control-sm vtx-count-custom"
                                               min="1" max="9999" placeholder="Özel sayı">
                                    </div>
                                    <input type="hidden" id="vtxCountValue" name="count" value="1">
                                </div>
                            </div>

                            <button type="submit" class="btn-teal w-100" id="vertexGenerateBtn"
                                    {{ ! $apiKeySet ? 'disabled' : '' }}>
                                <i class="bi bi-magic me-1"></i>
                                <span id="vertexGenerateBtnText">
                                    @if(! $apiKeySet) API Key Eksik @else Görseli Üret @endif
                                </span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Preview panel --}}
        <div class="col-lg-5" data-aos="fade-up" data-aos-delay="100">
            <div class="card-dark h-100">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-purple"><i class="bi bi-eye"></i></div>
                        <div>
                            <h6 class="mb-0">Önizleme</h6>
                            <small class="text-muted">Son üretilen görsel</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="ai-preview-container">
                        <div class="ai-preview-empty" id="vtxPreviewEmpty">
                            <i class="bi bi-image"></i>
                            <p class="text-clr-muted mb-0">Henüz görsel üretmedin</p>
                            <small class="text-clr-muted">Şablon seç ve "Üret" butonuna bas</small>
                        </div>

                        <div class="ai-preview-loading d-none" id="vtxPreviewLoading">
                            <div class="ai-spinner"></div>
                            <p class="text-clr-secondary mb-0 mt-3">Vertex AI çiziyor...</p>
                            <small class="text-clr-muted">Bu işlem 10-60 saniye sürebilir</small>
                            <small class="text-clr-muted d-block mt-1" id="vtxPollTick"></small>
                        </div>

                        <div class="ai-preview-result d-none" id="vtxPreviewResult">
                            <div class="ai-preview-image-wrap">
                                <img id="vtxPreviewImage" src="" alt="Vertex Generated">
                            </div>
                            <div class="ai-preview-meta mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Başarılı</span>
                                    <small class="text-clr-muted" id="vtxPreviewTime"></small>
                                </div>
                                <a id="vtxPreviewDownload" href="#" download class="btn-glass w-100 sm">
                                    <i class="bi bi-download me-1"></i> İndir
                                </a>
                            </div>
                        </div>

                        <div class="ai-preview-error d-none" id="vtxPreviewError">
                            <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                            <p class="text-danger mb-0 mt-2" id="vtxPreviewErrorText"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- BATCH HISTORY --}}
    @if($recentBatches->total() > 0)
        <div class="card-dark mb-4" data-aos="fade-up" id="batchHistory">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-blue"><i class="bi bi-list-check"></i></div>
                    <div>
                        <h6 class="mb-0">Batch Geçmişi</h6>
                        <small class="text-muted">Tüm toplu üretimler</small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="cl-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Şablon</th>
                                <th>Durum</th>
                                <th>İlerleme</th>
                                <th>Süre</th>
                                <th>Tarih</th>
                                <th class="text-center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentBatches as $rb)
                                <tr>
                                    <td class="fw-bold">{{ $rb->id }}</td>
                                    <td>{{ $rb->prompt?->name ?? '—' }}</td>
                                    <td>
                                        @php
                                            $statusClass = match($rb->status) {
                                                'completed' => 'vtx-status-completed',
                                                'processing' => 'vtx-status-processing',
                                                'pending' => 'vtx-status-pending',
                                                'partial_completed' => 'vtx-status-partial',
                                                'cancelled' => 'vtx-status-cancelled',
                                                default => 'bg-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $rb->status)) }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="vtx-progress vtx-progress-sm flex-grow-1">
                                                <div class="vtx-progress-bar" role="progressbar"
                                                     aria-valuenow="{{ $rb->progress() }}" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <small class="text-clr-muted text-nowrap">{{ $rb->completed_count }}/{{ $rb->total_count }}</small>
                                            @if($rb->failed_count > 0)
                                                <small class="text-danger text-nowrap">{{ $rb->failed_count }} hata</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-nowrap">{{ $rb->formattedDuration() }}</td>
                                    <td class="text-nowrap text-clr-muted">{{ $rb->created_at->diffForHumans() }}</td>
                                    <td class="text-center">
                                        @if($rb->failed_count > 0 && !$rb->isActive())
                                            <button type="button"
                                                    class="vtx-batch-retry-btn"
                                                    data-batch-id="{{ $rb->id }}"
                                                    data-failed-count="{{ $rb->failed_count }}"
                                                    title="{{ $rb->failed_count }} hatalı üretimi tekrar dene">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('partials.admin.pagination', ['paginator' => $recentBatches, 'itemLabel' => 'batch'])
            </div>
        </div>
    @endif

    {{-- RECENT GENERATIONS --}}
    @if($recentGenerations->isNotEmpty())
        <div class="card-dark mb-4" data-aos="fade-up">
            <div class="card-header-custom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-green"><i class="bi bi-clock-history"></i></div>
                    <div>
                        <h6 class="mb-0">Son Üretimler</h6>
                        <small class="text-muted">En yeni 12 görsel</small>
                    </div>
                </div>
                <a href="{{ route('admin.vertex.generations.index') }}" class="btn-glass sm">
                    Tümünü Gör <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body-custom">
                <div class="ai-gallery-grid">
                    @foreach($recentGenerations as $g)
                        <div class="ai-gallery-item status-{{ $g->status }}">
                            @if($g->isCompleted() && $g->output_image_path)
                                <a href="{{ upload_url($g->output_image_path, 'lg') }}"
                                   data-mlib-lightbox
                                   data-caption="{{ $g->prompt?->name ?? 'Vertex' }} · {{ $g->aspect_ratio }} · {{ $g->created_at->format('d.m.Y H:i') }}"
                                   class="ai-gallery-thumb">
                                    <img src="{{ upload_url($g->output_image_path, 'md') }}" alt="{{ $g->prompt?->name ?? 'Vertex Generated' }}" loading="lazy">
                                </a>
                            @elseif($g->isFailed())
                                <div class="ai-gallery-thumb ai-gallery-failed">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                </div>
                            @else
                                <div class="ai-gallery-thumb ai-gallery-failed">
                                    <i class="bi bi-hourglass-split"></i>
                                </div>
                            @endif

                            <div class="ai-gallery-info">
                                <div class="ai-gallery-prompt" title="{{ $g->prompt_used }}">
                                    {{ \Illuminate\Support\Str::limit($g->prompt_used, 160) }}
                                </div>
                                @if($g->isFailed() && $g->error_message)
                                    <div class="small text-danger mt-1">
                                        <i class="bi bi-exclamation-circle me-1"></i>{{ \Illuminate\Support\Str::limit($g->error_message, 80) }}
                                    </div>
                                @endif
                                <div class="ai-gallery-meta">
                                    <small class="text-clr-muted">
                                        <i class="bi bi-clock me-1"></i>{{ $g->created_at->diffForHumans() }}
                                    </small>
                                    @if($g->prompt)
                                        <small class="badge bg-teal-soft">{{ $g->prompt->name }}</small>
                                    @endif
                                    <small class="badge bg-dark">{{ $g->aspect_ratio }}</small>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        window.vertexConfig = {
            generateUrl: '{{ route('admin.vertex.generate') }}',
            csrfToken: '{{ csrf_token() }}',
            batchPollIntervalMs: 3000,
        };

        document.addEventListener('click', function (e) {
            var pinBtn = e.target.closest('[data-select-prompt]');
            if (pinBtn) {
                var sel = document.getElementById('vertexPromptId');
                if (sel) {
                    sel.value = pinBtn.dataset.selectPrompt;
                    sel.dispatchEvent(new Event('change'));
                }
                document.querySelectorAll('.vtx-pinned-btn').forEach(function (b) { b.classList.remove('active'); });
                pinBtn.classList.add('active');
                return;
            }

            var btn = e.target.closest('.vtx-batch-retry-btn');
            if (!btn) return;

            var batchId = btn.getAttribute('data-batch-id');
            var failedCount = btn.getAttribute('data-failed-count');

            AdminModal.confirm({
                title: 'Hatalı Üretimleri Tekrar Dene',
                message: failedCount + ' hatalı üretim yeniden kuyruğa alınacak. Onaylıyor musunuz?',
                confirmText: 'Tekrar Dene',
                type: 'warning',
            }).then(function (confirmed) {
                if (!confirmed) return;

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                fetch('/admin/vertex/batch/' + batchId + '/retry', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                }).then(function (r) { return r.json(); })
                  .then(function (data) {
                    if (data.success) {
                        AdminModal.status({
                            title: 'Kuyruğa Alındı',
                            message: data.message,
                            type: 'success',
                        }).then(function () { location.reload(); });
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
                        AdminModal.status({
                            title: 'Hata',
                            message: data.message || 'Bir hata oluştu.',
                            type: 'danger',
                        });
                    }
                }).catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
                    AdminModal.status({
                        title: 'Hata',
                        message: 'Bağlantı hatası oluştu.',
                        type: 'danger',
                    });
                });
            });
        });
    </script>
    <script>
    (function () {
        var statusUrl = '{{ route("admin.vertex.system-status") }}';
        var pollInterval = 10000;
        var statusMap = {
            running: 'Üretim Yapılıyor',
            idle: 'Boşta',
            rate_limited: 'Rate Limit — Bekliyor',
            unknown: 'Bilinmiyor'
        };
        var pulseMap = {
            running: 'vtx-sys-pulse-green',
            idle: 'vtx-sys-pulse-gray',
            rate_limited: 'vtx-sys-pulse-orange',
            unknown: 'vtx-sys-pulse-red'
        };

        function fetchStatus() {
            fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(function () {
                    document.getElementById('vtxSysLabel').textContent = 'Bağlantı hatası';
                });
        }

        function render(d) {
            var cronStatus = d.cron.alive ? d.cron.status : 'unknown';
            var pulse = document.getElementById('vtxSysPulse');
            pulse.className = 'vtx-sys-pulse ' + (pulseMap[cronStatus] || 'vtx-sys-pulse-red');

            document.getElementById('vtxSysLabel').textContent = statusMap[cronStatus] || cronStatus;

            var cronEl = document.getElementById('vtxCronStatus');
            if (d.cron.alive) {
                cronEl.textContent = cronStatus === 'running' ? 'Çalışıyor' : (cronStatus === 'rate_limited' ? 'Rate Limit' : 'Aktif');
                cronEl.className = 'vtx-sys-cell-value vtx-sys-text-' + (cronStatus === 'running' ? 'green' : (cronStatus === 'rate_limited' ? 'orange' : 'blue'));
            } else {
                cronEl.textContent = d.cron.last_run ? 'Yanıt Yok' : 'Hiç Çalışmadı';
                cronEl.className = 'vtx-sys-cell-value vtx-sys-text-red';
            }
            document.getElementById('vtxCronLastRun').textContent = d.cron.last_run_ago ? 'Son: ' + d.cron.last_run_ago : '';

            var queueCount = d.queue.pending_count;
            document.getElementById('vtxQueueCount').textContent = queueCount > 0 ? queueCount + ' görsel' : 'Kuyruk boş';
            document.getElementById('vtxQueueEta').textContent = queueCount > 0 ? 'Tahmini: ~' + d.queue.eta_human : '';

            var ab = d.active_batch;
            var batchEl = document.getElementById('vtxActiveBatch');
            var batchSub = document.getElementById('vtxActiveBatchSub');
            var progressWrap = document.getElementById('vtxSysProgressWrap');

            if (ab) {
                batchEl.textContent = '#' + ab.id + ' — ' + ab.prompt_name;
                batchSub.textContent = ab.completed_count + '/' + ab.total_count + ' tamamlandı';

                progressWrap.classList.remove('d-none');
                document.getElementById('vtxSysProgressLabel').textContent = 'Batch #' + ab.id + ' · ' + ab.prompt_name;
                document.getElementById('vtxSysProgressPct').textContent = '%' + Math.round(ab.progress);

                var bar = document.getElementById('vtxSysProgressBar');
                bar.setAttribute('aria-valuenow', ab.progress);
                bar.style.width = ab.progress + '%';

                var detail = ab.completed_count + ' başarılı';
                if (ab.failed_count > 0) detail += ' · ' + ab.failed_count + ' hatalı';
                document.getElementById('vtxSysProgressDetail').textContent = detail;
                document.getElementById('vtxSysProgressDuration').textContent = ab.duration;
            } else {
                batchEl.textContent = 'Yok';
                batchSub.textContent = d.queue.active_batch_count > 0 ? d.queue.active_batch_count + ' batch kuyrukta' : '';
                progressWrap.classList.add('d-none');
            }

            var todayTotal = d.today.completed + d.today.failed;
            document.getElementById('vtxTodayCount').textContent = d.today.completed + ' başarılı';
            document.getElementById('vtxTodaySub').textContent = todayTotal > 0
                ? (d.today.failed > 0 ? d.today.failed + ' hatalı · ' : '') + 'toplam ' + todayTotal
                : 'Henüz üretim yok';

            var alertEl = document.getElementById('vtxSysAlert');
            var alertText = document.getElementById('vtxSysAlertText');
            if (d.cron.rate_limit_strikes > 0) {
                alertEl.classList.remove('d-none');
                alertText.textContent = d.cron.rate_limit_strikes + ' kez rate limit · Aralık: ' + d.cron.current_delay + ' saniye';
            } else if (!d.cron.alive && d.cron.last_run) {
                alertEl.classList.remove('d-none');
                alertText.textContent = 'Cron yanıt vermiyor — son çalışma: ' + d.cron.last_run;
            } else {
                alertEl.classList.add('d-none');
            }
        }

        fetchStatus();
        setInterval(fetchStatus, pollInterval);
    })();
    </script>
    <script>
    (function () {
        if (new URLSearchParams(window.location.search).has('batch_page')) {
            var el = document.getElementById('batchHistory');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    })();
    </script>
    <script src="{{ asset('assets/admin/js/vertex-generate.js') }}"></script>
    <script src="{{ asset('assets/admin/js/mlib-lightbox.js') }}" defer></script>
@endpush
