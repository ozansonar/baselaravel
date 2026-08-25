@extends('layouts.app')

@section('title', \App\Models\Setting::getValue('site_title', config('app.name')))
@section('canonical', url('/'))

@section('content')

    {{-- ══════════ HERO ══════════ --}}
    @if($sliders->isNotEmpty())
        <section class="section--tight">
            <div class="container">
                <div id="heroSlider" class="hero-slider carousel slide" data-bs-ride="carousel">
                    @if($sliders->count() > 1)
                    <div class="carousel-indicators">
                        @foreach($sliders as $i => $s)
                            <button type="button" data-bs-target="#heroSlider" data-bs-slide-to="{{ $i }}"
                                    class="{{ $i === 0 ? 'active' : '' }}" aria-label="Slayt {{ $i + 1 }}"></button>
                        @endforeach
                    </div>
                    @endif
                    <div class="carousel-inner">
                        @foreach($sliders as $i => $s)
                            <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                                <div class="hero-slide">
                                    <img src="{{ upload_url($s->image) }}" alt="{{ $s->title }}" class="hero-slide__img"
                                         loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async">
                                    <div class="hero-slide__overlay">
                                        <div>
                                            <h1 class="hero-slide__title">{{ $s->title }}</h1>
                                            @if($s->subtitle)
                                                <p class="hero-slide__text mt-2 mb-3">{{ $s->subtitle }}</p>
                                            @endif
                                            @if($s->button_text && $s->button_url)
                                                <a href="{{ $s->button_url }}" class="btn btn-light btn-lg">{{ $s->button_text }}</a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($sliders->count() > 1)
                    <button class="carousel-control-prev" type="button" data-bs-target="#heroSlider" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Önceki</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#heroSlider" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Sonraki</span>
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
                        <span class="hero__eyebrow"><i class="fa-solid fa-sparkles"></i> Kurumsal Base Altyapı</span>
                        <h1 class="hero__title">İşinizi <span class="grad">bir üst seviyeye</span> taşıyın</h1>
                        <p class="hero__lead">Modern, hızlı ve güvenilir bir altyapı ile fikirlerinizi hayata geçirin. Yönetim paneliyle tam entegre, ölçeklenebilir bir temel.</p>
                        <div class="d-flex flex-wrap gap-3 mt-4">
                            <a href="{{ route('contact') }}" class="btn btn-primary btn-lg">Hemen Başlayın <i class="fa-solid fa-arrow-right"></i></a>
                            <a href="{{ route('blog.index') }}" class="btn btn-light btn-lg">İçerikleri Keşfet</a>
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
            <div class="section__head--center mb-5">
                <span class="section__eyebrow"><i class="fa-solid fa-star"></i> Neden Biz?</span>
                <h2 class="section__title">Öne Çıkan Özelliklerimiz</h2>
                <p class="section__lead">İhtiyaçlarınıza uygun, modern ve sürdürülebilir çözümler sunuyoruz.</p>
            </div>
            <div class="row g-4">
                @php
                    $features = [
                        ['icon' => 'fa-bolt',          'title' => 'Yüksek Performans', 'text' => 'Hızlı yüklenen, optimize edilmiş ve ölçeklenebilir bir altyapı.'],
                        ['icon' => 'fa-shield-halved', 'title' => 'Güvenlik Odaklı',  'text' => 'Modern güvenlik standartlarıyla verileriniz her zaman koruma altında.'],
                        ['icon' => 'fa-mobile-screen','title' => 'Tam Responsive',   'text' => 'Tüm cihazlarda kusursuz görünüm ve akıcı bir kullanıcı deneyimi.'],
                        ['icon' => 'fa-headset',       'title' => 'Sürekli Destek',   'text' => 'İhtiyaç duyduğunuz her an yanınızda olan güçlü bir destek ekibi.'],
                    ];
                @endphp
                @foreach($features as $f)
                    <div class="col-sm-6 col-lg-3">
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
    <section class="section--tight section--soft">
        <div class="container">
            <div class="row g-4 text-center">
                @php
                    $stats = [
                        ['num' => '10+',  'label' => 'Yıllık Deneyim'],
                        ['num' => '500+', 'label' => 'Tamamlanan Proje'],
                        ['num' => '250+', 'label' => 'Mutlu Müşteri'],
                        ['num' => '24/7', 'label' => 'Kesintisiz Destek'],
                    ];
                @endphp
                @foreach($stats as $st)
                    <div class="col-6 col-lg-3">
                        <div class="stat">
                            <div class="stat__num">{{ $st['num'] }}</div>
                            <div class="stat__label">{{ $st['label'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════ LATEST CONTENT ══════════ --}}
    @if($blogPosts->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-5">
                    <div>
                        <span class="section__eyebrow"><i class="fa-solid fa-newspaper"></i> Blog</span>
                        <h2 class="section__title mb-0">Son İçerikler</h2>
                    </div>
                    <a href="{{ route('blog.index') }}" class="btn btn-outline-primary">Tümünü Gör <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="row g-4">
                    @foreach($blogPosts as $post)
                        <div class="col-md-6 col-lg-4">
                            <article class="post-card">
                                <a href="{{ route('blog.show', [$post->category->slug, $post->slug]) }}" class="post-card__media">
                                    @if($post->image)
                                        <img src="{{ upload_url($post->image, 'md') }}" alt="{{ $post->title }}" class="post-card__img" loading="lazy" decoding="async">
                                    @else
                                        <span class="post-card__ph"><i class="fa-regular fa-image"></i></span>
                                    @endif
                                    @if($post->category)
                                        <span class="post-card__cat">{{ $post->category->name }}</span>
                                    @endif
                                </a>
                                <div class="post-card__body">
                                    <div class="post-card__meta">
                                        <span><i class="fa-regular fa-calendar me-1"></i>{{ optional($post->published_at)->translatedFormat('d M Y') }}</span>
                                        <span><i class="fa-regular fa-eye me-1"></i>{{ number_format((int) $post->views) }}</span>
                                    </div>
                                    <h3 class="post-card__title">
                                        <a href="{{ route('blog.show', [$post->category->slug, $post->slug]) }}">{{ $post->title }}</a>
                                    </h3>
                                    @if($post->excerpt)
                                        <p class="post-card__excerpt">{{ \Illuminate\Support\Str::limit($post->excerpt, 110) }}</p>
                                    @endif
                                    <a href="{{ route('blog.show', [$post->category->slug, $post->slug]) }}" class="post-card__more">Devamını oku <i class="fa-solid fa-arrow-right"></i></a>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ══════════ CTA ══════════ --}}
    <section class="section">
        <div class="container">
            <div class="cta text-center">
                <h2 class="mb-3">Bir sonraki adımı birlikte atalım</h2>
                <p class="cta__lead mb-4 mx-auto mw-readable">Sorularınız mı var? Ekibimiz size yardımcı olmaktan memnuniyet duyar.</p>
                <a href="{{ route('contact') }}" class="btn btn-light btn-lg">Bize Ulaşın <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </section>

@endsection
