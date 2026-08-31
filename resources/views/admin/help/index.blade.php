@extends('layouts.admin')

@section('title', 'Yardım Merkezi')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Yardım</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title">Yardım Merkezi</h1>
            <p class="page-subtitle">Panelin modülleri, sık sorulan sorular ve destek bilgileri</p>
        </div>
    </div>

    {{-- ==================== SECTION 1: ARAMA ==================== --}}
    <div class="hlp-search-hero" data-aos="fade-up" data-aos-delay="0">
        <div class="hlp-search-inner">
            <i class="bi bi-search hlp-search-icon"></i>
            <h2 class="hlp-search-title">Size nasıl yardımcı olabiliriz?</h2>
            <p class="hlp-search-sub">Modül kılavuzlarında ve sık sorulan sorularda arayın</p>

            <form method="GET" action="{{ route('admin.help.index') }}" class="hlp-search-bar">
                <input type="text" name="q" value="{{ $search }}"
                       placeholder="Aramak istediğiniz konuyu yazın..." data-fv-ignore>
                <button type="submit" class="hlp-search-btn">Ara</button>
            </form>

            <div class="hlp-search-tags">
                @foreach(['yedek', 'e-posta', 'izin', 'dil', 'kuyruk'] as $tag)
                    <a href="{{ route('admin.help.index', ['q' => $tag]) }}" class="hlp-tag">{{ $tag }}</a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 2: SAYILAR ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <a href="#guides" class="hlp-stat-card">
                <div class="hlp-stat-icon hlp-stat-teal"><i class="bi bi-book"></i></div>
                <span class="hlp-stat-num">{{ $stats['guides'] }}</span>
                <span class="hlp-stat-label">Modül Kılavuzu</span>
            </a>
        </div>
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <a href="#faq" class="hlp-stat-card">
                <div class="hlp-stat-icon hlp-stat-blue"><i class="bi bi-patch-question"></i></div>
                <span class="hlp-stat-num">{{ $stats['faqs'] }}</span>
                <span class="hlp-stat-label">Sık Sorulan Soru</span>
            </a>
        </div>
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <a href="#environment" class="hlp-stat-card">
                <div class="hlp-stat-icon hlp-stat-purple"><i class="bi bi-hdd-network"></i></div>
                <span class="hlp-stat-num">{{ count($environment) }}</span>
                <span class="hlp-stat-label">Sistem Bilgisi</span>
            </a>
        </div>
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <a href="#contact" class="hlp-stat-card">
                <div class="hlp-stat-icon hlp-stat-green"><i class="bi bi-headset"></i></div>
                <span class="hlp-stat-num"><i class="bi bi-envelope"></i></span>
                <span class="hlp-stat-label">Destek</span>
            </a>
        </div>
    </div>

    {{-- ==================== SECTION 3: KILAVUZLAR ==================== --}}
    <section id="guides">
        <div class="d-flex align-items-center justify-content-between mb-3" data-aos="fade-up">
            <h2 class="rpr-section-title">Modül Kılavuzları</h2>
            <span class="rpr-section-sub">{{ count($guides) }} modül</span>
        </div>

        <div class="row g-4 mb-4">
            @forelse($guides as $index => $guide)
                <div class="col-xl-4 col-md-6" data-aos="fade-up" data-aos-delay="{{ ($index % 3) * 80 }}">
                    <a href="{{ route($guide['route']) }}" class="hlp-guide-card">
                        <div class="hlp-guide-cover hlp-cover-{{ $guide['cover'] }}">
                            <i class="bi {{ $guide['icon'] }}"></i>
                            <span class="hlp-guide-badge">{{ $guide['badge'] }}</span>
                        </div>
                        <div class="hlp-guide-body">
                            <h5 class="hlp-guide-title">{{ $guide['title'] }}</h5>
                            <p class="hlp-guide-desc">{{ $guide['description'] }}</p>
                            <div class="hlp-guide-meta">
                                <span><i class="bi bi-box-arrow-up-right me-1"></i>Modüle git</span>
                            </div>
                        </div>
                    </a>
                </div>
            @empty
                <div class="col-12">
                    <div class="card-dark">
                        <div class="card-body-custom text-center py-5">
                            <span class="usr-meta">"{{ $search }}" ile eşleşen kılavuz yok.</span>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    </section>

    {{-- ==================== SECTION 4: SİSTEM BİLGİSİ ==================== --}}
    <section id="environment">
        <div class="d-flex align-items-center justify-content-between mb-3" data-aos="fade-up">
            <h2 class="rpr-section-title">Sistem Bilgisi</h2>
            <span class="rpr-section-sub">Destek isterken bu bilgileri paylaşın</span>
        </div>

        <div class="row g-4 mb-4">
            @foreach($environment as $index => $info)
                <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="{{ $index * 80 }}">
                    <div class="usr-stat-card">
                        <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi {{ $info['icon'] }}"></i></div>
                        <div class="usr-stat-info">
                            <span class="usr-stat-label">{{ $info['label'] }}</span>
                            <h3 class="usr-stat-value">{{ $info['value'] }}</h3>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ==================== SECTION 5: SSS ==================== --}}
    <section id="faq">
        <div class="d-flex align-items-center justify-content-between mb-3" data-aos="fade-up">
            <h2 class="rpr-section-title">Sık Sorulan Sorular</h2>
            <span class="rpr-section-sub">{{ count($faqs) }} soru</span>
        </div>

        <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="50">
            <div class="card-body-custom">
                <div class="hlp-faq-tabs mb-4">
                    <a href="{{ route('admin.help.index', array_filter(['q' => $search])) }}"
                       class="hlp-faq-tab {{ $category === 'all' ? 'active' : '' }}">Tümü</a>
                    @foreach($categories as $key => $label)
                        <a href="{{ route('admin.help.index', array_filter(['q' => $search, 'category' => $key])) }}"
                           class="hlp-faq-tab {{ $category === $key ? 'active' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>

                <div class="accordion hlp-accordion" id="helpFaq">
                    @forelse($faqs as $index => $faq)
                        <div class="accordion-item hlp-accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button hlp-accordion-btn collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#helpFaq{{ $index }}"
                                        aria-expanded="false" aria-controls="helpFaq{{ $index }}">
                                    {{ $faq['question'] }}
                                </button>
                            </h2>
                            <div id="helpFaq{{ $index }}" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
                                <div class="accordion-body hlp-accordion-body">
                                    <p class="mb-2">{{ $faq['answer'] }}</p>
                                    @if(isset($faq['route']) && Route::has($faq['route']))
                                        <a href="{{ route($faq['route']) }}" class="hlp-faq-link">
                                            <i class="bi bi-arrow-right me-1"></i>İlgili ekrana git
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="usr-meta mb-0">"{{ $search }}" ile eşleşen soru yok.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== SECTION 6: DESTEK ==================== --}}
    <section id="contact">
        <div class="card-dark" data-aos="fade-up">
            <div class="card-body-custom">
                <h5 class="rpr-chart-title mb-1">Bize Ulaşın</h5>
                <p class="rpr-chart-sub mb-4">Aradığınızı bulamadıysanız</p>

                <div class="row g-3">
                    @if($supportEmail !== '')
                        <div class="col-md-6">
                            <a href="mailto:{{ $supportEmail }}" class="hlp-contact-card">
                                <div class="hlp-contact-icon hlp-contact-teal"><i class="bi bi-envelope"></i></div>
                                <div>
                                    <span class="hlp-guide-title">E-posta</span>
                                    <p class="hlp-guide-desc mb-0">{{ $supportEmail }}</p>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if($supportPhone !== '')
                        <div class="col-md-6">
                            <a href="tel:{{ preg_replace('/\s+/', '', $supportPhone) }}" class="hlp-contact-card">
                                <div class="hlp-contact-icon hlp-contact-blue"><i class="bi bi-telephone"></i></div>
                                <div>
                                    <span class="hlp-guide-title">Telefon</span>
                                    <p class="hlp-guide-desc mb-0">{{ $supportPhone }}</p>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if($supportEmail === '' && $supportPhone === '')
                        <div class="col-12">
                            <p class="usr-meta mb-0">
                                Destek bilgileri henüz girilmemiş.
                                <a href="{{ route('admin.settings.index') }}" class="hlp-faq-link">Ayarlar ekranından</a>
                                iletişim e-postası ve telefonu tanımlayabilirsiniz.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

@endsection
