@extends('layouts.app')

@section('title', $activeCategory?->name ? $activeCategory->name . ' — ' . __('site.blog.title') : __('site.blog.title'))
@section('meta_description', $activeCategory?->name ? __('site.blog.category_meta', ['category' => $activeCategory->name]) : __('site.blog.meta_desc'))
{{-- Arama sonucu bir sayfa değil bir görünüm: sonsuz sayıda terim, sonsuz
     sayıda adres demek. Dizine girerse aynı içerik yüzlerce adreste görünür.
     Kanonik de basılmıyor — noindex ile birlikte kullanıldığında arama
     motorları ikisinden hangisine uyacağını bilemiyor. --}}
@if($search)
    @section('robots', 'noindex, follow')
@else
    @section('canonical', url()->current())
@endif

@section('content')

    {{-- ══════════ PAGE HERO ══════════ --}}
    <section class="page-hero">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="{{ localized_route('home') }}">{{ __('site.nav.home') }}</a></li>
                    @if($activeCategory)
                        <li class="breadcrumb-item"><a href="{{ localized_route('blog.index') }}">{{ __('site.blog.title') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $activeCategory->name }}</li>
                    @else
                        <li class="breadcrumb-item active" aria-current="page">{{ __('site.blog.title') }}</li>
                    @endif
                </ol>
            </nav>
            <span class="page-hero__eyebrow"><i class="fa-solid fa-newspaper"></i> {{ __('site.blog.eyebrow') }}</span>
            <h1 class="page-hero__title">{{ $activeCategory?->name ?? __('site.blog.title') }}</h1>
            <p class="page-hero__lead">{{ __('site.blog.lead') }}</p>
        </div>
    </section>

    {{-- ══════════ LIST ══════════ --}}
    <section class="section--tight">
        <div class="container">

            @php
                // Arama, içinde bulunulan görünümü korumalı: bir kategorideyken
                // arama yapmak o kategoride aramak demek.
                $searchAction = $activeCategory
                    ? route('blog.category', $activeCategory->slug)
                    : localized_route('blog.index');
            @endphp

            {{-- Search --}}
            <form action="{{ $searchAction }}" method="GET" role="search" class="mb-4" data-validate novalidate>
                <label for="blog-search" class="visually-hidden">{{ __('site.blog.search_label') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="search"
                           id="blog-search"
                           name="arama"
                           class="form-control"
                           value="{{ $search }}"
                           maxlength="100"
                           placeholder="{{ __('site.blog.search_placeholder') }}"
                           data-validation-engine="validate[maxSize[100]]">
                    <button class="btn btn-primary" type="submit">{{ __('site.blog.search_submit') }}</button>
                </div>
            </form>

            @if($search)
                {{-- Temizle bağlantısı grubun içinde değil: dar ekranda üçüncü
                     bir düğme metin alanını on karaktere düşürüyordu. Burada
                     hem yeri bol hem de "şu kadar sonuç, temizle" birlikte
                     okunuyor. --}}
                <p class="text-muted mb-4">
                    {{ trans_choice('site.blog.search_results', $posts->total(), ['count' => $posts->total(), 'term' => $search]) }}
                    <a href="{{ $searchAction }}" class="ms-2">{{ __('site.blog.search_clear') }}</a>
                </p>
            @endif

            {{-- Category filter --}}
            @if($categories->isNotEmpty())
                <nav class="pill-nav mb-4" aria-label="{{ __('site.misc.category_filter') }}">
                    <a href="{{ localized_route('blog.index') }}" class="pill {{ $activeCategory ? '' : 'pill--active' }}">{{ __('site.blog.all') }}</a>
                    @foreach($categories as $cat)
                        <a href="{{ route('blog.category', $cat->slug) }}"
                           class="pill {{ $activeCategory?->id === $cat->id ? 'pill--active' : '' }}">{{ $cat->name }}</a>
                    @endforeach
                </nav>
            @endif

            @if($posts->isNotEmpty())
                @php
                    // En yeni yazı yalnız ilk sayfada geniş kart oluyor. Sonraki
                    // sayfalarda "öne çıkan" ilan etmek yanlış olurdu; oradaki
                    // yazı yalnızca sıradaki yazı.
                    $lead       = $posts->currentPage() === 1 ? $posts->first() : null;
                    $gridPosts  = $lead ? collect($posts->items())->slice(1) : collect($posts->items());
                @endphp

                @if($lead)
                    <div class="mb-4" data-reveal>
                        @include('partials.post-card', ['post' => $lead, 'featured' => true, 'titleTag' => 'h2'])
                    </div>
                @endif

                @if($gridPosts->isNotEmpty())
                    <div class="row g-4">
                        @foreach($gridPosts as $post)
                            <div class="col-md-6 col-lg-4" data-reveal data-reveal-delay="{{ $loop->index % 4 }}">
                                @include('partials.post-card', ['post' => $post, 'titleTag' => 'h2'])
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5">
                    {{ $posts->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            @else
                <div class="empty-state">
                    @if($search)
                        {{-- Aramada boş sonuç "içerik yok" demek değil: ziyaretçi
                             farklı bir kelime deneyebileceğini bilmeli. --}}
                        <div class="empty-state__icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                        <h2 class="h5">{{ __('site.blog.search_empty') }}</h2>
                        <p class="mb-0">{{ __('site.blog.search_empty_lead') }}</p>
                    @else
                        <div class="empty-state__icon"><i class="fa-regular fa-newspaper"></i></div>
                        <h2 class="h5">{{ __('site.blog.empty') }}</h2>
                        <p class="mb-0">{{ __('site.blog.empty_lead') }}</p>
                    @endif
                </div>
            @endif

        </div>
    </section>

@endsection
