{{--
    Blog kartı — ana sayfa, blog listesi ve ilgili içerikler aynı kartı
    kullanıyor. Üç yerde ayrı ayrı yazılıydı; biri düzeltildiğinde ötekiler
    geride kalıyordu.

    @var \App\Models\BlogPost $post
    @var bool $featured Geniş "öne çıkan" sürüm (yatay düzen)
    @var bool $showExcerpt Özet basılsın mı
    @var string $titleTag Başlık etiketi — sayfadaki başlık sırasına göre
--}}
@php
    $featured    = $featured ?? false;
    $showExcerpt = $showExcerpt ?? true;
    // Kart bazen h2 (liste sayfası, h1 sayfa başlığı) bazen h3 (ana sayfa,
    // h2 bölüm başlığı) taşımalı; sıra bozulursa erişilebilirlik kırılıyor.
    $titleTag    = $titleTag ?? 'h3';
    $postUrl     = route('blog.show', [$post->category->slug, $post->slug]);

    // Okuma süresi yalnız gövde yüklendiğinde hesaplanıyor; ilgili içerikler
    // gibi gövdesiz gelen listelerde satır hiç basılmıyor.
    $readMinutes = null;
    if (filled($post->body ?? null)) {
        $wordCount   = count(preg_split('/\s+/u', trim(strip_tags((string) $post->body))) ?: []);
        $readMinutes = max(1, (int) ceil($wordCount / 200));
    }
@endphp

<article class="post-card {{ $featured ? 'post-card--featured' : '' }}">
    <a href="{{ $postUrl }}" class="post-card__media" tabindex="-1" aria-hidden="true">
        @if($post->image)
            <img src="{{ upload_url($post->image, $featured ? 'lg' : 'md') }}" alt="{{ $post->title }}"
                 class="post-card__img" loading="lazy" decoding="async">
        @else
            <span class="post-card__ph"><i class="fa-regular fa-image"></i></span>
        @endif
        @if($featured)
            <span class="post-card__flag"><i class="fa-solid fa-star"></i> {{ __('site.blog.featured') }}</span>
        @endif
        @if($post->category)
            <span class="post-card__cat">{{ $post->category->name }}</span>
        @endif
    </a>
    <div class="post-card__body">
        <div class="post-card__meta">
            <span><i class="fa-regular fa-calendar me-1"></i>{{ optional($post->published_at)->translatedFormat('d M Y') }}</span>
            @if($readMinutes !== null)
                <span><i class="fa-regular fa-clock me-1"></i>{{ __('site.blog.read_time', ['count' => $readMinutes]) }}</span>
            @endif
            <span><i class="fa-regular fa-eye me-1"></i>{{ number_format((int) $post->views) }}</span>
        </div>
        <{{ $titleTag }} class="post-card__title">
            <a href="{{ $postUrl }}">{{ $post->title }}</a>
        </{{ $titleTag }}>
        @if($showExcerpt && $post->excerpt)
            <p class="post-card__excerpt">{{ \Illuminate\Support\Str::limit($post->excerpt, $featured ? 190 : 110) }}</p>
        @endif
        <a href="{{ $postUrl }}" class="post-card__more">{{ __('site.actions.read_more') }} <i class="fa-solid fa-arrow-right"></i></a>
    </div>
</article>
