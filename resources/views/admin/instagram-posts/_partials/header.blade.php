@php
    /** @var \App\Models\InstagramPost|null $post */
    /** @var bool $isEdit */
    /** @var bool $isPublished */
@endphp

<div class="d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('admin.instagram-posts.index') }}" class="btn-glass" title="Geri Dön">
            <i class="bi bi-arrow-left"></i>
        </a>
        <button type="button" class="btn-glass" data-bs-toggle="modal" data-bs-target="#igGuideModal" title="İçerik standartları">
            <i class="bi bi-info-circle me-1"></i> İçerik Rehberi
        </button>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if(! $isPublished)
            <button type="submit" class="btn-glass" data-ig-action="save_draft">
                <i class="bi bi-file-earmark me-1"></i> Taslak Kaydet
            </button>
            <button type="submit" class="btn-glass" data-ig-action="schedule">
                <i class="bi bi-calendar-event me-1"></i> Planla
            </button>
            <button type="submit" class="btn-teal" data-ig-action="publish_now">
                <i class="bi bi-send-fill me-1"></i> Şimdi Paylaş
            </button>
        @else
            <span class="usr-status-badge active">
                <i class="bi bi-check-circle-fill me-1"></i> Yayınlandı
            </span>
        @endif
    </div>
</div>
