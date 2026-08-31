@extends('layouts.app')

@section('title', $term ? __('site.search.title_for', ['term' => $term]) : __('site.search.title'))
@section('meta_description', __('site.search.meta_desc'))

{{-- Arama sonucu bir sayfa değil bir görünüm: sonsuz sayıda terim, sonsuz
     sayıda adres demek. Dizine girerse aynı içerik yüzlerce adreste görünür.
     Boş arama sayfası ise gerçek bir sayfa — kanonik onda basılıyor. --}}
@if($term)
    @section('robots', 'noindex, follow')
@else
    @section('canonical', url()->current())
@endif

@php
    // Süzgeç bağlantıları taban adrese elle ekleniyor: localized_route(),
    // panelden o rota için özel bir adres açılmışsa fazladan parametreleri
    // düşürüyor ve süzgeç sessizce kayboluyor.
    $searchBase = localized_route('search');
    $searchUrl = fn (array $query = []): string => $searchBase
        . ($query ? '?' . http_build_query(array_filter($query, static fn ($v): bool => $v !== null && $v !== '')) : '');
@endphp

@section('content')

    {{-- ══════════ PAGE HERO ══════════ --}}
    <section class="page-hero">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="{{ localized_route('home') }}">{{ __('site.nav.home') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ __('site.search.title') }}</li>
                </ol>
            </nav>
            <span class="page-hero__eyebrow"><i class="fa-solid fa-magnifying-glass"></i> {{ __('site.search.eyebrow') }}</span>
            <h1 class="page-hero__title">{{ __('site.search.title') }}</h1>
            <p class="page-hero__lead">{{ __('site.search.lead') }}</p>
        </div>
    </section>

    {{-- ══════════ RESULTS ══════════ --}}
    <section class="section--tight">
        <div class="container">

            <form action="{{ $searchBase }}" method="GET" role="search" class="mb-4" data-validate novalidate>
                <label for="site-search" class="visually-hidden">{{ __('site.search.label') }}</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="search"
                           id="site-search"
                           name="arama"
                           class="form-control"
                           value="{{ $term }}"
                           maxlength="{{ config('search.max_length') }}"
                           autofocus
                           placeholder="{{ __('site.search.placeholder') }}"
                           data-validation-engine="validate[maxSize[{{ config('search.max_length') }}]]">
                    <button class="btn btn-primary" type="submit">{{ __('site.search.submit') }}</button>
                </div>
            </form>

            @if($tooShort)
                {{-- Tek harflik terim pratikte bütün siteyi döndürür; arama hiç
                     yapılmamış sayılıyor ve sebebi söyleniyor. --}}
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="fa-solid fa-keyboard"></i></div>
                    <h2 class="h5">{{ __('site.search.too_short') }}</h2>
                    <p class="mb-0">{{ __('site.search.too_short_lead', ['min' => config('search.min_length')]) }}</p>
                </div>

            @elseif(! $searchable)
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                    <h2 class="h5">{{ __('site.search.prompt') }}</h2>
                    <p class="mb-0">{{ __('site.search.prompt_lead') }}</p>
                </div>

            @else
                {{-- Tür süzgeci: rozetler tek sorgudan geliyor, her tür için
                     ayrı sayım yapılmıyor. --}}
                <nav class="pill-nav mb-4" aria-label="{{ __('site.search.type_filter') }}">
                    <a href="{{ $searchUrl(['arama' => $term]) }}"
                       class="pill {{ $type ? '' : 'pill--active' }}">
                        {{ __('site.search.all') }} <span class="ms-1 opacity-75">{{ $results->total() }}</span>
                    </a>
                    @foreach($types as $available)
                        @continue(($counts[$available->value] ?? 0) === 0)
                        <a href="{{ $searchUrl(['arama' => $term, 'tur' => $available->value]) }}"
                           class="pill {{ $type === $available ? 'pill--active' : '' }}">
                            <i class="{{ $available->icon() }}"></i>
                            {{ $available->label() }} <span class="ms-1 opacity-75">{{ $counts[$available->value] }}</span>
                        </a>
                    @endforeach
                </nav>

                @if($results->isNotEmpty())
                    <p class="text-muted mb-4">
                        {{ trans_choice('site.search.results', $results->total(), ['count' => $results->total(), 'term' => $term]) }}
                    </p>

                    <div class="d-flex flex-column gap-3">
                        @foreach($results as $row)
                            @php($item = $presenter->present($row))
                            <article class="search-result" data-reveal data-reveal-delay="{{ $loop->index % 4 }}">
                                <div class="search-result__icon" aria-hidden="true">
                                    <i class="{{ $item['type']->icon() }}"></i>
                                </div>
                                <div class="search-result__body">
                                    <div class="search-result__meta">
                                        <span class="search-result__type">{{ $item['type']->label() }}</span>
                                        @if($item['date'])
                                            <time datetime="{{ \Illuminate\Support\Carbon::parse($item['date'])->toDateString() }}">
                                                {{ \Illuminate\Support\Carbon::parse($item['date'])->translatedFormat('d M Y') }}
                                            </time>
                                        @endif
                                    </div>
                                    <h2 class="search-result__title">
                                        <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                                    </h2>
                                    @if($item['snippet'])
                                        <p class="search-result__snippet">{{ $item['snippet'] }}</p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="mt-5">
                        {{ $results->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                @else
                    <div class="empty-state">
                        <div class="empty-state__icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                        <h2 class="h5">{{ __('site.search.empty') }}</h2>
                        <p class="mb-0">{{ __('site.search.empty_lead') }}</p>
                    </div>
                @endif
            @endif

        </div>
    </section>

@endsection
