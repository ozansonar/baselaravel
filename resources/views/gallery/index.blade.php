@extends('layouts.app')

@section('title', 'Galeri | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Çiftliğimizden fotoğraf ve video galerisi. Doğal yaşamı keşfedin. ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('canonical', route('gallery'))
@if(\App\Models\Setting::getValue('og_image'))
@section('og_image', url(upload_url(\App\Models\Setting::getValue('og_image'))))
@endif

@push('json-ld')
@php
    $galleryBreadcrumbJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Galeri'],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($galleryBreadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')

{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <nav class="breadcrumb-custom animate-fade-up" aria-label="Breadcrumb">
            <a href="{{ route('home') }}"><i class="fa-solid fa-house"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Galeri</span>
        </nav>
        <h1 class="page-title animate-fade-up delay-1">Galeri</h1>
        <p class="page-subtitle animate-fade-up delay-2">Çiftliğimizden kareler ve videolar</p>
    </div>
</header>

{{-- Gallery Section --}}
<section class="gallery-section" aria-labelledby="gallery-heading">
    <h2 class="visually-hidden" id="gallery-heading">Galeri</h2>
    <div class="container">
        {{-- Tab Navigation --}}
        <div class="gallery-tabs animate-fade-up">
            <button class="gallery-tab active" data-tab="photos" type="button">
                <i class="fa-solid fa-images"></i>
                Fotoğraflar
            </button>
            <button class="gallery-tab" data-tab="videos" type="button">
                <i class="fa-solid fa-video"></i>
                Videolar
            </button>
        </div>

        {{-- Photo Gallery --}}
        <div class="gallery-content active" id="photos">
            {{-- Category Filter --}}
            <div class="category-filter animate-fade-up delay-1">
                <button class="filter-btn active" data-filter="all" type="button">Tümü</button>
                @foreach ($categories as $cat)
                    <button class="filter-btn" data-filter="{{ $cat->slug }}" type="button">{{ $cat->name }}</button>
                @endforeach
            </div>

            {{-- Photo Grid --}}
            <div class="photo-grid animate-fade-up delay-2">
                @forelse ($photos as $photo)
                    <figure class="photo-item" data-category="{{ $photo->galleryCategory?->slug }}" data-title="{{ $photo->title }}" data-desc="{{ $photo->description }}">
                        @if ($photo->image)
                            <x-responsive-image :path="$photo->image" :alt="$photo->title" size="md" />
                        @else
                            <i class="fa-solid fa-image"></i>
                        @endif
                        <figcaption class="photo-overlay">
                            <span class="photo-title">{{ $photo->title }}</span>
                            <span class="photo-category">{{ $photo->galleryCategory?->name }}</span>
                        </figcaption>
                        <div class="photo-zoom"><i class="fa-solid fa-magnifying-glass-plus"></i></div>
                    </figure>
                @empty
                    <div class="gallery-empty-state text-center py-5 w-100">
                        <i class="fa-solid fa-images fa-3x mb-3 text-muted"></i>
                        <p class="text-muted">Henüz fotoğraf eklenmemiş.</p>
                    </div>
                @endforelse
            </div>

            {{-- Empty Filter State --}}
            <div class="gallery-filter-empty text-center py-5 d-none" id="photoFilterEmpty">
                <i class="fa-solid fa-filter-circle-xmark fa-3x mb-3 text-muted"></i>
                <p class="text-muted">Bu kategoride henüz fotoğraf bulunmuyor.</p>
            </div>
        </div>

        {{-- Video Gallery --}}
        <div class="gallery-content" id="videos">
            <div class="video-grid animate-fade-up">
                @forelse ($videos as $video)
                    <article class="video-item">
                        <div class="video-thumbnail" data-video-url="{{ $video->video_url }}">
                            @if ($video->image)
                                <x-responsive-image :path="$video->image" :alt="$video->title" size="md" />
                            @else
                                <i class="fa-solid fa-film"></i>
                            @endif
                            <div class="video-play-btn"><i class="fa-solid fa-play"></i></div>
                            @if ($video->duration)
                                <span class="video-duration">{{ $video->duration }}</span>
                            @endif
                        </div>
                        <div class="video-info">
                            <h4 class="video-title">{{ $video->title }}</h4>
                            <div class="video-meta">
                                <time datetime="{{ $video->created_at->format('Y-m-d') }}"><i class="fa-solid fa-calendar"></i> {{ $video->created_at->translatedFormat('d F Y') }}</time>
                                <span><i class="fa-solid fa-eye"></i> {{ number_format($video->view_count) }} görüntüleme</span>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="text-center py-5 w-100">
                        <i class="fa-solid fa-video fa-3x mb-3 text-muted"></i>
                        <p class="text-muted">Henüz video eklenmemiş.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</section>

{{-- Lightbox --}}
<div class="lightbox" id="lightbox" role="dialog" aria-label="Fotoğraf görüntüleyici" aria-hidden="true">
    <div class="lightbox-content">
        <button class="lightbox-close" type="button" aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button>
        <button class="lightbox-nav lightbox-prev" type="button" aria-label="Önceki fotoğraf"><i class="fa-solid fa-chevron-left"></i></button>
        <div class="lightbox-image" id="lightboxImage">
            <i class="fa-solid fa-image"></i>
        </div>
        <button class="lightbox-nav lightbox-next" type="button" aria-label="Sonraki fotoğraf"><i class="fa-solid fa-chevron-right"></i></button>
        <div class="lightbox-caption">
            <h4 id="lightboxTitle">Fotoğraf Başlığı</h4>
            <p id="lightboxDesc">Fotoğraf açıklaması</p>
        </div>
    </div>
</div>

{{-- Video Modal --}}
<div class="video-modal" id="videoModal" role="dialog" aria-label="Video oynatıcı" aria-hidden="true">
    <div class="video-modal-content">
        <button class="video-modal-close" type="button" aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button>
        <div class="video-container" id="videoContainer">
            <i class="fa-solid fa-play-circle"></i>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ versioned_asset('js/gallery.js') }}" defer></script>
@endpush
