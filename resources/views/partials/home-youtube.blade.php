{{-- YouTube Videos Section --}}
@if($youtubeVideos->isNotEmpty())
<section class="youtube-section" id="youtubeVideos" aria-labelledby="youtube-title">
    <div class="container">
        <div class="section-header">
            <span class="section-badge"><i class="fa-brands fa-youtube"></i> YouTube</span>
            <h2 class="section-title" id="youtube-title">Çiftlik Videoları</h2>
            <p class="section-subtitle">Kanalımızdaki en son videolarımızı izleyin</p>
        </div>

        <div class="row g-4">
            @foreach($youtubeVideos as $video)
            <div class="col-lg-4 col-md-6 animate-on-scroll">
                <div class="youtube-card" data-video-id="{{ $video->video_id }}">
                    <div class="youtube-card__thumb">
                        @if($video->thumbnail_url)
                        <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" class="youtube-card__img" width="480" height="360" loading="lazy" decoding="async">
                        @endif
                        <div class="youtube-card__play">
                            <i class="fa-solid fa-play"></i>
                        </div>
                        @if($video->formatted_duration)
                        <span class="youtube-card__duration">{{ $video->formatted_duration }}</span>
                        @endif
                    </div>
                    <div class="youtube-card__body">
                        <h3 class="youtube-card__title">{{ Str::limit($video->title, 65) }}</h3>
                        <div class="youtube-card__meta">
                            @if($video->published_at)
                            <span><i class="fa-regular fa-calendar"></i> {{ $video->published_at->translatedFormat('d M Y') }}</span>
                            @endif
                            <span><i class="fa-solid fa-eye"></i> {{ $video->formatted_view_count }} görüntülenme</span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @php $channelUrl = \App\Models\Setting::getValue('youtube_channel_id') ? 'https://www.youtube.com/channel/' . \App\Models\Setting::getValue('youtube_channel_id') : 'https://www.youtube.com/@orhanbabaninciftligicorum'; @endphp
        <div class="text-center mt-4">
            <a href="{{ $channelUrl }}" target="_blank" rel="noopener noreferrer" class="youtube-channel-link">
                <i class="fa-brands fa-youtube me-2"></i>Tüm Videoları İzle
                <i class="fa-solid fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>

{{-- YouTube Video Modal --}}
<div class="youtube-modal" id="youtubeModal" role="dialog" aria-label="Video oynatıcı">
    <div class="youtube-modal__backdrop"></div>
    <div class="youtube-modal__content">
        <button type="button" class="youtube-modal__close" aria-label="Kapat">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="youtube-modal__player">
            <iframe id="youtubePlayer" src="" frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    'use strict';
    var modal = document.getElementById('youtubeModal');
    var player = document.getElementById('youtubePlayer');
    if (!modal || !player) return;

    var backdrop = modal.querySelector('.youtube-modal__backdrop');
    var closeBtn = modal.querySelector('.youtube-modal__close');

    function openVideo(videoId) {
        player.src = 'https://www.youtube.com/embed/' + videoId + '?autoplay=1&rel=0';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeVideo() {
        modal.classList.remove('active');
        player.src = '';
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.youtube-card').forEach(function(card) {
        card.addEventListener('click', function() {
            var videoId = card.getAttribute('data-video-id');
            if (videoId) openVideo(videoId);
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeVideo);
    if (backdrop) backdrop.addEventListener('click', closeVideo);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeVideo();
        }
    });
})();
</script>
@endpush
@endif
