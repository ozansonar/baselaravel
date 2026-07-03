@php
    /** @var \App\Models\InstagramPost|null $post */
    /** @var bool $isEdit */
    /** @var bool $isPublished */
    /** @var string $mediaTypeValue */

    $hiddenInitial = $mediaTypeValue !== 'image';
    $existingCount = $isEdit ? $post->additionalImages->count() : 0;
    $remainingSlots = max(0, 9 - $existingCount);
@endphp

@if(! $isPublished)
<div class="card-dark mb-4 {{ $hiddenInitial ? 'd-none' : '' }}" data-ig-context="image">
    <div class="card-header-custom">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-pink"><i class="bi bi-images"></i></div>
            <div>
                <h6 class="mb-0">Carousel Ek Görseller (opsiyonel)</h6>
                <small class="text-muted">Çoklu görsel post için ek görseller ekle. Toplam max 10 görsel (1 ana + 9 ek). Boşsa tek görsel post olur.</small>
            </div>
        </div>
    </div>
    <div class="card-body-custom">
        @if($isEdit && $post->additionalImages->isNotEmpty())
        <div class="mb-3">
            <label class="form-label">Mevcut Ek Görseller ({{ $post->additionalImages->count() }})</label>
            <div class="ig-carousel-grid">
                @foreach($post->additionalImages as $img)
                    <div class="ig-carousel-item">
                        <img src="{{ upload_url($img->image_path, 'thumb') }}" alt="Ek görsel #{{ $img->sort_order }}">
                        <label class="ig-carousel-remove">
                            <input type="checkbox" name="remove_image_ids[]" value="{{ $img->id }}">
                            <span><i class="bi bi-trash"></i> Bu görseli sil</span>
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($remainingSlots > 0)
        <div>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <label class="form-label mb-0">
                    Yeni Ek Görsel Yükle <small class="text-muted">(max {{ $remainingSlots }} görsel daha)</small>
                </label>
                <button type="button" class="btn-glass btn-glass-sm" id="igVertexCarouselBtn" title="Vertex galerisinden çoklu seç">
                    <i class="bi bi-grid-3x3-gap me-1"></i> Vertex'ten Seç
                </button>
            </div>

            {{-- Vertex'ten seçilen carousel görselleri için hidden inputs --}}
            <div id="vtxCarouselPaths"></div>

            {{-- Vertex'ten seçilen görsellerin önizlemesi --}}
            <div id="vtxCarouselPreview" class="ig-carousel-grid mb-3 d-none"></div>

            <div class="ig-dropzone ig-dropzone-multi @error('additional_images.*') ig-dropzone-error @enderror" id="carouselDropzone" data-max="{{ $remainingSlots }}">
                <input type="file"
                       id="additionalImagesInput"
                       name="additional_images[]"
                       class="ig-dropzone-input"
                       accept="image/jpeg,image/png,image/webp"
                       multiple>
                <div class="ig-dropzone-content" id="carouselDropzoneContent">
                    <i class="bi bi-images ig-dropzone-icon"></i>
                    <strong>Ek görselleri sürükle bırak</strong>
                    <small>çoklu seçim yapabilirsin (max {{ $remainingSlots }} dosya)</small>
                    <span class="ig-dropzone-meta">JPG/PNG/WebP — max 8 MB her biri</span>
                </div>
            </div>
            @error('additional_images.*')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror

            <div class="mt-3 ig-carousel-preview" id="carouselPreviewWrap"></div>
        </div>
        @else
        <p class="text-muted mb-0">Maksimum görsel sayısına ulaşıldı. Yenisini eklemek için mevcutlardan birini sil.</p>
        @endif
    </div>
</div>
@endif
