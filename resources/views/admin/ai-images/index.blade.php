@extends('layouts.admin')

@section('title', 'AI Görsel Oluşturucu')
@section('page_title', 'AI Görsel Oluşturucu')
@section('page_description', 'Gemini AI ile prompt yazarak görsel üret.')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">AI Görsel Oluşturucu</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-magic text-teal me-2"></i>AI Görsel Oluşturucu</h1>
            <p class="page-subtitle">Prompt yaz, Gemini saniyeler içinde görseli çizsin.</p>
        </div>
        <button type="button" class="btn-glass" data-bs-toggle="modal" data-bs-target="#promptManagerModal">
            <i class="bi bi-collection"></i> Prompt Şablonları ({{ $stats['prompts'] }})
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
    @endif

    @php
        $aiImageEnabled = (string) \App\Models\Setting::getValue('ai_image_enabled', '0') === '1';
        $aiImageKeySet  = ! empty(\App\Models\Setting::getValue('ai_image_api_key'));
    @endphp

    @if(! $aiImageEnabled)
        <div class="alert alert-warning d-flex align-items-center gap-3" role="alert">
            <i class="bi bi-power fs-4"></i>
            <div class="flex-grow-1">
                <strong>AI Görsel Üretimi Pasif</strong>
                <div class="small">Görsel üretebilmek için <a href="{{ route('admin.settings.index') }}#stg-ai" class="alert-link">Ayarlar → AI → Görsel Üretimi</a> bölümünden toggle'ı aktif edin.</div>
            </div>
        </div>
    @elseif(! $aiImageKeySet)
        <div class="alert alert-danger d-flex align-items-center gap-3" role="alert">
            <i class="bi bi-key-fill fs-4"></i>
            <div class="flex-grow-1">
                <strong>Görsel API Key eksik</strong>
                <div class="small">Aktif olmasına rağmen API key girilmemiş. <a href="{{ route('admin.settings.index') }}#stg-ai" class="alert-link">Ayarlar → AI → AI Görsel API Key</a> alanına (text key'den ayrı) bir key ekleyin.</div>
            </div>
        </div>
    @endif

    {{-- STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-images"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Görsel</span>
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
                    <span class="usr-stat-label">Prompt Şablonu</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['prompts'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- GENERATION PANEL --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-7" data-aos="fade-up">
            <div class="card-dark h-100">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-blue"><i class="bi bi-pencil-square"></i></div>
                        <div>
                            <h6 class="mb-0">Prompt</h6>
                            <small class="text-muted">Şablon seç veya özgürce yaz</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <form id="aiImageForm">
                        @csrf

                        {{-- Prompt template selector --}}
                        <div class="mb-3">
                            <label for="promptTemplate" class="form-label text-clr-secondary">Şablon Seçin (isteğe bağlı)</label>
                            <select class="form-select form-select-theme" id="promptTemplate" name="prompt_template_id">
                                <option value="">— Kendi promptumu yazacağım —</option>
                                @foreach($prompts as $prompt)
                                    <option value="{{ $prompt->id }}"
                                            data-prompt="{{ $prompt->prompt }}"
                                            data-reference-image="{{ $prompt->reference_image_path ? upload_url($prompt->reference_image_path) : '' }}"
                                            data-reference-image-name="{{ $prompt->reference_image_path ? basename($prompt->reference_image_path) : '' }}">
                                        {{ $prompt->name }}@if($prompt->reference_image_path) 🖼️ @endif
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-clr-muted">Şablon seçtiğinde prompt alanına otomatik yazılır; dilediğin gibi düzenleyebilirsin.</small>
                        </div>

                        {{-- Reference image preview (şablon seçilince görünür) --}}
                        <div class="mb-3 ai-template-ref-preview d-none" id="templateRefPreview">
                            <label class="form-label text-clr-secondary mb-2">
                                <i class="bi bi-image-fill text-info me-1"></i> Şablonun Referans Görseli
                                <small class="text-clr-muted ms-1">(image-to-image modunda kullanılır)</small>
                            </label>
                            <div class="ai-template-ref-thumb-wrap">
                                <img src="" alt="Referans görsel" id="templateRefImage" class="ai-template-ref-thumb">
                                <a href="" target="_blank" rel="noopener" id="templateRefLink"
                                   class="ai-template-ref-link" title="Yeni sekmede tam boyutta aç">
                                    <span id="templateRefName"></span>
                                    <i class="bi bi-box-arrow-up-right ms-1"></i>
                                </a>
                            </div>
                        </div>

                        {{-- Main prompt textarea --}}
                        <div class="mb-3">
                            <label for="promptText" class="form-label text-clr-secondary">
                                Prompt <span class="text-danger">*</span>
                                <small class="text-clr-muted ms-2"><span id="promptCharCount">0</span> karakter</small>
                            </label>
                            <textarea class="form-control form-control-theme" id="promptText" name="prompt"
                                      rows="14" required
                                      placeholder="Örn: Rustik ahşap masada taze köy sütü, sabah ışığı, doğal çiftlik manzarası arka plan, profesyonel yemek fotoğrafı stili..."></textarea>
                        </div>

                        {{-- Aspect ratio --}}
                        <div class="mb-3">
                            <label class="form-label text-clr-secondary">En Boy Oranı</label>
                            <div class="ai-aspect-switch">
                                <input type="radio" class="btn-check" name="aspect_ratio" id="aspect-1-1" value="1:1" checked>
                                <label for="aspect-1-1" class="ai-aspect-btn">
                                    <i class="bi bi-square"></i>
                                    <span>1:1</span>
                                    <small>Kare</small>
                                </label>

                                <input type="radio" class="btn-check" name="aspect_ratio" id="aspect-16-9" value="16:9">
                                <label for="aspect-16-9" class="ai-aspect-btn">
                                    <i class="bi bi-aspect-ratio"></i>
                                    <span>16:9</span>
                                    <small>Yatay</small>
                                </label>

                                <input type="radio" class="btn-check" name="aspect_ratio" id="aspect-9-16" value="9:16">
                                <label for="aspect-9-16" class="ai-aspect-btn">
                                    <i class="bi bi-phone"></i>
                                    <span>9:16</span>
                                    <small>Dikey</small>
                                </label>

                                <input type="radio" class="btn-check" name="aspect_ratio" id="aspect-4-3" value="4:3">
                                <label for="aspect-4-3" class="ai-aspect-btn">
                                    <i class="bi bi-tablet-landscape"></i>
                                    <span>4:3</span>
                                    <small>Klasik</small>
                                </label>

                                <input type="radio" class="btn-check" name="aspect_ratio" id="aspect-3-4" value="3:4">
                                <label for="aspect-3-4" class="ai-aspect-btn">
                                    <i class="bi bi-tablet"></i>
                                    <span>3:4</span>
                                    <small>Portre</small>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn-teal w-100" id="generateBtn"
                                {{ (! $aiImageEnabled || ! $aiImageKeySet) ? 'disabled' : '' }}>
                            <i class="bi bi-magic me-1"></i>
                            <span id="generateBtnText">
                                @if(! $aiImageEnabled)
                                    Görsel Üretimi Pasif
                                @elseif(! $aiImageKeySet)
                                    API Key Eksik
                                @else
                                    Görseli Oluştur
                                @endif
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Preview panel --}}
        <div class="col-lg-5" data-aos="fade-up" data-aos-delay="100">
            <div class="card-dark h-100">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-teal"><i class="bi bi-eye"></i></div>
                        <div>
                            <h6 class="mb-0">Önizleme</h6>
                            <small class="text-muted">Son oluşturulan görsel</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div id="previewContainer" class="ai-preview-container">
                        <div class="ai-preview-empty" id="previewEmpty">
                            <i class="bi bi-image"></i>
                            <p class="text-clr-muted mb-0">Henüz görsel oluşturmadın</p>
                            <small class="text-clr-muted">Prompt yaz ve "Oluştur" butonuna bas</small>
                        </div>

                        <div class="ai-preview-loading d-none" id="previewLoading">
                            <div class="ai-spinner"></div>
                            <p class="text-clr-secondary mb-0 mt-3">Gemini görseli çiziyor...</p>
                            <small class="text-clr-muted">Bu işlem 10-30 saniye sürebilir</small>
                        </div>

                        <div class="ai-preview-result d-none" id="previewResult">
                            <div class="ai-preview-image-wrap">
                                <img id="previewImage" src="" alt="AI Generated">
                            </div>
                            <div class="ai-preview-meta mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Başarılı</span>
                                    <small class="text-clr-muted" id="previewTime"></small>
                                </div>
                                <a id="previewDownload" href="#" download class="btn-glass w-100 btn-sm">
                                    <i class="bi bi-download me-1"></i> İndir
                                </a>
                            </div>
                        </div>

                        <div class="ai-preview-error d-none" id="previewError">
                            <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                            <p class="text-danger mb-0 mt-2" id="previewErrorText"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- GALLERY --}}
    <div class="card-dark mb-4" data-aos="fade-up">
        <div class="card-header-custom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-purple"><i class="bi bi-grid-3x3-gap-fill"></i></div>
                <div>
                    <h6 class="mb-0">Galeri</h6>
                    <small class="text-muted">Tüm oluşturulan görseller</small>
                </div>
            </div>
            @if($images->total() > 0)
                <small class="text-clr-muted">Toplam {{ $images->total() }} görsel</small>
            @endif
        </div>
        <div class="card-body-custom">
            @if($images->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-images" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="text-clr-muted mt-3 mb-0">Henüz hiç görsel oluşturmadın.</p>
                </div>
            @else
                <div class="ai-gallery-grid" id="galleryGrid">
                    @foreach($images as $img)
                        <div class="ai-gallery-item status-{{ $img->status }}">
                            @if($img->isCompleted() && $img->image_path)
                                <a href="{{ upload_url($img->image_path, 'lg') }}" target="_blank" class="ai-gallery-thumb">
                                    <img src="{{ upload_url($img->image_path, 'md') }}" alt="AI Generated" loading="lazy">
                                </a>
                            @else
                                <div class="ai-gallery-thumb ai-gallery-failed">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                </div>
                            @endif

                            <div class="ai-gallery-info">
                                <div class="ai-gallery-prompt" title="{{ $img->final_prompt }}">
                                    {{ \Illuminate\Support\Str::limit($img->final_prompt, 200) }}
                                </div>
                                @if($img->isFailed() && $img->error_message)
                                    <div class="small text-danger mt-1">
                                        <i class="bi bi-exclamation-circle me-1"></i>{{ \Illuminate\Support\Str::limit($img->error_message, 60) }}
                                    </div>
                                @endif
                                <div class="ai-gallery-meta">
                                    <small class="text-clr-muted">
                                        <i class="bi bi-clock me-1"></i>{{ $img->created_at->diffForHumans() }}
                                    </small>
                                    @if($img->promptTemplate)
                                        <small class="badge bg-teal-soft">{{ $img->promptTemplate->name }}</small>
                                    @endif
                                    <small class="badge bg-dark">{{ $img->aspect_ratio }}</small>
                                </div>
                                <div class="ai-gallery-actions">
                                    <button type="button" class="usr-action-btn" title="Detay (prompt + API yanıtı)"
                                            data-ai-image-detail="{{ $img->id }}">
                                        <i class="bi bi-search"></i>
                                    </button>
                                    @if($img->isCompleted() && $img->image_path)
                                        <a href="{{ upload_url($img->image_path, 'lg') }}" download class="usr-action-btn" title="İndir">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    @endif
                                    <form method="POST" action="{{ route('admin.ai-images.images.destroy', $img->id) }}" class="d-inline js-delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="usr-action-btn danger" title="Sil"
                                                data-confirm="Bu görseli silmek istediğine emin misin?">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @include('partials.admin.pagination', ['paginator' => $images, 'itemLabel' => 'görsel'])

    {{-- PROMPT MANAGER MODAL --}}
    <div class="modal fade" id="promptManagerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-theme">
            <div class="modal-content modal-content-theme">
                <div class="modal-header modal-header-theme">
                    <h5 class="modal-title">
                        <i class="bi bi-collection-fill me-2 text-teal"></i> Prompt Şablonları
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>

                <div class="modal-body modal-body-theme">
                    {{-- Existing prompts list --}}
                    @if($prompts->isNotEmpty())
                        <div class="ai-prompt-list mb-4">
                            @foreach($prompts as $prompt)
                                <div class="ai-prompt-row" data-prompt-id="{{ $prompt->id }}">
                                    <div class="ai-prompt-row-info">
                                        <strong>{{ $prompt->name }}</strong>
                                        @if($prompt->description)
                                            <small class="d-block text-clr-muted">{{ $prompt->description }}</small>
                                        @endif
                                        @if($prompt->reference_image_path)
                                            <small class="d-block mt-1">
                                                <i class="bi bi-image-fill text-info me-1"></i>
                                                <span class="badge bg-info-subtle text-info-emphasis">Referans görsel ekli</span>
                                            </small>
                                        @endif
                                        {{-- Tam prompt — expand/collapse, kesme YOK --}}
                                        <details class="ai-prompt-details mt-2">
                                            <summary class="text-clr-secondary small" style="cursor:pointer;">
                                                <i class="bi bi-chevron-right me-1"></i>
                                                Prompt içeriğini göster
                                                <small class="text-muted">({{ number_format(mb_strlen($prompt->prompt, 'UTF-8'), 0, ',', '.') }} karakter)</small>
                                            </summary>
                                            <pre class="ai-prompt-fulltext mt-2"><code>{{ $prompt->prompt }}</code></pre>
                                        </details>
                                    </div>
                                    <div class="ai-prompt-row-actions">
                                        <button type="button" class="usr-action-btn js-edit-prompt"
                                                data-prompt='@json($prompt)' title="Düzenle">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="{{ route('admin.ai-images.prompts.destroy', $prompt) }}" class="d-inline js-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="usr-action-btn danger" title="Sil"
                                                    data-confirm="Bu şablonu silmek istediğine emin misin?">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Add/edit form --}}
                    <form id="promptForm" method="POST" action="{{ route('admin.ai-images.prompts.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="_method" id="promptFormMethod" value="POST">

                        <h6 class="text-clr-secondary mb-3" id="promptFormTitle">
                            <i class="bi bi-plus-square me-1"></i> Yeni Şablon Ekle
                        </h6>

                        {{-- Validation hata banner'ı — submit fail olursa burada listelenir --}}
                        @if($errors->any())
                            <div class="alert alert-danger d-flex align-items-start gap-2 mb-3" role="alert">
                                <i class="bi bi-exclamation-triangle-fill" style="font-size:1.25rem;"></i>
                                <div class="flex-grow-1">
                                    <strong>Şablon kaydedilemedi — aşağıdaki hataları düzelt:</strong>
                                    <ul class="mb-0 mt-1 ps-3 small">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="promptName" class="form-label text-clr-secondary">Şablon Adı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-theme @error('name') is-invalid @enderror"
                                       id="promptName" name="name"
                                       value="{{ old('name') }}"
                                       required maxlength="120" placeholder="Örn: Ürün Fotoğrafı">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="promptDescription" class="form-label text-clr-secondary">Kısa Açıklama</label>
                                <input type="text" class="form-control form-control-theme @error('description') is-invalid @enderror"
                                       id="promptDescription" name="description"
                                       value="{{ old('description') }}"
                                       maxlength="255" placeholder="Ürünler için fotoğraf stili">
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="promptBody" class="form-label text-clr-secondary">
                                    Varsayılan Prompt <span class="text-danger">*</span>
                                    <small class="text-muted">(<span id="promptBodyCounter">0</span> / 20.000)</small>
                                </label>
                                <textarea class="form-control form-control-theme @error('prompt') is-invalid @enderror"
                                          id="promptBody" name="prompt"
                                          rows="8" required minlength="10" maxlength="20000"
                                          placeholder="Profesyonel ürün fotoğrafı, beyaz arka plan, yumuşak ışık... Logo varsa: 'Bu logoyu şişenin etiketine yerleştir, dokunma'">{{ old('prompt') }}</textarea>
                                @error('prompt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="form-text text-clr-secondary">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Placeholder: <code>{topic}</code> kullanırsan bulk import sırasında her satırın <code>konu</code> veya <code>gorsel_degeri</code> alanı buraya yerleşir.
                                    Uzun prompt'lar (örn. Özel Gün Hikaye, CTA Story) max 20.000 karakter desteklenir.
                                </small>
                            </div>

                            {{-- Referans görsel — image-to-image flow için AI'a yollanır --}}
                            <div class="col-12">
                                <label for="promptReferenceImage" class="form-label text-clr-secondary">
                                    Referans Görsel (opsiyonel)
                                </label>
                                <div class="d-flex align-items-start gap-3 flex-wrap">
                                    <div id="promptReferenceImagePreviewWrap" class="d-none">
                                        <img id="promptReferenceImagePreview" src="" alt="Mevcut referans görsel"
                                             style="max-width:120px;max-height:120px;border-radius:8px;object-fit:contain;background:#fff;padding:4px;">
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" id="promptReferenceImageRemove" name="reference_image_remove" value="1">
                                            <label class="form-check-label small text-danger" for="promptReferenceImageRemove">
                                                Görseli kaldır
                                            </label>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <input type="file" class="form-control form-control-theme @error('reference_image') is-invalid @enderror"
                                               id="promptReferenceImage"
                                               name="reference_image" accept="image/jpeg,image/png,image/webp">
                                        @error('reference_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="form-text text-clr-secondary">
                                            <i class="bi bi-image me-1"></i>
                                            Logo, ürün fotoğrafı veya marka öğesi (JPG/PNG/WebP, max 5 MB).
                                            Yüklediğin görsel <strong>image-to-image</strong> akışıyla AI'a aynen iletilir;
                                            prompt'ta nasıl kullanılacağını yaz (örn: "Bu logoyu şişenin etiketine yerleştir, dokunma").
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="promptIsActive" name="is_active" value="1" checked>
                                    <label class="form-check-label text-clr-secondary" for="promptIsActive">Aktif</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn-teal">
                                <i class="bi bi-check-lg me-1"></i> <span id="promptSubmitText">Ekle</span>
                            </button>
                            <button type="button" class="btn-glass d-none" id="promptCancelBtn">Vazgeç</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    {{-- AI Detay Modal (shared partial — gönderilen prompt + API yanıtı + ref görsel) --}}
    @include('admin._partials.ai-image-detail-modal')

@endsection

@push('scripts')
    <script>
        window.aiImageConfig = {
            generateUrl: '{{ route('admin.ai-images.generate') }}',
            csrfToken: '{{ csrf_token() }}',
        };
    </script>
    <script src="{{ asset('assets/admin/js/ai-images.js') }}"></script>
@endpush
