@extends('layouts.app')

@section('title', 'Galeri')
@section('meta_description', 'Fotoğraf ve video galerisi. Kareler ve videolarla bize daha yakından bakın.')
@section('canonical', url()->current())

@section('content')

    {{-- ══════════ PAGE HERO ══════════ --}}
    <section class="page-hero">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Anasayfa</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Galeri</li>
                </ol>
            </nav>
            <h1 class="page-hero__title">Galeri</h1>
            <p class="page-hero__lead">Kareler ve videolarla bize daha yakından bakın.</p>
        </div>
    </section>

    {{-- ══════════ GALLERY ══════════ --}}
    <section class="section--tight" aria-labelledby="gallery-heading">
        <div class="container">
            <h2 class="visually-hidden" id="gallery-heading">Galeri</h2>

            @if($photos->isEmpty() && $videos->isEmpty())
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="fa-regular fa-images"></i></div>
                    <p class="mb-0">Henüz galeri içeriği eklenmemiş.</p>
                </div>
            @else

                {{-- Photos --}}
                @if($photos->isNotEmpty())
                    <div class="mb-3">
                        <span class="section__eyebrow"><i class="fa-solid fa-images"></i> Fotoğraflar</span>
                    </div>
                    <div class="gallery-grid mb-5">
                        @foreach($photos as $photo)
                            @if($photo->image)
                                <a href="{{ upload_url($photo->image) }}" target="_blank" rel="noopener"
                                   class="gallery-item" aria-label="{{ $photo->title }}">
                                    <img src="{{ upload_url($photo->image, 'md') }}" alt="{{ $photo->title }}"
                                         class="gallery-item__img" loading="lazy" decoding="async">
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
                        <span class="section__eyebrow"><i class="fa-solid fa-video"></i> Videolar</span>
                    </div>
                    <div class="gallery-grid">
                        @foreach($videos as $video)
                            @if($video->video_url)
                                <a href="{{ $video->video_url }}" target="_blank" rel="noopener"
                                   class="gallery-item" aria-label="{{ $video->title }}">
                                    @if($video->image)
                                        <img src="{{ upload_url($video->image, 'md') }}" alt="{{ $video->title }}"
                                             class="gallery-item__img" loading="lazy" decoding="async">
                                    @else
                                        <span class="post-card__ph"><i class="fa-regular fa-image"></i></span>
                                    @endif
                                    <span class="gallery-item__overlay">
                                        <span>
                                            <i class="fa-solid fa-circle-play fa-2x mb-2 d-block"></i>
                                            <strong>{{ $video->title }}</strong>
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
