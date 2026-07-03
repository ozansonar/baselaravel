@php
    /** @var string $mediaTypeValue */
    /** @var bool $isPublished */
@endphp

@if(! $isPublished)
<div class="card-dark mb-4">
    <div class="card-header-custom">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-blue"><i class="bi bi-collection-play"></i></div>
            <div>
                <h6 class="mb-0">Medya Tipi</h6>
                <small class="text-muted">Hangi formatta paylaşılacak — Feed Post, Reels veya Story</small>
            </div>
        </div>
    </div>
    <div class="card-body-custom">
        <div class="ig-media-type-grid">
            @foreach(\App\Enums\InstagramMediaType::cases() as $type)
                <label class="ig-media-type-card {{ $mediaTypeValue === $type->value ? 'is-active' : '' }}">
                    <input type="radio" name="media_type" value="{{ $type->value }}"
                           class="ig-media-type-radio"
                           {{ $mediaTypeValue === $type->value ? 'checked' : '' }}>
                    <span class="ig-media-type-icon"><i class="bi {{ $type->icon() }}"></i></span>
                    <span class="ig-media-type-label">{{ $type->label() }}</span>
                    <small class="ig-media-type-hint">
                        @if($type === \App\Enums\InstagramMediaType::Image)
                            Görsel + carousel (max 10) + Facebook cross-post
                        @elseif($type === \App\Enums\InstagramMediaType::Reels)
                            MP4 video (max 90 sn, 100 MB) — feed'de + Reels tab'ında görünür, kalıcı
                        @else
                            Görsel veya video — 24 saat sonra Meta tarafında silinir
                        @endif
                    </small>
                </label>
            @endforeach
        </div>
    </div>
</div>
@else
    <input type="hidden" name="media_type" value="{{ $mediaTypeValue }}">
@endif
