@php
    /** @var \App\Models\InstagramPost|null $post */
    /** @var bool $isEdit */
    /** @var bool $isPublished */
    /** @var string $mediaTypeValue */

    $videoContexts = ['reels', 'story'];
    $hiddenInitial = ! in_array($mediaTypeValue, $videoContexts, true);
@endphp

@if(! $isPublished)
<div class="card-dark mb-4 {{ $hiddenInitial ? 'd-none' : '' }}" data-ig-context="reels story">
    <div class="card-header-custom">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-red"><i class="bi bi-camera-reels"></i></div>
            <div>
                <h6 class="mb-0">Video</h6>
                <small class="text-muted">MP4 veya MOV, maksimum 100 MB. Reels için 90 sn, Story için 60 sn altında olmalı.</small>
            </div>
        </div>
    </div>
    <div class="card-body-custom">
        @if($isEdit && $post->video_path)
        <div class="mb-3">
            <label class="form-label">Mevcut Video</label>
            <video controls preload="metadata" class="ig-video-preview">
                <source src="{{ upload_url($post->video_path) }}" type="video/mp4">
                Tarayıcınız video oynatmayı desteklemiyor.
            </video>
            @if($post->video_duration_seconds)
                <small class="text-muted d-block mt-1">Süre: {{ $post->video_duration_seconds }} saniye</small>
            @endif
        </div>
        @endif

        <div>
            <label class="form-label">
                @if($isEdit && $post->video_path) Videoyu Değiştir (opsiyonel) @else Video Yükle @endif
            </label>
            <input type="file" name="video"
                   class="form-control @error('video') is-invalid @enderror"
                   accept="video/mp4,video/quicktime"
                   data-ig-video-input
                   data-reels-min="3" data-reels-max="90"
                   data-story-min="1" data-story-max="60">
            @error('video')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            <small class="form-text text-muted d-block mt-1">
                <i class="bi bi-info-circle me-1"></i>
                Reels için video zorunlu. Story için görsel veya video yeterli (ikisi de yüklenirse video önceliklidir).
            </small>
            {{-- Client-side duration validator feedback (JS doldurur) --}}
            <div class="mt-2 small d-none" data-ig-video-feedback></div>
        </div>
    </div>
</div>
@endif
