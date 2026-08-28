@extends('layouts.app')

@section('title', __('site.nav.gallery'))
@section('meta_description', __('site.gallery.meta_desc'))
@section('canonical', url()->current())

@section('content')

    {{-- ══════════ PAGE HERO ══════════ --}}
    <section class="page-hero">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('site.nav.home') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ __('site.nav.gallery') }}</li>
                </ol>
            </nav>
            <span class="page-hero__eyebrow"><i class="fa-solid fa-images"></i> {{ __('site.gallery.title') }}</span>
            <h1 class="page-hero__title">{{ __('site.nav.gallery') }}</h1>
            <p class="page-hero__lead">{{ __('site.gallery.lead') }}</p>
        </div>
    </section>

    {{-- ══════════ GALLERY ══════════ --}}
    <section class="section--tight" aria-labelledby="gallery-heading">
        <div class="container">
            <h2 class="visually-hidden" id="gallery-heading">{{ __('site.nav.gallery') }}</h2>

            @if($photos->isEmpty() && $videos->isEmpty())
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="fa-regular fa-images"></i></div>
                    <p class="mb-0">{{ __('site.gallery.empty') }}</p>
                </div>
            @else

                {{-- Photos --}}
                @if($photos->isNotEmpty())
                    <div class="mb-3">
                        <span class="section__eyebrow"><i class="fa-solid fa-images"></i> {{ __('site.gallery.photos') }}</span>
                    </div>
                    {{-- Fotoğraflar sayfadan ayrılmadan büyüyor: yeni sekmede
                         açılan ham dosya galeriyi terk ettiriyordu. --}}
                    <div class="gallery-grid gallery-grid--masonry mb-5" data-lightbox-gallery>
                        @foreach($photos as $photo)
                            @if($photo->image)
                                <a href="{{ upload_url($photo->image) }}"
                                   class="gallery-item"
                                   data-lightbox-item
                                   data-title="{{ $photo->title }}"
                                   data-caption="{{ $photo->galleryCategory?->name }}"
                                   aria-label="{{ $photo->title }} — {{ __('site.gallery.view') }}">
                                    <img src="{{ upload_url($photo->image, 'md') }}" alt="{{ $photo->title }}"
                                         class="gallery-item__img" loading="lazy" decoding="async">
                                    <span class="gallery-item__zoom" aria-hidden="true"><i class="fa-solid fa-expand"></i></span>
                                    <span class="gallery-item__overlay">
                                        <span>
                                            <strong class="d-block">{{ $photo->title }}</strong>
                                            @if($photo->galleryCategory)
                                                <small>{{ $photo->galleryCategory->name }}</small>
                                            @endif
                                        </span>
                                    </span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- Videos --}}
                @if($videos->isNotEmpty())
                    <div class="mb-3">
                        <span class="section__eyebrow"><i class="fa-solid fa-video"></i> {{ __('site.gallery.videos') }}</span>
                    </div>
                    <div class="gallery-grid">
                        @foreach($videos as $video)
                            @if($video->video_url)
                                {{-- Video dış bir adrese gidiyor, bu yüzden büyütme
                                     penceresine değil yeni sekmeye açılıyor. --}}
                                <a href="{{ $video->video_url }}" target="_blank" rel="noopener"
                                   class="gallery-item" aria-label="{{ $video->title }} — {{ __('site.gallery.watch') }}">
                                    @if($video->image)
                                        <img src="{{ upload_url($video->image, 'md') }}" alt="{{ $video->title }}"
                                             class="gallery-item__img" loading="lazy" decoding="async">
                                    @else
                                        <span class="post-card__ph"><i class="fa-regular fa-image"></i></span>
                                    @endif
                                    <span class="gallery-item__play" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                                    <span class="gallery-item__overlay">
                                        <span>
                                            <strong class="d-block">{{ $video->title }}</strong>
                                            @if($video->galleryCategory)
                                                <small>{{ $video->galleryCategory->name }}</small>
                                            @endif
                                        </span>
                                    </span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif

            @endif
        </div>
    </section>

@endsection
