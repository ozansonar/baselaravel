@extends('layouts.app')

@section('title', \App\Models\Setting::getValue('site_title', config('app.name')))
@section('canonical', route('home'))

@section('content')

    {{-- ══════════ HERO ══════════ --}}
    @if($sliders->isNotEmpty())
        {{-- Panelden yönetilen slayt. Kayıt yoksa aşağıdaki durağan kahraman
             devreye giriyor; iki durumda da sayfa aynı yükseklikte açılıyor. --}}
        <section class="section--tight section--aurora">
            <div class="container">
                <div id="heroSlider" class="hero-slider carousel slide" data-bs-ride="carousel">
                    @if($sliders->count() > 1)
                    <div class="carousel-indicators">
                        @foreach($sliders as $i => $s)
                            <button type="button" data-bs-target="#heroSlider" data-bs-slide-to="{{ $i }}"
                                    class="{{ $i === 0 ? 'active' : '' }}" aria-label="{{ __('site.home.slide_aria', ['number' => $i + 1]) }}"></button>
                        @endforeach
                    </div>
                    @endif
                    <div class="carousel-inner">
                        @foreach($sliders as $i => $s)
                            <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                                <div class="hero-slide">
                                    <img src="{{ upload_url($s->image) }}" alt="{{ $s->title }}" class="hero-slide__img"
                                         loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                                         fetchpriority="{{ $i === 0 ? 'high' : 'auto' }}" decoding="async">
                                    <div class="hero-slide__overlay">
                                        <div class="hero-slide__inner">
                                            <span class="hero-slide__badge"><i class="fa-solid fa-sparkles"></i> {{ __('site.home.eyebrow') }}</span>
                                            {{-- İlk slayt sayfanın h1'i; ötekiler başlık sırasını
                                                 bozmamak için h2 kalıyor. --}}
                                            @if($i === 0)
                                                <h1 class="hero-slide__title">{{ $s->title }}</h1>
                                            @else
                                                <h2 class="hero-slide__title">{{ $s->title }}</h2>
                                            @endif
                                            @if($s->subtitle)
                                                <p class="hero-slide__text mt-3 mb-4">{{ $s->subtitle }}</p>
                                            @endif
                                            <div class="d-flex flex-wrap gap-2">
                                                @if($s->button_text && $s->button_url)
                                                    <a href="{{ $s->button_url }}" class="btn btn-light btn-lg">{{ $s->button_text }} <i class="fa-solid fa-arrow-right"></i></a>
                                                @endif
                                                <a href="{{ route('contact') }}" class="btn btn-glass btn-lg">{{ __('site.actions.contact_us') }}</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($sliders->count() > 1)
                    <button class="carousel-control-prev" type="button" data-bs-target="#heroSlider" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">{{ __('site.actions.prev') }}</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#heroSlider" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">{{ __('site.actions.next') }}</span>
                    </button>
                    @endif
                </div>
            </div>
        </section>
    @else
        <section class="hero">
            <div class="container">
                <div class="row align-items-center g-5">
                    <div class="col-lg-6">
                        <span class="hero__eyebrow"><i class="fa-solid fa-sparkles"></i> {{ __('site.home.eyebrow') }}</span>
                        {{-- Contains markup on purpose: the highlighted words differ per language --}}
                        <h1 class="hero__title">{!! __('site.home.hero_title') !!}</h1>
                        <p class="hero__lead">{{ __('site.home.hero_lead') }}</p>
                        <div class="d-flex flex-wrap gap-3 mt-4">
                            <a href="{{ route('contact') }}" class="btn btn-primary btn-lg">{{ __('site.actions.get_start') }} <i class="fa-solid fa-arrow-right"></i></a>
                            <a href="{{ route('blog.index') }}" class="btn btn-light btn-lg">{{ __('site.actions.explore') }}</a>
                        </div>

                        {{-- Güven şeridi: ilk ekranda ne sunulduğunu tek bakışta söylüyor.
                             Anahtarlar açık yazılıyor; birleştirilmiş anahtarı ne çeviri
                             denetimi ne de arama bulabiliyor. --}}
                        @php
                            $trust = [
                                ['icon' => 'fa-gauge-high',    'text' => __('site.home.trust_1')],
                                ['icon' => 'fa-language',      'text' => __('site.home.trust_2')],
                                ['icon' => 'fa-mobile-screen', 'text' => __('site.home.trust_3')],
                            ];
                        @endphp
                        <div class="hero__trust">
                            @foreach($trust as $t)
                                <span class="hero__trust-item"><i class="fa-solid {{ $t['icon'] }}"></i> {{ $t['text'] }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="hero__panel">
                            <i class="fa-solid fa-cubes"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ══════════ FEATURES ══════════ --}}
    <section class="section">
        <div class="container">
            <div class="section__head--center mb-5" data-reveal>
                <span class="section__eyebrow"><i class="fa-solid fa-star"></i> {{ __('site.misc.why_us') }}</span>
                <h2 class="section__title">{{ __('site.home.features') }}</h2>
                <p class="section__lead">{{ __('site.home.features_lead') }}</p>
            </div>
            <div class="row g-4">
                @php
                    $features = [
                        ['icon' => 'fa-bolt',          'title' => __('site.home.f1_title'), 'text' => __('site.home.f1_text')],
                        ['icon' => 'fa-shield-halved', 'title' => __('site.home.f2_title'), 'text' => __('site.home.f2_text')],
                        ['icon' => 'fa-mobile-screen', 'title' => __('site.home.f3_title'), 'text' => __('site.home.f3_text')],
                        ['icon' => 'fa-headset',       'title' => __('site.home.f4_title'), 'text' => __('site.home.f4_text')],
                    ];
                @endphp
                @foreach($features as $f)
                    {{-- Kartlar sırayla beliriyor; gecikme satır içi değişkenle
                         veriliyor çünkü değeri döngünün sırası belirliyor. --}}
                    <div class="col-sm-6 col-lg-3" data-reveal data-reveal-delay="{{ $loop->index % 4 }}">
                        <div class="feature-card">
                            <div class="feature-card__icon"><i class="fa-solid {{ $f['icon'] }}"></i></div>
                            <h3 class="feature-card__title">{{ $f['title'] }}</h3>
                            <p class="feature-card__text">{{ $f['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════ STATS ══════════ --}}
    <section class="section--tight section--aurora">
        <div class="container">
            <div class="row g-4 text-center">
                @php
                    $stats = [
                        ['num' => '10+',  'label' => __('site.home.stat1')],
                        ['num' => '500+', 'label' => __('site.home.stat2')],
                        ['num' => '250+', 'label' => __('site.home.stat3')],
                        ['num' => '24/7', 'label' => __('site.home.stat4')],
                    ];
                @endphp
                @foreach($stats as $st)
                    <div class="col-6 col-lg-3" data-reveal data-reveal-delay="{{ $loop->index % 4 }}">
                        <div class="stat">
                            <div class="stat__num">{{ $st['num'] }}</div>
                            <div class="stat__label">{{ $st['label'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════ PROCESS ══════════ --}}
    <section class="section section--soft">
        <div class="container">
            <div class="section__head--center mb-5" data-reveal>
                <span class="section__eyebrow"><i class="fa-solid fa-route"></i> {{ __('site.home.process_eyebrow') }}</span>
                <h2 class="section__title">{{ __('site.home.process') }}</h2>
                <p class="section__lead">{{ __('site.home.process_lead') }}</p>
            </div>
            <div class="row g-4">
                @php
                    $steps = [
                        ['icon' => 'fa-sliders',      'title' => __('site.home.p1_title'), 'text' => __('site.home.p1_text')],
                        ['icon' => 'fa-pen-to-square','title' => __('site.home.p2_title'), 'text' => __('site.home.p2_text')],
                        ['icon' => 'fa-chart-line',   'title' => __('site.home.p3_title'), 'text' => __('site.home.p3_text')],
                    ];
                @endphp
                @foreach($steps as $step)
                    <div class="col-md-4" data-reveal data-reveal-delay="{{ $loop->index % 4 }}">
                        <div class="step-card">
                            <span class="step-card__num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="step-card__icon"><i class="fa-solid {{ $step['icon'] }}"></i></div>
                            <h3 class="step-card__title">{{ $step['title'] }}</h3>
                            <p class="step-card__text">{{ $step['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════ GALLERY ══════════ --}}
    {{-- Panelden yönetilen galerinin önizlemesi. Kayıt yoksa bölüm hiç
         basılmıyor: boş bir ızgara ana sayfada delik gibi duruyordu. --}}
    @if($galleryPhotos->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4" data-reveal>
                    <div>
                        <span class="section__eyebrow"><i class="fa-solid fa-images"></i> {{ __('site.home.gallery_eyebrow') }}</span>
                        <h2 class="section__title mb-2">{{ __('site.home.gallery_title') }}</h2>
                        <p class="section__lead mb-0">{{ __('site.home.gallery_lead') }}</p>
                    </div>
                    <a href="{{ route('gallery') }}" class="btn btn-outline-primary">{{ __('site.actions.view_all') }} <i class="fa-solid fa-arrow-right"></i></a>
                </div>

                <div class="gallery-grid gallery-grid--masonry" data-lightbox-gallery>
                    @foreach($galleryPhotos as $photo)
                        <a href="{{ upload_url($photo->image) }}" class="gallery-item"
                           data-lightbox-item
                           data-title="{{ $photo->title }}"
                           data-caption="{{ $photo->galleryCategory?->name }}"
                           aria-label="{{ $photo->title }}">
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
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ══════════ LATEST CONTENT ══════════ --}}
    @if($blogPosts->isNotEmpty())
        @php
            // İlk yazı geniş kart olarak öne çıkıyor, kalanlar ızgarada.
            // Ayrım listeyi düz bir kart duvarı olmaktan çıkarıyor.
            $featuredPost = $blogPosts->first();
            $otherPosts   = $blogPosts->slice(1);
        @endphp
        <section class="section {{ $galleryPhotos->isNotEmpty() ? 'section--soft' : '' }}">
            <div class="container">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4" data-reveal>
                    <div>
                        <span class="section__eyebrow"><i class="fa-solid fa-newspaper"></i> {{ __('site.home.blog_eyebrow') }}</span>
                        <h2 class="section__title mb-0">{{ __('site.blog.latest') }}</h2>
                    </div>
                    <a href="{{ route('blog.index') }}" class="btn btn-outline-primary">{{ __('site.actions.view_all') }} <i class="fa-solid fa-arrow-right"></i></a>
                </div>

                <div class="mb-4" data-reveal>
                    @include('partials.post-card', ['post' => $featuredPost, 'featured' => true])
                </div>

                @if($otherPosts->isNotEmpty())
                    <div class="row g-4">
                        @foreach($otherPosts as $post)
                            <div class="col-md-6 col-lg-4" data-reveal data-reveal-delay="{{ $loop->index % 4 }}">
                                @include('partials.post-card', ['post' => $post])
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ══════════ CTA ══════════ --}}
    <section class="section">
        <div class="container">
            <div class="cta text-center" data-reveal>
                <h2 class="mb-3">{{ __('site.home.cta') }}</h2>
                <p class="cta__lead mb-4 mx-auto mw-readable">{{ __('site.home.cta_lead') }}</p>
                <a href="{{ route('contact') }}" class="btn btn-light btn-lg">{{ __('site.actions.contact_us') }} <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </section>

@endsection
