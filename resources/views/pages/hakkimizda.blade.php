@extends('layouts.app')

@section('title', $page->meta_title ?? $page->title . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', $page->meta_description ?? '1975\'ten bu yana 3 nesil boyunca doğal köy ürünleri üreten ' . \App\Models\Setting::getValue('site_name', config('app.name')) . '\'nin hikayesi.')
@section('canonical', route('pages.show', $page->slug))
@if($page->image)
@section('og_image', url(upload_url($page->image)))
@endif

@push('json-ld')
@php
    $aboutBreadcrumbJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $page->title],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($aboutBreadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@php
    $story = $page->getSection('story', []);
    $values = $page->getSection('values', []);
    $timeline = $page->getSection('timeline', []);
    $stats = $page->getSection('stats', []);
    $team = $page->getSection('team', []);
    $cta = $page->getSection('cta', []);
@endphp

@section('content')

{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <nav class="breadcrumb-custom animate-fade-up" aria-label="Breadcrumb">
            <a href="{{ route('home') }}"><i class="fa-solid fa-house"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>{{ $page->title }}</span>
        </nav>
        <h1 class="page-title animate-fade-up delay-1">{{ $story['tag'] ?? 'Hikayemiz' }}</h1>
        <p class="page-subtitle animate-fade-up delay-2">{{ $page->excerpt ?? 'Doğanın en saf halini sofralarınıza taşıyoruz' }}</p>
    </div>
</header>

{{-- Story Section --}}
<section class="about-story-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 animate-fade-up">
                <div class="about-story-image">
                    @if($page->image)
                        <x-responsive-image :path="$page->image" :alt="$page->title" size="lg" class="w-100" :responsive="true" />
                    @else
                        <div class="about-story-image-placeholder">
                            <i class="fa-solid fa-tractor"></i>
                            <span>Çiftlik Fotoğrafı</span>
                        </div>
                    @endif
                    @if(!empty($story['experience_year']))
                    <div class="about-experience-badge">
                        <span class="about-experience-number">{{ $story['experience_year'] }}</span>
                        <span>Yıllık Tecrübe</span>
                    </div>
                    @endif
                </div>
            </div>
            <div class="col-lg-6 animate-fade-up delay-1">
                <div class="about-story-content">
                    @if(!empty($story['tag']))
                    <span class="about-section-tag"><i class="fa-solid fa-leaf me-2"></i>{{ $story['tag'] }}</span>
                    @endif
                    @if(!empty($story['title']))
                    <h2>{{ $story['title'] }}</h2>
                    @endif
                    @if(!empty($story['paragraphs']))
                        @foreach(preg_split('/\n\s*\n/', $story['paragraphs']) as $paragraph)
                    <p>{{ trim($paragraph) }}</p>
                        @endforeach
                    @endif
                    @if(!empty($story['highlight']))
                    <div class="about-highlight">
                        <p>"{{ $story['highlight'] }}"</p>
                    </div>
                    @endif
                    @if(!empty($story['closing']))
                    <p>{{ $story['closing'] }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Values Section --}}
@if(!empty($values))
<section class="about-values-section">
    <div class="container">
        <div class="about-values-header animate-fade-up">
            <span class="about-section-tag"><i class="fa-solid fa-heart me-2"></i>Değerlerimiz</span>
            <h2>Bizi Biz Yapan Değerler</h2>
            <p>Her ürünümüzün arkasında bu değerler var</p>
        </div>

        <div class="row g-4">
            @foreach($values as $i => $value)
            <div class="col-md-6 col-lg-3 animate-fade-up delay-{{ $i + 1 }}">
                <div class="about-value-card">
                    <div class="about-value-icon">
                        <i class="{{ $value['icon'] }}"></i>
                    </div>
                    <h4>{{ $value['title'] }}</h4>
                    <p>{{ $value['description'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Timeline Section --}}
@if(!empty($timeline))
<section class="about-timeline-section">
    <div class="container">
        <div class="about-timeline-header animate-fade-up">
            <span class="about-section-tag"><i class="fa-solid fa-clock-rotate-left me-2"></i>Tarihçe</span>
            <h2>Yolculuğumuz</h2>
            <p>Yarım asrı aşan köklü geçmişimiz</p>
        </div>

        <div class="about-timeline">
            @foreach($timeline as $item)
            <div class="about-timeline-item animate-fade-up">
                <div class="about-timeline-content">
                    <span class="about-timeline-year">{{ $item['year'] }}</span>
                    <h4>{{ $item['title'] }}</h4>
                    <p>{{ $item['description'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Stats Section --}}
@if(!empty($stats))
<section class="about-stats-section" id="aboutStatsSection">
    <div class="container">
        <div class="row g-4">
            @foreach($stats as $i => $stat)
            <div class="col-6 col-md-3">
                <div class="about-stat-item animate-fade-up{{ $i > 0 ? ' delay-' . $i : '' }}">
                    <span class="about-stat-number" data-target="{{ $stat['number'] }}" data-suffix="{{ $stat['suffix'] ?? '' }}"@if(!empty($stat['format'])) data-format="{{ $stat['format'] }}"@endif>0</span>
                    <span class="about-stat-label">{{ $stat['label'] }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Team Section --}}
@if(!empty($team))
<section class="about-team-section">
    <div class="container">
        <div class="about-team-header animate-fade-up">
            <span class="about-section-tag about-section-tag-light"><i class="fa-solid fa-users me-2"></i>Ekibimiz</span>
            <h2>Ailemizle Tanışın</h2>
            <p>Üç kuşaktır bu işi sevgiyle yapan ekibimiz</p>
        </div>

        <div class="row g-4">
            @foreach($team as $i => $member)
            <div class="col-md-6 col-lg-4 animate-fade-up delay-{{ $i + 1 }}">
                <div class="about-team-card">
                    <div class="about-team-photo">
                        @if(!empty($member['photo']))
                            <img src="{{ upload_url($member['photo'], 'sm') }}" alt="{{ $member['name'] }}" class="img-fluid" width="300" height="300" loading="lazy" decoding="async">
                        @else
                            <i class="fa-solid fa-user"></i>
                        @endif
                    </div>
                    <div class="about-team-info">
                        <h4>{{ $member['name'] }}</h4>
                        <span class="about-team-role">{{ $member['role'] }}</span>
                        @if(!empty($member['description']))
                        <p>{{ $member['description'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- CTA Section --}}
<section class="about-cta-section">
    <div class="container">
        <div class="about-cta-content animate-fade-up">
            <h2>{{ $cta['title'] ?? 'Doğallığı Tatmaya Hazır mısınız?' }}</h2>
            <p>{{ $cta['description'] ?? '50 yıllık tecrübemizle hazırladığımız ürünlerimizi keşfedin ve doğallığın tadını çıkarın.' }}</p>
            <div class="about-cta-buttons">
                <a href="{{ route('products.all') }}" class="btn-custom">
                    <i class="fa-solid fa-basket-shopping"></i>
                    Ürünleri Keşfet
                </a>
                <a href="{{ route('contact') }}" class="about-btn-outline">
                    <i class="fa-solid fa-phone-flip"></i>
                    Bize Ulaşın
                </a>
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script src="{{ versioned_asset('js/about.js') }}" defer></script>
@endpush
