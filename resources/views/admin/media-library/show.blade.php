@extends('layouts.admin')

@section('title', $productLabel . ' — Görsel Kütüphanesi')
@section('page_title', $productLabel)
@section('page_description', $productLabel . ' için yüklenen görseller (cron blog kapağı için kullanılır)')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.media-library.index') }}" class="breadcrumb-link">Görsel Kütüphanesi</a>
            </li>
            <li class="breadcrumb-item active text-teal">{{ $productLabel }}</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title">
                @if($productName === null)
                    <i class="bi bi-stars text-warning me-2"></i>{{ $productLabel }}
                    <span class="badge bg-info-subtle text-info-emphasis ms-2">Fallback Havuz</span>
                @else
                    <i class="bi bi-box-seam text-teal me-2"></i>{{ $productLabel }}
                @endif
            </h1>
            <p class="page-subtitle">
                <i class="bi bi-images me-1"></i>{{ $totalActive }} aktif görsel
                @if($productName === null)
                    · Diğer ürünlerle eşleşme bulunamadığında cron buradan seçer
                @else
                    · Cron bu ürün için yazılan blog'larda buradan rastgele seçer
                @endif
            </p>
        </div>
        <a href="{{ route('admin.media-library.index') }}" class="btn-glass">
            <i class="bi bi-arrow-left me-1"></i> Tüm Ürünler
        </a>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="alert alert-success mb-4" data-aos="fade-up">
            <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-4" data-aos="fade-up">
            <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger mb-4" data-aos="fade-up">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- ==================== AI PROMPT ÖNERİSİ ==================== --}}
    <div class="card-dark mb-4 mlib-prompt-card" data-aos="fade-up">
        <div class="card-header-custom d-flex align-items-center gap-3 flex-wrap">
            <div class="form-section-icon bg-icon-purple"><i class="bi bi-stars"></i></div>
            <div class="flex-grow-1">
                <h6 class="mb-0">
                    AI Prompt Önerisi (Nano Banana / Gemini Image)
                    <span class="badge bg-info-subtle text-info-emphasis ms-1">Kopyala-Yapıştır</span>
                </h6>
                <small class="text-muted">
                    Tarayıcıdan Nano Banana'ya yapıştır → kare 1500×1500 + brand uyumlu
                    görsel üretsin. Yazı/logo basmama garantisi ile.
                </small>
            </div>
            <button type="button" class="btn-glass btn-sm" id="mlibPromptToggle">
                <i class="bi bi-eye me-1"></i><span data-mlib-toggle-label>Göster</span>
            </button>
        </div>
        <div class="card-body-custom mlib-prompt-body d-none" id="mlibPromptBody">
            {{-- Mode seçimi --}}
            <div class="stg-field mb-3">
                <label class="stg-label">
                    <i class="bi bi-toggles me-1"></i>Prompt Modu
                </label>
                <div class="d-flex gap-3 flex-wrap">
                    <label class="form-check mlib-mode-option">
                        <input type="radio" name="mlibPromptMode" id="mlibModeBranded"
                               value="branded_overlay" class="form-check-input" checked>
                        <span class="form-check-label ms-1">
                            <strong>Markalı Overlay</strong>
                            <span class="badge bg-success-subtle text-success-emphasis ms-1">Önerilen</span>
                            <small class="d-block text-muted">Logo + iletişim hazır, paylaşıma hazır görsel (5 PNG asset gerek)</small>
                        </span>
                    </label>
                    <label class="form-check mlib-mode-option">
                        <input type="radio" name="mlibPromptMode" id="mlibModeStandard"
                               value="standard" class="form-check-input">
                        <span class="form-check-label ms-1">
                            <strong>Standart</strong>
                            <small class="d-block text-muted">Sade ürün fotoğrafı, yazısız temiz arka plan</small>
                        </span>
                    </label>
                    <label class="form-check mlib-mode-option">
                        <input type="radio" name="mlibPromptMode" id="mlibModeCtaStory"
                               value="cta_story" class="form-check-input">
                        <span class="form-check-label ms-1">
                            <strong>CTA Hikaye</strong>
                            <span class="badge bg-warning-subtle text-warning-emphasis ms-1">Story</span>
                            <small class="d-block text-muted">9:16 dikey, logo + ürün + CTA butonu + telefon (1 PNG asset gerek)</small>
                        </span>
                    </label>
                    <label class="form-check mlib-mode-option">
                        <input type="radio" name="mlibPromptMode" id="mlibModeOzelGun"
                               value="ozel_gun_hikaye" class="form-check-input">
                        <span class="form-check-label ms-1">
                            <strong>Özel Gün Hikaye</strong>
                            <span class="badge bg-info-subtle text-info-emphasis ms-1">Story</span>
                            <small class="d-block text-muted">9:16 dikey, bayram/anma günü mesajı + logo (1 PNG asset gerek). [OZEL GUN MESAJI] ve [ALT MESAJ] placeholder'larını kendi mesajınla değiştir.</small>
                        </span>
                    </label>
                </div>
            </div>

            {{-- Asset listesi (sadece Branded modda) --}}
            <div class="alert alert-info mb-3 small mlib-branded-assets" id="mlibBrandedAssets">
                <strong><i class="bi bi-paperclip me-1"></i>Nano Banana'ya yükleyeceğin 5 PNG dosyası:</strong>
                <ul class="mb-0 mt-1 ps-4">
                    <li><code>01-logo.png</code> — Marka logon</li>
                    <li><code>02-instagram.png</code> — Instagram ikonu</li>
                    <li><code>03-facebook.png</code> — Facebook ikonu</li>
                    <li><code>04-whatsapp.png</code> — WhatsApp ikonu</li>
                    <li><code>05-phone.png</code> — Telefon ikonu</li>
                </ul>
                <small class="text-muted d-block mt-1">
                    Hepsi PNG (transparan arka planlı), bu sırayla attach et.
                </small>
            </div>

            {{-- Asset listesi (sadece CTA Story modda) --}}
            <div class="alert alert-warning mb-3 small mlib-cta-assets d-none" id="mlibCtaAssets">
                <strong><i class="bi bi-paperclip me-1"></i>Nano Banana'ya yükleyeceğin 1 PNG dosyası:</strong>
                <ul class="mb-0 mt-1 ps-4">
                    <li><code>01-logo.png</code> — Marka logon (transparan arka planlı)</li>
                </ul>
                <small class="text-muted d-block mt-1">
                    Dikey 1080×1920 (9:16) Story görseli üretilir. CTA + telefon numarası otomatik.
                </small>
            </div>

            {{-- Asset listesi (sadece Özel Gün Hikaye modda) --}}
            <div class="alert alert-info mb-3 small mlib-ozelgun-assets d-none" id="mlibOzelGunAssets">
                <strong><i class="bi bi-paperclip me-1"></i>Nano Banana'ya yükleyeceğin 1 PNG dosyası:</strong>
                <ul class="mb-0 mt-1 ps-4">
                    <li><code>01-logo.png</code> — Marka logon (transparan arka planlı)</li>
                </ul>
                <strong class="d-block mt-2">📝 Prompt'ta değiştirilecek 2 alan var:</strong>
                <ul class="mb-0 mt-1 ps-4">
                    <li><code>[OZEL GUN MESAJI]</code> — Ana mesaj (örn. <em>"24 Kasım Öğretmenler Günü"</em>, <em>"Ramazan Bayramı"</em>)</li>
                    <li><code>[ALT MESAJ]</code> — Opsiyonel alt yazı (örn. <em>"Kutlu olsun"</em>, <em>"Mübarek olsun"</em>, <em>"Saygıyla anıyoruz"</em>)</li>
                </ul>
                <small class="text-muted d-block mt-1">
                    Dikey 1080×1920 (9:16) Story görseli — özel gün/bayram/anma günü için duygusal kompozisyon.
                </small>
            </div>

            {{-- Varyasyon dropdown (sadece Standart modda) --}}
            <div class="stg-field mb-3 mlib-standard-variation d-none" id="mlibVariationWrap">
                <label for="mlibPromptVariation" class="stg-label">
                    <i class="bi bi-shuffle me-1"></i>Varyasyon (opsiyonel)
                </label>
                <select id="mlibPromptVariation" class="stg-input">
                    <option value="">— Varyasyon yok (sadece temel prompt)</option>
                    @foreach($promptDataStandard['variations'] ?? [] as $variation)
                        <option value="{{ $variation }}">{{ $variation }}</option>
                    @endforeach
                </select>
                <small class="stg-hint">
                    Her ürettirmede farklı bir varyasyon seç → koleksiyonun çeşitlensin.
                </small>
            </div>

            <div class="stg-field">
                <label class="stg-label">
                    <i class="bi bi-card-text me-1"></i>Prompt (kopyalanacak metin)
                </label>
                <textarea id="mlibPromptText"
                          class="stg-input mlib-prompt-textarea"
                          rows="20"
                          readonly></textarea>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Nano Banana paid tier'da <strong>2K çözünürlük</strong> seçeneği var
                    (önerilen). Free tier 1024×1024 üretir, min 1080×1080 limitini karşılamaz.
                </small>
                <div class="d-flex gap-2">
                    <a href="https://aistudio.google.com/prompts/new_chat" target="_blank" rel="noopener"
                       class="btn-glass btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Nano Banana Aç
                    </a>
                    <button type="button" class="btn-teal btn-sm" id="mlibPromptCopy">
                        <i class="bi bi-clipboard me-1"></i>Prompt'u Kopyala
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== UPLOAD FORM (Dropzone) ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up">
        <div class="card-header-custom d-flex align-items-center gap-3">
            <div class="form-section-icon bg-icon-teal"><i class="bi bi-cloud-upload-fill"></i></div>
            <div class="flex-grow-1">
                <h6 class="mb-0">Görsel Yükle</h6>
                <small class="text-muted">
                    Sürükle bırak → <strong>anında yüklenir</strong> · 6 paralel istek ·
                    Kare 1:1 zorunlu · min 1024×1024 · önerilen 1500×1500 · max 4 MB
                </small>
            </div>
        </div>
        <div class="card-body-custom">
            {{-- Ürün dropdown — varsayılan: bu sayfanın ürünü --}}
            <div class="stg-field mb-3">
                <label for="mlibProductSelect" class="stg-label">
                    <i class="bi bi-box-seam me-2 text-teal"></i>Hangi ürün için yüklensin?
                </label>
                <select id="mlibProductSelect" class="stg-input">
                    <option value="Genel" {{ $productName === null ? 'selected' : '' }}>
                        🌾 Genel (üründen bağımsız fallback)
                    </option>
                    @foreach($availableProducts ?? [] as $product)
                        <option value="{{ $product }}" {{ $productName === $product ? 'selected' : '' }}>
                            {{ $product }}
                        </option>
                    @endforeach
                </select>
                <small class="stg-hint">
                    Yüklenen tüm dosyalar seçili ürüne bağlanır. Dosyaları kuyruğa eklemeden ürün
                    değiştirirsen onay sorulur.
                </small>
            </div>

            {{-- Dropzone --}}
            <div id="mediaLibraryDropzone"
                 class="dropzone mlib-dropzone"
                 data-upload-url="{{ route('admin.media-library.upload') }}">
                {{-- Dropzone JS init eder; default mesaj media-library-upload.js'de --}}
            </div>

            {{-- Yükleme sonuç paneli (JS doldurur — başarısız dosyalar listelenir) --}}
            <div id="mlibUploadResult" class="mlib-upload-result d-none mt-3"></div>

            {{-- Action buttons (autoProcessQueue: true → "Tümünü Yükle" gereksiz) --}}
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" id="mlibClearAllBtn" class="btn-glass">
                    <i class="bi bi-x-circle me-1"></i> Hatalıları/Tümünü Kaldır
                </button>
            </div>
        </div>
    </div>

    {{-- ==================== ASSET GRID ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="card-header-custom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h6 class="mb-0"><i class="bi bi-images me-2 text-teal"></i>Yüklü Görseller</h6>
                <small class="text-muted">Smart selection: en uzun süredir kullanılmamış + en az kullanılan öncelenir</small>
            </div>
            <div class="cl-per-page">
                <form method="GET" id="perPageForm" class="d-flex align-items-center gap-2 m-0">
                    <label for="perPageSelect" class="m-0 small">Göster:</label>
                    <select name="per_page" id="perPageSelect" class="cl-filter-select"
                            onchange="document.getElementById('perPageForm').submit()">
                        @foreach([12, 24, 48, 96] as $pp)
                            <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        <div class="card-body-custom">
            {{-- Empty state — grid boşken görünür, JS yeni asset eklediğinde gizlenir --}}
            <div id="mlibEmptyState" class="mlib-empty-state {{ $assets->isEmpty() ? '' : 'd-none' }}">
                <i class="bi bi-image"></i>
                <h6>Henüz görsel yok</h6>
                <p class="text-muted small mb-0">
                    Üstteki yükleme alanından görsel ekle. Cron şu an
                    @if($productName === null)
                        <strong>placeholder</strong> kullanıyor.
                    @else
                        <strong>"Genel" havuza</strong> düşüyor (varsa).
                    @endif
                </p>
            </div>

            {{-- Asset grid — her zaman render edilir, JS yeni dosyaları en başa ekler --}}
            <div class="row g-3" id="mlibAssetGrid">
                @foreach($assets as $asset)
                    <div class="col-xl-3 col-lg-4 col-md-6" data-asset-id="{{ $asset->id }}">
                        <div class="mlib-asset-tile {{ ! $asset->is_active ? 'mlib-asset-tile--inactive' : '' }}">
                            <a href="{{ upload_url($asset->image_path) }}"
                               class="mlib-asset-tile__preview"
                               data-mlib-lightbox
                               data-caption="{{ $asset->title ?? $productLabel }}"
                               title="Büyüt">
                                <img src="{{ upload_url($asset->image_path, 'thumb') }}"
                                     alt="{{ $asset->title ?? $productLabel }}"
                                     loading="lazy"
                                     class="img-fluid">
                                @if(! $asset->is_active)
                                    <span class="mlib-asset-tile__inactive-badge">Pasif</span>
                                @endif
                            </a>
                            <div class="mlib-asset-tile__meta">
                                <div class="mlib-asset-tile__usage">
                                    <i class="bi bi-arrow-repeat me-1"></i>{{ $asset->usage_count }}× kullanıldı
                                </div>
                                <div class="mlib-asset-tile__last-used">
                                    @if($asset->last_used_at)
                                        <i class="bi bi-clock me-1"></i>{{ $asset->last_used_at->diffForHumans() }}
                                    @else
                                        <i class="bi bi-dot me-1"></i>Henüz kullanılmamış
                                    @endif
                                </div>
                                @if($asset->title)
                                    <div class="mlib-asset-tile__title">{{ \Illuminate\Support\Str::limit($asset->title, 50) }}</div>
                                @endif
                            </div>
                            <div class="mlib-asset-tile__actions">
                                <button type="button"
                                        class="usr-action-btn"
                                        onclick="mlibOpenEdit({{ $asset->id }}, @js($asset->title), @js($asset->product_name ?? 'Genel'), {{ $asset->is_active ? 'true' : 'false' }})"
                                        title="Düzenle">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button"
                                        class="usr-action-btn ail-action-danger"
                                        data-asset-id="{{ $asset->id }}"
                                        data-asset-title="{{ $asset->title ?? '#' . $asset->id }}"
                                        onclick="mlibConfirmDelete(this)"
                                        title="Sil">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination — sadece çok sayfa varsa --}}
            @if($assets->hasPages())
                <div class="mt-4">
                    @include('partials.admin.pagination', ['paginator' => $assets, 'itemLabel' => 'görsel'])
                </div>
            @endif
        </div>
    </div>

    {{-- ==================== EDIT MODAL ==================== --}}
    <div class="modal fade" id="mlibEditModal" tabindex="-1" aria-labelledby="mlibEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content card-dark">
                <form id="mlibEditForm" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <h5 class="modal-title" id="mlibEditModalLabel">
                            <i class="bi bi-pencil me-2 text-teal"></i>Görseli Düzenle
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                    </div>
                    <div class="modal-body">
                        <div class="stg-field mb-3">
                            <label class="stg-label" for="mlibEditTitle">
                                <i class="bi bi-card-text me-1"></i>Başlık (SEO alt text)
                            </label>
                            <input type="text" id="mlibEditTitle" name="title" class="stg-input"
                                   maxlength="191" placeholder="Örn: Tereyağı sabah ışığında - kahvaltı">
                            <small class="stg-hint">Boş bırakırsan blog başlığı alt text olarak kullanılır.</small>
                        </div>
                        <div class="stg-field mb-3">
                            <label class="stg-label" for="mlibEditProduct">
                                <i class="bi bi-box-seam me-1"></i>Ürün
                            </label>
                            <select id="mlibEditProduct" name="product_name" class="stg-input">
                                <option value="Genel">🌾 Genel (fallback)</option>
                                @foreach($availableProducts ?? [] as $product)
                                    <option value="{{ $product }}">{{ $product }}</option>
                                @endforeach
                            </select>
                            <small class="stg-hint">Görseli farklı bir ürüne taşımak için seçim değiştir.</small>
                        </div>
                        <div class="stg-field">
                            <label class="stg-label">
                                <i class="bi bi-power me-1"></i>Durum
                            </label>
                            <div class="form-check form-switch ps-5">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" id="mlibEditActive" name="is_active" value="1" class="form-check-input">
                                <label for="mlibEditActive" class="form-check-label ms-2">
                                    <span class="text-success">Aktif</span> — pasifte cron seçmez
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-glass" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn-teal">
                            <i class="bi bi-save me-1"></i>Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('styles')
<style>
.mlib-empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:48px 16px;color:var(--text-muted);text-align:center}
.mlib-empty-state i{font-size:48px;opacity:.4}
.mlib-empty-state h6{margin:8px 0 0;color:var(--text-primary)}

.mlib-dropzone{min-height:160px;border:2px dashed var(--border-color);background:var(--bg-input);border-radius:12px;transition:border-color .2s ease}
.mlib-dropzone:hover{border-color:var(--text-teal)}

.mlib-upload-result{padding:14px 16px;border-radius:10px;border:1px solid var(--border-color);background:var(--bg-input);font-size:13px}
.mlib-upload-result--success{background:color-mix(in srgb, #22c55e 8%, transparent);border-color:color-mix(in srgb, #22c55e 25%, transparent)}
.mlib-upload-result--warning{background:color-mix(in srgb, #f59e0b 8%, transparent);border-color:color-mix(in srgb, #f59e0b 25%, transparent)}
.mlib-upload-result--mixed{background:var(--bg-input)}
.mlib-upload-result__title{font-weight:600;margin-bottom:6px;display:flex;align-items:center;gap:6px;color:var(--text-primary)}
.mlib-upload-result__clear{margin-left:auto;background:transparent;border:0;color:var(--text-muted);font-size:12px;cursor:pointer;text-decoration:underline}
.mlib-upload-result__clear:hover{color:var(--text-primary)}
.mlib-upload-result__list{margin:8px 0 0;padding-left:0;list-style:none;font-size:13px;line-height:1.9;max-height:240px;overflow-y:auto}
.mlib-result-row{padding:4px 8px;border-radius:6px;display:flex;align-items:center;gap:4px}
.mlib-result-row--success{background:color-mix(in srgb, #22c55e 6%, transparent)}
.mlib-result-row--error{background:color-mix(in srgb, #ef4444 8%, transparent)}
.mlib-result-row strong{color:var(--text-primary)}

@keyframes mlibTilePulse{
    0%{box-shadow:0 0 0 0 color-mix(in srgb, var(--text-teal) 60%, transparent)}
    100%{box-shadow:0 0 0 12px color-mix(in srgb, var(--text-teal) 0%, transparent)}
}
.mlib-asset-tile-new .mlib-asset-tile{animation:mlibTilePulse 1.5s ease-out}

/* Lightbox CSS → styles.css'e taşındı */

.mlib-asset-tile{display:flex;flex-direction:column;background:var(--bg-input);border:1px solid var(--border-color);border-radius:12px;overflow:hidden;transition:all .2s ease;height:100%}
.mlib-asset-tile:hover{border-color:var(--text-teal);transform:translateY(-2px)}
.mlib-asset-tile--inactive{opacity:.5}
.mlib-asset-tile--inactive:hover{opacity:.85}

.mlib-asset-tile__preview{display:block;position:relative;aspect-ratio:1/1;overflow:hidden;background:var(--bg-base)}
.mlib-asset-tile__preview img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .3s ease}
.mlib-asset-tile__preview:hover img{transform:scale(1.04)}
.mlib-asset-tile__inactive-badge{position:absolute;top:8px;left:8px;background:rgba(239,68,68,.85);color:#fff;font-size:11px;padding:3px 8px;border-radius:4px;font-weight:600}

.mlib-asset-tile__meta{padding:12px;display:flex;flex-direction:column;gap:4px;flex-grow:1}
.mlib-asset-tile__usage{font-size:12px;color:var(--text-primary);font-weight:500}
.mlib-asset-tile__last-used{font-size:11px;color:var(--text-muted)}
.mlib-asset-tile__title{font-size:11px;color:var(--text-muted);font-style:italic;margin-top:2px;word-break:break-word}

.mlib-asset-tile__actions{display:flex;gap:6px;padding:0 12px 12px;justify-content:flex-end}
.ail-action-danger{color:#ef4444}
.ail-action-danger:hover{background:rgba(239,68,68,.15)}

.mlib-prompt-card{border-left:3px solid color-mix(in srgb, #a855f7 60%, transparent)}
.mlib-prompt-textarea{font-family:'Courier New',monospace;font-size:12.5px;line-height:1.6;white-space:pre-wrap;background:var(--bg-base);min-height:280px}
.mlib-prompt-textarea:focus{background:var(--bg-base)}
.bg-icon-purple{background:color-mix(in srgb, #a855f7 18%, transparent);color:#a855f7}
.mlib-mode-option{display:flex;align-items:flex-start;gap:8px;padding:12px 16px;background:var(--bg-input);border:1px solid var(--border-color);border-radius:10px;cursor:pointer;flex:1 1 280px;min-width:280px;transition:all .15s ease}
.mlib-mode-option:hover{border-color:var(--text-teal)}
.mlib-mode-option:has(input:checked){border-color:var(--text-teal);background:color-mix(in srgb, var(--text-teal) 8%, transparent)}
.mlib-mode-option .form-check-input{margin-top:6px;flex-shrink:0}
.mlib-branded-assets{background:color-mix(in srgb, #0dcaf0 8%, transparent);border:1px solid color-mix(in srgb, #0dcaf0 25%, transparent);border-radius:8px;padding:12px 16px;color:var(--text-primary)}
.mlib-branded-assets ul{color:var(--text-secondary)}
.mlib-branded-assets code{color:var(--text-teal);background:transparent}
.mlib-cta-assets{background:color-mix(in srgb, #f59e0b 8%, transparent);border:1px solid color-mix(in srgb, #f59e0b 25%, transparent);border-radius:8px;padding:12px 16px;color:var(--text-primary)}
.mlib-cta-assets ul{color:var(--text-secondary)}
.mlib-cta-assets code{color:var(--text-teal);background:transparent}
</style>
@endpush

@push('scripts')
<script src="{{ asset('assets/admin/js/mlib-lightbox.js') }}?v={{ filemtime(public_path('assets/admin/js/mlib-lightbox.js')) }}" defer></script>
<script src="{{ asset('assets/admin/js/media-library-upload.js') }}?v={{ filemtime(public_path('assets/admin/js/media-library-upload.js')) }}" defer></script>
<script>
'use strict';

function mlibOpenEdit(assetId, title, productName, isActive) {
    var form = document.getElementById('mlibEditForm');
    if (!form) return;

    form.action = '/admin/media-library/' + assetId;
    document.getElementById('mlibEditTitle').value = title || '';
    document.getElementById('mlibEditProduct').value = productName || 'Genel';
    document.getElementById('mlibEditActive').checked = !!isActive;

    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('mlibEditModal'));
    modal.show();
}

// ─── AI Prompt panel ───
(function () {
    var toggleBtn       = document.getElementById('mlibPromptToggle');
    var bodyEl          = document.getElementById('mlibPromptBody');
    var variation       = document.getElementById('mlibPromptVariation');
    var variationWrap   = document.getElementById('mlibVariationWrap');
    var brandedAssets   = document.getElementById('mlibBrandedAssets');
    var ctaAssets        = document.getElementById('mlibCtaAssets');
    var ozelGunAssets    = document.getElementById('mlibOzelGunAssets');
    var promptText      = document.getElementById('mlibPromptText');
    var copyBtn         = document.getElementById('mlibPromptCopy');
    var modeRadios      = document.querySelectorAll('input[name="mlibPromptMode"]');

    if (!toggleBtn || !bodyEl || !promptText) return;

    // Backend'den gelen 4 mod verisi
    var standardData = {
        basePrompt:  @json($promptDataStandard['base_prompt']  ?? ''),
        sharedRules: @json($promptDataStandard['shared_rules'] ?? ''),
    };
    var brandedData = {
        basePrompt:  @json($promptDataBranded['base_prompt']  ?? ''),
        sharedRules: @json($promptDataBranded['shared_rules'] ?? ''),
    };
    var ctaStoryData = {
        basePrompt: @json($promptDataCtaStory['base_prompt'] ?? ''),
    };
    var ozelGunData = {
        basePrompt: @json($promptDataOzelGun['base_prompt'] ?? ''),
    };

    function currentMode() {
        var checked = document.querySelector('input[name="mlibPromptMode"]:checked');
        return checked ? checked.value : 'branded_overlay';
    }

    function buildText() {
        var mode = currentMode();

        if (mode === 'branded_overlay') {
            return brandedData.basePrompt.trim();
        }

        if (mode === 'cta_story') {
            return ctaStoryData.basePrompt.trim();
        }

        if (mode === 'ozel_gun_hikaye') {
            return ozelGunData.basePrompt.trim();
        }

        // Standart mod: base + variation + shared
        var parts = [standardData.basePrompt.trim()];
        if (variation && variation.value.trim() !== '') {
            parts.push(variation.value.trim());
        }
        if (standardData.sharedRules.trim() !== '') {
            parts.push(standardData.sharedRules.trim());
        }
        return parts.join('\n\n');
    }

    function refreshText() {
        promptText.value = buildText();
    }

    function applyModeUi() {
        var mode = currentMode();

        // Tüm mod-bağımlı panelleri gizle
        if (brandedAssets) brandedAssets.classList.add('d-none');
        if (ctaAssets) ctaAssets.classList.add('d-none');
        if (ozelGunAssets) ozelGunAssets.classList.add('d-none');
        if (variationWrap) variationWrap.classList.add('d-none');

        if (mode === 'branded_overlay') {
            if (brandedAssets) brandedAssets.classList.remove('d-none');
        } else if (mode === 'cta_story') {
            if (ctaAssets) ctaAssets.classList.remove('d-none');
        } else if (mode === 'ozel_gun_hikaye') {
            if (ozelGunAssets) ozelGunAssets.classList.remove('d-none');
        } else {
            if (variationWrap && variation && variation.options.length > 1) {
                variationWrap.classList.remove('d-none');
            }
        }
        refreshText();
    }

    // Mode radio değişimi
    modeRadios.forEach(function (radio) {
        radio.addEventListener('change', applyModeUi);
    });

    if (variation) {
        variation.addEventListener('change', refreshText);
    }

    // Toggle aç/kapa
    toggleBtn.addEventListener('click', function () {
        var label = toggleBtn.querySelector('[data-mlib-toggle-label]');
        if (bodyEl.classList.contains('d-none')) {
            bodyEl.classList.remove('d-none');
            if (label) label.textContent = 'Gizle';
            toggleBtn.querySelector('i').className = 'bi bi-eye-slash me-1';
            applyModeUi();
        } else {
            bodyEl.classList.add('d-none');
            if (label) label.textContent = 'Göster';
            toggleBtn.querySelector('i').className = 'bi bi-eye me-1';
        }
    });

    // Clipboard kopyalama
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var txt = promptText.value;
            var notify = function (success) {
                if (success) {
                    if (typeof showToast === 'function') {
                        showToast('Prompt panoya kopyalandı.', 'success');
                    }
                    var origHtml = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<i class="bi bi-check2 me-1"></i>Kopyalandı';
                    copyBtn.disabled = true;
                    setTimeout(function () {
                        copyBtn.innerHTML = origHtml;
                        copyBtn.disabled = false;
                    }, 1800);
                } else {
                    if (window.AdminModal && typeof AdminModal.status === 'function') {
                        AdminModal.status({
                            title: 'Kopyalanamadı',
                            message: 'Tarayıcı izin vermedi. Metni elle seçip Ctrl+C ile kopyalayın.',
                            type: 'warning',
                        });
                    }
                }
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(txt).then(
                    function () { notify(true); },
                    function () { fallbackCopy(); }
                );
            } else {
                fallbackCopy();
            }

            function fallbackCopy() {
                try {
                    promptText.removeAttribute('readonly');
                    promptText.select();
                    var ok = document.execCommand('copy');
                    promptText.setAttribute('readonly', 'readonly');
                    notify(ok);
                } catch (e) {
                    notify(false);
                }
            }
        });
    }
})();

function mlibConfirmDelete(btn) {
    var id = btn.getAttribute('data-asset-id');
    var title = btn.getAttribute('data-asset-title');
    var csrf = document.querySelector('meta[name="csrf-token"]').content;

    var doSubmit = function () {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/media-library/' + id;
        form.innerHTML =
            '<input type="hidden" name="_token" value="' + csrf + '">' +
            '<input type="hidden" name="_method" value="DELETE">';
        document.body.appendChild(form);
        form.submit();
    };

    if (window.AdminModal && typeof AdminModal.confirm === 'function') {
        AdminModal.confirm({
            title: 'Görsel Silinsin Mi?',
            message: '<strong>' + title + '</strong> kalıcı olarak silinecek (dosya + DB kaydı). Bu işlem geri alınamaz.',
            type: 'danger',
            confirmText: 'Evet, Sil',
            confirmIcon: 'bi bi-trash3',
        }).then(function (confirmed) {
            if (confirmed) doSubmit();
        });
    } else if (window.confirm('"' + title + '" silinsin mi?')) {
        doSubmit();
    }
}
</script>
@endpush
