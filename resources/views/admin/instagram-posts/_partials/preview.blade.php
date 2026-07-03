@php
    /** @var \App\Models\InstagramPost|null $post */
    /** @var bool $isEdit */
    /** @var array $previewSettings */
    /** @var string $mediaTypeValue */

    $previewMainImg  = $isEdit && $post->image_path ? upload_url($post->image_path, 'lg') : null;
    $previewCaption  = old('caption', $isEdit ? (string) $post->caption : '');
    $previewHashtags = old('hashtags', $isEdit && is_array($post->hashtags) ? implode(' ', $post->hashtags) : '');
    $previewExtraImgs = $isEdit ? $post->additionalImages->map(fn($i) => upload_url($i->image_path, 'lg'))->all() : [];

    // Story bg fallback için video varsa poster kullanılabilir; şu an sadece görsel destekliyor
    $hasMedia = $previewMainImg !== null;
@endphp

<div class="card-dark mb-4 ig-preview-card">
    <div class="card-header-custom">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-pink"><i class="bi bi-eye"></i></div>
            <div>
                <h6 class="mb-0">Instagram Önizleme</h6>
                <small class="text-muted">Post nasıl görünecek (medya tipine göre değişir)</small>
            </div>
        </div>
    </div>
    <div class="card-body-custom p-0">
        <div class="ig-preview"
             id="igPreview"
             data-username="{{ $previewSettings['ig_username'] }}"
             data-ig-mode="{{ $mediaTypeValue }}">

            {{-- ═══ Feed (image) header ═══ --}}
            <div class="ig-preview-header">
                <div class="ig-preview-avatar">
                    @if($previewSettings['logo_url'])
                        <img src="{{ $previewSettings['logo_url'] }}" alt="{{ $previewSettings['site_name'] }}">
                    @else
                        <i class="bi bi-person-circle"></i>
                    @endif
                </div>
                <div class="ig-preview-username">{{ $previewSettings['ig_username'] }}</div>
                <div class="ig-preview-more"><i class="bi bi-three-dots"></i></div>
            </div>

            {{-- ═══ Media (her modda var, mode'a göre aspect-ratio değişir) ═══ --}}
            <div class="ig-preview-media" id="igPreviewMedia">
                @if($previewMainImg)
                    <img id="igPreviewMainImg" src="{{ $previewMainImg }}" alt="Preview" data-existing-images='@json($previewExtraImgs)'>
                @else
                    <div class="ig-preview-placeholder" id="igPreviewPlaceholder">
                        <i class="bi bi-image"></i>
                        <span>Görsel yüklendiğinde burada görünecek</span>
                    </div>
                @endif

                {{-- Video önizlemesi (Reels/Story modunda kullanıcı video seçince JS açar) --}}
                <video id="igPreviewVideo" class="ig-preview-video d-none"
                       controls muted loop playsinline preload="metadata"></video>

                <div class="ig-preview-carousel-dots is-hidden" id="igPreviewDots"></div>

                {{-- Reels rozeti (sadece reels mode'da görünür) --}}
                <div class="ig-preview-reels-badge" aria-hidden="true">
                    <i class="bi bi-camera-reels-fill"></i>
                    <span>Reels</span>
                </div>

                {{-- Story progress bar (sadece story mode'da görünür) --}}
                <div class="ig-preview-story-bars" aria-hidden="true">
                    <span class="ig-preview-story-bar"></span>
                </div>

                {{-- Story üst overlay: avatar + username + zaman + X --}}
                <div class="ig-preview-story-header" aria-hidden="true">
                    <div class="ig-preview-story-avatar">
                        @if($previewSettings['logo_url'])
                            <img src="{{ $previewSettings['logo_url'] }}" alt="">
                        @else
                            <i class="bi bi-person-circle"></i>
                        @endif
                    </div>
                    <span class="ig-preview-story-username">{{ $previewSettings['ig_username'] }}</span>
                    <span class="ig-preview-story-time">şimdi</span>
                    <span class="ig-preview-story-close"><i class="bi bi-x-lg"></i></span>
                </div>

                {{-- Reels sağ aksiyon barı --}}
                <div class="ig-preview-reels-actions" aria-hidden="true">
                    <span class="ig-preview-reels-avatar">
                        @if($previewSettings['logo_url'])
                            <img src="{{ $previewSettings['logo_url'] }}" alt="">
                        @else
                            <i class="bi bi-person-circle"></i>
                        @endif
                    </span>
                    <span><i class="bi bi-heart-fill"></i></span>
                    <span><i class="bi bi-chat-fill"></i></span>
                    <span><i class="bi bi-send-fill"></i></span>
                    <span><i class="bi bi-three-dots-vertical"></i></span>
                </div>

                {{-- Reels alt caption overlay --}}
                <div class="ig-preview-reels-bottom" aria-hidden="true">
                    <strong>{{ $previewSettings['ig_username'] }}</strong>
                    <span class="ig-preview-overlay-caption-text" id="igPreviewOverlayCaptionText">{{ $previewCaption }}</span>
                    <span class="ig-preview-overlay-hashtags" id="igPreviewOverlayHashtags"></span>
                </div>

                {{-- Story alt mesaj çubuğu (cosmetic) --}}
                <div class="ig-preview-story-footer" aria-hidden="true">
                    <span class="ig-preview-story-msg">Mesaj gönder…</span>
                    <i class="bi bi-heart"></i>
                    <i class="bi bi-send"></i>
                </div>
            </div>

            {{-- ═══ Feed alt: aksiyon + likes + caption + hashtag + zaman ═══ --}}
            <div class="ig-preview-actions">
                <i class="bi bi-heart"></i>
                <i class="bi bi-chat"></i>
                <i class="bi bi-send"></i>
                <i class="bi bi-bookmark ig-preview-bookmark"></i>
            </div>

            <div class="ig-preview-likes" id="igPreviewLikes">— beğenme</div>

            <div class="ig-preview-caption" id="igPreviewCaption">
                <strong>{{ $previewSettings['ig_username'] }}</strong>
                <span id="igPreviewCaptionText">{{ $previewCaption ?: 'Caption yazılınca burada görünecek...' }}</span>
            </div>

            <div class="ig-preview-hashtags" id="igPreviewHashtags">
                @if($previewHashtags)
                    @foreach(preg_split('/[\s,]+/', $previewHashtags) as $tag)
                        @php $tag = trim($tag, " #\t\n\r\0\x0B"); @endphp
                        @if($tag !== '')
                            <a href="#">#{{ $tag }}</a>
                        @endif
                    @endforeach
                @endif
            </div>

            <div class="ig-preview-time">{{ now()->translatedFormat('d F Y') }}</div>
        </div>
    </div>
</div>
