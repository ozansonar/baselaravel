@extends('layouts.app')

@section('title', $activeCategory?->name ? $activeCategory->name . ' — ' . __('site.nav.gallery') : __('site.nav.gallery'))
@section('meta_description', __('site.gallery.meta_desc'))
{{-- Süzgeç ve sayfa numarası adresin parçası: kendini gösteren canonical
     olmasaydı ikinci sayfa birincinin kopyası sayılıp dizinden düşerdi. --}}
@section('canonical', $canonical)

@section('content')

    {{-- ══════════ PAGE HERO ══════════ --}}
    <section class="page-hero">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="{{ localized_route('home') }}">{{ __('site.nav.home') }}</a></li>
                    @if($activeCategory)
                        <li class="breadcrumb-item"><a href="{{ localized_route('gallery') }}">{{ __('site.nav.gallery') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $activeCategory->name }}</li>
                    @else
                        <li class="breadcrumb-item active" aria-current="page">{{ __('site.nav.gallery') }}</li>
                    @endif
                </ol>
            </nav>
            <span class="page-hero__eyebrow"><i class="fa-solid fa-images"></i> {{ __('site.gallery.title') }}</span>
            <h1 class="page-hero__title">{{ $activeCategory?->name ?? __('site.nav.gallery') }}</h1>
            <p class="page-hero__lead">{{ __('site.gallery.lead') }}</p>
        </div>
    </section>

    {{-- ══════════ GALLERY ══════════ --}}
    <section class="section--tight" aria-labelledby="gallery-heading">
        <div class="container">
            <h2 class="visually-hidden" id="gallery-heading">{{ __('site.nav.gallery') }}</h2>

            {{-- Kategori süzgeci --}}
            @if($categories->isNotEmpty())
                <nav class="pill-nav mb-3" aria-label="{{ __('site.misc.category_filter') }}">
                    <a href="{{ route('gallery', $type ? ['tur' => $type] : []) }}"
                       class="pill {{ $categorySlug ? '' : 'pill--active' }}">{{ __('site.gallery.all') }}</a>
                    @foreach($categories as $cat)
                        <a href="{{ route('gallery', array_filter(['kategori' => $cat->slug, 'tur' => $type])) }}"
                           class="pill {{ $categorySlug === $cat->slug ? 'pill--active' : '' }}">{{ $cat->name }}</a>
                    @endforeach
                </nav>
            @endif

            {{-- Tür süzgeci — eskiden fotoğraf ve video ayrı bölümlerdi; sayfa
                 başına sabit sayıda öğe düşünce ikisi tek ızgarada birleşti,
                 ayrım süzgece taşındı. --}}
            <nav class="pill-nav mb-4" aria-label="{{ __('site.gallery.type_filter') }}">
                <a href="{{ route('gallery', array_filter(['kategori' => $categorySlug])) }}"
                   class="pill {{ $type ? '' : 'pill--active' }}">{{ __('site.gallery.all') }}</a>
                <a href="{{ route('gallery', array_filter(['kategori' => $categorySlug, 'tur' => 'photo'])) }}"
                   class="pill {{ $type === 'photo' ? 'pill--active' : '' }}">
                    <i class="fa-solid fa-images"></i> {{ __('site.gallery.photos') }}
                </a>
                <a href="{{ route('gallery', array_filter(['kategori' => $categorySlug, 'tur' => 'video'])) }}"
                   class="pill {{ $type === 'video' ? 'pill--active' : '' }}">
                    <i class="fa-solid fa-video"></i> {{ __('site.gallery.videos') }}
                </a>
            </nav>

            @if($items->isEmpty())
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="fa-regular fa-images"></i></div>
                    <p class="mb-0">
                        {{ $categorySlug || $type ? __('site.gallery.no_results') : __('site.gallery.empty') }}
                    </p>
                </div>
            @else
                {{-- Fotoğraflar sayfadan ayrılmadan büyüyor: yeni sekmede
                     açılan ham dosya galeriyi terk ettiriyordu. --}}
                <div class="gallery-grid gallery-grid--masonry" data-lightbox-gallery>
                    @foreach($items as $item)
                        @php
                            // İlk sıradaki iki kare sayfanın açılışında zaten
                            // ekranda; onları geciktirmek en büyük görselin
                            // boyanmasını geciktirmek olurdu. Gerisi görünür
                            // alana girene kadar inmiyor.
                            $eager = $items->currentPage() === 1 && $loop->index < 2;
                        @endphp

                        @if($item->type === \App\Enums\GalleryType::Video && $item->video_url)
                            {{-- Video dış bir adrese gidiyor, bu yüzden büyütme
                                 penceresine değil yeni sekmeye açılıyor. --}}
                            <a href="{{ $item->video_url }}" target="_blank" rel="noopener"
                               class="gallery-item" aria-label="{{ $item->title }} — {{ __('site.gallery.watch') }}">
                                @include('partials.gallery-thumb', ['item' => $item, 'eager' => $eager])
                                <span class="gallery-item__play" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                                <span class="gallery-item__overlay">
                                    <span>
                                        <strong class="d-block">{{ $item->title }}</strong>
                                        @if($item->galleryCategory)
                                            <small>{{ $item->galleryCategory->name }}</small>
                                        @endif
                                    </span>
                                </span>
                            </a>
                        @elseif($item->image)
                            <a href="{{ upload_url($item->image) }}"
                               class="gallery-item"
                               data-lightbox-item
                               data-title="{{ $item->title }}"
                               data-caption="{{ $item->galleryCategory?->name }}"
                               aria-label="{{ $item->title }} — {{ __('site.gallery.view') }}">
                                @include('partials.gallery-thumb', ['item' => $item, 'eager' => $eager])
                                <span class="gallery-item__zoom" aria-hidden="true"><i class="fa-solid fa-expand"></i></span>
                                <span class="gallery-item__overlay">
                                    <span>
                                        <strong class="d-block">{{ $item->title }}</strong>
                                        @if($item->galleryCategory)
                                            <small>{{ $item->galleryCategory->name }}</small>
                                        @endif
                                    </span>
                                </span>
                            </a>
                        @endif
                    @endforeach
                </div>

                <div class="mt-5">
                    {{ $items->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </section>

@endsection
