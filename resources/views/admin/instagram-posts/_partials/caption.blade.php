@php
    /** @var \App\Models\InstagramPost|null $post */
    /** @var bool $isEdit */
    /** @var bool $isPublished */
@endphp

<div class="card-dark mb-4">
    <div class="card-header-custom">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-teal"><i class="bi bi-chat-left-text"></i></div>
            <div>
                <h6 class="mb-0">Yazı (Caption)</h6>
                <small class="text-muted">Instagram caption — max 2000 karakter</small>
            </div>
        </div>
    </div>
    <div class="card-body-custom">
        <textarea name="caption" id="captionInput"
                  class="form-control @error('caption') is-invalid @enderror"
                  rows="6"
                  maxlength="2000"
                  placeholder="Paylaşmak istediğiniz yazıyı buraya girin..."
                  {{ $isPublished ? 'readonly' : '' }}
                  data-ig-counter="captionCounter"
                  data-ig-counter-warn="1800"
                  data-ig-counter-danger="1950">{{ old('caption', $isEdit ? $post->caption : '') }}</textarea>
        <div class="d-flex justify-content-between mt-1">
            <div class="form-text">Emoji, satır atlaması kullanabilirsiniz.</div>
            <div class="form-text"><span id="captionCounter">0</span>/2000</div>
        </div>
        @error('caption')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>
