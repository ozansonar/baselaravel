@php
    /** @var \App\Models\InstagramPost|null $post */
    /** @var bool $isEdit */
    /** @var bool $isPublished */
    /** @var string $mediaTypeValue */

    // Server-side visibility — FOUC engelleme: ilk yüklemede d-none
    $imageContexts = ['image', 'story'];
    $hiddenInitial = ! in_array($mediaTypeValue, $imageContexts, true);
@endphp

<div class="card-dark mb-4 {{ $hiddenInitial ? 'd-none' : '' }}" data-ig-context="image story">
    <div class="card-header-custom">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-purple"><i class="bi bi-image"></i></div>
            <div>
                <h6 class="mb-0">Görsel</h6>
                <small class="text-muted">Instagram'da paylaşılacak görsel (JPG, PNG, WebP — max 8 MB)</small>
            </div>
        </div>
    </div>
    <div class="card-body-custom">

        @if($isEdit && $post->image_path)
        <div class="mb-3">
            <label class="form-label">Mevcut Görsel</label>
            <div>
                <img src="{{ upload_url($post->image_path, 'md') }}"
                     alt="Mevcut görsel"
                     class="img-fluid rounded ig-existing-image">
            </div>
        </div>
        @endif

        <div>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <label class="form-label mb-0">
                    @if($isEdit) Görseli Değiştir (opsiyonel) @else Görsel Yükle <span class="text-danger">*</span> @endif
                </label>
                @if(! $isPublished)
                <div class="d-flex gap-2">
                    <button type="button" class="btn-glass btn-glass-sm" id="igVertexGalleryBtn" title="Vertex galerisinden seç">
                        <i class="bi bi-grid-3x3-gap me-1"></i> Vertex'ten Seç
                    </button>
                    <button type="button" class="btn-glass btn-glass-sm" id="igAiImageBtn" title="AI ile görsel üret">
                        <i class="bi bi-stars me-1"></i> AI ile Görsel Üret
                    </button>
                </div>
                @endif
            </div>

            {{-- AI ile üretilmiş görsel için hidden path --}}
            <input type="hidden" name="ai_image_path" id="aiImagePath" value="">

            @if(! $isPublished)
            <div class="ig-dropzone @error('image') ig-dropzone-error @enderror" id="mainImageDropzone">
                <input type="file" id="imageInput" name="image"
                       class="ig-dropzone-input @error('image') is-invalid @enderror"
                       accept="image/jpeg,image/png,image/webp">
                <div class="ig-dropzone-content" id="mainDropzoneContent">
                    <i class="bi bi-cloud-arrow-up ig-dropzone-icon"></i>
                    <strong>Görseli sürükle bırak</strong>
                    <small>veya tıkla, dosya seç</small>
                    <span class="ig-dropzone-meta">JPG/PNG/WebP — max 8 MB · min 320×320 · oran 4:5 ile 1.91:1 arası</span>
                </div>
                <div class="ig-dropzone-selected is-hidden" id="mainDropzoneSelected">
                    <img id="newImagePreview" src="" alt="Yeni görsel önizleme">
                    <div class="ig-dropzone-selected-info">
                        <strong id="mainDropzoneFileName"></strong>
                        <small id="mainDropzoneFileSize"></small>
                    </div>
                    <button type="button" class="ig-dropzone-remove" id="mainDropzoneRemove" title="Görseli kaldır">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            @error('image')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            @endif
        </div>

    </div>
</div>
