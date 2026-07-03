@php
    /** @var bool $isPublished */
    /** @var string $hashtagsStr */
@endphp

<div class="card-dark mb-4">
    <div class="card-header-custom">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-orange"><i class="bi bi-hash"></i></div>
            <div>
                <h6 class="mb-0">Hashtag'ler</h6>
                <small class="text-muted">Virgül veya boşlukla ayırın. # koymasanız da olur. Max 30 adet.</small>
            </div>
        </div>
    </div>
    <div class="card-body-custom">
        <input type="text" name="hashtags" id="hashtagsInput"
               class="form-control @error('hashtags') is-invalid @enderror"
               value="{{ $hashtagsStr }}"
               placeholder="Ör: organik köyürünleri doğal çiftlik"
               {{ $isPublished ? 'readonly' : '' }}>
        @error('hashtags')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        <div class="form-text mt-2">
            <strong>Önizleme:</strong>
            <span id="hashtagPreview" class="text-teal"></span>
        </div>
    </div>
</div>
