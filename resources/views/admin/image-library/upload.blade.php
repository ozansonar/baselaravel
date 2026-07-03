@extends('layouts.admin')

@section('title', 'Görsel Yükle — Kütüphane')

@section('content')
<div class="container-fluid py-4 il-upload-page">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-cloud-upload text-warning me-2"></i> Görsel Yükle
            </h2>
            <small class="text-muted">Drag-drop ile asenkron toplu yükleme — her dosya ayrı ayrı işlenir, paralel</small>
        </div>
        <a href="{{ route('admin.image-library.index') }}" class="btn-glass">
            <i class="bi bi-arrow-left me-1"></i> Listeye Dön
        </a>
    </div>

    {{-- Kullanım rehberi --}}
    <div class="alert alert-info il-help">
        <strong><i class="bi bi-info-circle me-1"></i> Nasıl çalışır?</strong>
        <ol class="mb-0 mt-2 ps-4 small">
            <li>Aşağıdaki formdaki <strong>ortak ayarları</strong> doldur (medya tipi, tema, mood, mevsim, tag) — bu yüklenecek tüm dosyalar için geçerli</li>
            <li>Video yüklemek istersen önce <strong>Video Posteri</strong> alanına thumbnail görseli yükle</li>
            <li><strong>Görsel/video alanına</strong> dosyaları sürükle veya tıkla — her dosya <strong>otomatik ve paralel</strong> yüklenir (max 6 eşzamanlı)</li>
            <li>Yüklendikçe alt panelde başarı/hata satırları birikir, sayfa yenilenmez</li>
            <li>Tüm dosyalar yüklenince üstte "Listeye Dön" ile yönetebilirsin</li>
        </ol>
    </div>

    <div class="row g-4">
        {{-- Sol: Form-level ayarlar --}}
        <div class="col-lg-4">
            <div class="card-dark il-upload-settings">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-blue"><i class="bi bi-sliders"></i></div>
                        <div>
                            <h6 class="mb-0">Ortak Ayarlar</h6>
                            <small class="text-muted">Yüklenecek tüm dosyalar için</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="mb-3">
                        <label class="form-label">Medya Tipi <span class="text-danger">*</span></label>
                        <select id="ilUploadMediaType" class="form-select form-control-theme">
                            <option value="story">📍 Story (1080×1920)</option>
                            <option value="feed" selected>📷 Feed Post (1080×1080)</option>
                            <option value="reels">🎬 Reels (1080×1920)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tema</label>
                        <select id="ilUploadTheme" class="form-select form-control-theme">
                            <option value="">— Tema yok —</option>
                            @foreach(\App\Models\MediaLibraryImage::THEMES as $th)
                                <option value="{{ $th }}">{{ (new \App\Models\MediaLibraryImage(['theme' => $th]))->themeLabel() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Mood</label>
                            <select id="ilUploadMood" class="form-select form-control-theme">
                                <option value="">— yok —</option>
                                @foreach(\App\Models\MediaLibraryImage::MOODS as $m)
                                    <option value="{{ $m }}">{{ ucfirst($m) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mevsim</label>
                            <select id="ilUploadSeason" class="form-select form-control-theme">
                                <option value="">— yok —</option>
                                @foreach(\App\Models\MediaLibraryImage::SEASONS as $s)
                                    <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label">Görünürlük</label>
                        <select id="ilUploadVisibility" class="form-select form-control-theme">
                            <option value="private" selected>🔒 Sadece Bulk</option>
                            <option value="public">🌐 Halka Açık</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tag'ler</label>
                        <input type="text" id="ilUploadTags" class="form-control form-control-theme" maxlength="500"
                               placeholder="süt, sabah, sağım, kahvaltı">
                        <small class="form-text text-muted">Virgülle ayır</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea id="ilUploadDescription" class="form-control form-control-theme" rows="2" maxlength="2000"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Alt Text</label>
                        <input type="text" id="ilUploadAlt" class="form-control form-control-theme" maxlength="250">
                    </div>
                </div>
            </div>

            {{-- Video Posteri --}}
            <div class="card-dark mt-3 il-upload-poster">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-purple"><i class="bi bi-camera-reels"></i></div>
                        <div>
                            <h6 class="mb-0">Video Posteri</h6>
                            <small class="text-muted">Sadece video yüklerken zorunlu</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div id="ilPosterStatus" class="il-poster-status">
                        <i class="bi bi-image text-muted"></i>
                        <small class="text-muted">Henüz poster yüklenmedi</small>
                    </div>
                    <input type="file" id="ilPosterFile" class="form-control form-control-theme mt-2"
                           accept=".jpg,.jpeg,.png,.webp">
                    <small class="form-text text-muted mt-1">
                        Video yüklemek istiyorsan önce buradan poster (1080×1080 önerilen) yükle.
                        Yüklenen video'lar bu poster'ı thumbnail olarak kullanır.
                    </small>
                </div>
            </div>
        </div>

        {{-- Sağ: Dropzone --}}
        <div class="col-lg-8">
            <div class="card-dark">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-green"><i class="bi bi-cloud-upload"></i></div>
                        <div>
                            <h6 class="mb-0">Dosya Yükleme</h6>
                            <small class="text-muted">Sürükle bırak veya tıkla — paralel asenkron upload</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <form id="ilDropzone"
                          action="{{ route('admin.image-library.upload-single') }}"
                          class="dropzone il-dropzone needsclick">
                        @csrf
                        <div class="dz-message needsclick">
                            <div class="il-dz-icon"><i class="bi bi-cloud-arrow-up-fill"></i></div>
                            <h5 class="mb-2">Dosyaları buraya sürükle veya tıkla</h5>
                            <p class="text-muted mb-0">
                                Görsel: jpg/png/webp (max 8 MB) · Video: mp4/mov (max 100 MB)<br>
                                <small>Her dosya ayrı ayrı, paralel olarak yüklenir</small>
                            </p>
                        </div>
                    </form>

                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn-glass btn-glass-sm" id="ilDzClearBtn" disabled>
                            <i class="bi bi-x-lg me-1"></i> Tümünü Kaldır
                        </button>
                        <span class="ms-auto text-muted small d-flex align-items-center" id="ilDzCounter">
                            0 dosya
                        </span>
                    </div>

                    <div id="ilUploadResult" class="mt-3 d-none"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.il-help { background: rgba(13,202,240,0.06); border: 1px solid rgba(13,202,240,0.2); color: #cfe9f7; }
.il-help strong { color: #93c5fd; }

/* Dropzone area styling */
.il-dropzone.dropzone {
    background: rgba(0,0,0,0.3);
    border: 2px dashed rgba(245,158,11,0.4);
    border-radius: 12px;
    min-height: 280px;
    padding: 30px 20px;
    transition: all 0.2s ease;
    color: #e5e7eb;
}
.il-dropzone.dropzone:hover,
.il-dropzone.dropzone.dz-drag-hover {
    background: rgba(245,158,11,0.08);
    border-color: #f59e0b;
}
.il-dropzone .dz-message { text-align: center; margin: 1em 0; }
.il-dropzone h5 { color: #fcd34d; }
.il-dz-icon {
    font-size: 3.5rem;
    color: rgba(245,158,11,0.6);
    margin-bottom: 12px;
    line-height: 1;
}

/* Dropzone preview overrides — dark theme */
.il-dropzone .dz-preview {
    background: rgba(0,0,0,0.5);
    border-radius: 8px;
    overflow: hidden;
    margin: 8px;
}
.il-dropzone .dz-preview .dz-image { border-radius: 8px; }
.il-dropzone .dz-preview .dz-details {
    background: linear-gradient(transparent, rgba(0,0,0,0.85));
    color: #fff;
}
.il-dropzone .dz-preview .dz-details .dz-filename span,
.il-dropzone .dz-preview .dz-details .dz-size span {
    background: transparent;
    padding: 0;
    color: #e5e7eb;
}
.il-dropzone .dz-preview .dz-progress {
    background: rgba(255,255,255,0.15);
}
.il-dropzone .dz-preview .dz-progress .dz-upload {
    background: linear-gradient(90deg, #fbbf24, #f59e0b);
}
.il-dropzone .dz-preview.dz-success .dz-success-mark { color: #10b981; }
.il-dropzone .dz-preview.dz-error .dz-error-message { background: rgba(239,68,68,0.92); color: #fff; }
.il-dropzone .dz-preview .dz-remove {
    color: #fca5a5;
    font-weight: 600;
}
.il-dropzone .dz-preview .dz-remove:hover { color: #fff; }

/* Poster status */
.il-poster-status {
    background: rgba(0,0,0,0.4);
    border: 1px dashed rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 16px;
    text-align: center;
    transition: all 0.2s;
}
.il-poster-status.is-loaded {
    border-color: rgba(34,197,94,0.4);
    background: rgba(34,197,94,0.06);
}
.il-poster-status img {
    max-width: 120px;
    max-height: 120px;
    border-radius: 6px;
    object-fit: cover;
}

/* Upload result panel */
.il-upload-result {
    background: rgba(0,0,0,0.4);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 8px;
    padding: 12px 16px;
    max-height: 300px;
    overflow-y: auto;
}
.il-upload-result__title {
    color: #fcd34d; font-weight: 600; margin-bottom: 8px;
    display: flex; align-items: center; justify-content: space-between;
}
.il-upload-result__list { list-style: none; padding: 0; margin: 0; }
.il-upload-result__list li {
    padding: 4px 0;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 0.88rem;
}
.il-upload-result__list li:last-child { border-bottom: none; }
.il-upload-result__list .text-success { color: #86efac !important; }
.il-upload-result__list .text-danger  { color: #fca5a5 !important; }
.il-upload-result__clear {
    background: none; border: none;
    color: #9ca3af; font-size: 0.78rem;
    text-decoration: underline;
}
.il-upload-result__clear:hover { color: #fff; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('assets/admin/js/image-library-upload.js') }}"></script>
@endpush
