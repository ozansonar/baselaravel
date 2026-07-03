@extends('layouts.app')

@php
    $siteName = \App\Models\Setting::getValue('site_name', config('app.name'));
    $siteLogo = \App\Models\Setting::getValue('site_logo');
    $logoUrl  = $siteLogo
        ? upload_url($siteLogo)
        : 'https://www.orhanbabaninciftligi.com/uploads/images/5f6cd32c2da54-1600967468.png';

    $whatsapp = \App\Models\Setting::getValue('social_whatsapp');
    $whatsappMessage = "Merhaba,\n\n{$page->city_name}'a {$siteName} ürünleri hakkında bilgi almak istiyorum.\n\nTeşekkürler.";

    $canonicalUrl = url('/' . $page->city_slug . '-koy-urunleri');

    $defaultMetaTitle = $page->title;
    $defaultMetaDesc  = "Çorum köyünden {$page->city_name}'a soğuk zincir kargo ile doğal köy ürünleri. Taze süt, köy peyniri, tereyağı. {$page->city_name}'a kapınıza teslimat.";
@endphp

@section('title', $page->meta_title ?? ($defaultMetaTitle . ' | ' . $siteName))
@section('meta_description', $page->meta_description ?? $defaultMetaDesc)
@section('canonical', $canonicalUrl)

@section('og_title', $page->meta_title ?? $defaultMetaTitle)
@section('og_description', $page->meta_description ?? $defaultMetaDesc)
@section('og_type', 'website')

@push('json-ld')
@php
    $orgPhone   = \App\Models\Setting::getValue('contact_phone');
    $ogImage    = \App\Models\Setting::getValue('og_image');
    $pageImage  = $ogImage ? url(upload_url($ogImage)) : url($logoUrl);

    $cityServiceJsonLd = [
        '@context'       => 'https://schema.org',
        '@type'          => 'LocalBusiness',
        'additionalType' => 'https://schema.org/Farm',
        'name'           => $siteName,
        'url'            => $canonicalUrl,
        'description'    => $page->meta_description ?? $defaultMetaDesc,
        'image'          => $pageImage,
        'logo'           => url($logoUrl),
        'address'        => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => 'Büyük Palabıyık Köyü',
            'addressLocality' => 'Çorum',
            'addressRegion'   => 'Çorum',
            'postalCode'      => '19100',
            'addressCountry'  => 'TR',
        ],
        'areaServed' => [
            '@type' => 'City',
            'name'  => $page->city_name,
        ],
        'priceRange' => '₺₺',
    ];
    if ($orgPhone) {
        $cityServiceJsonLd['telephone'] = $orgPhone;
    }

    $breadcrumbJsonLd = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $page->city_name . ' Köy Ürünleri', 'item' => $canonicalUrl],
        ],
    ];

    $itemListItems = [];
    foreach ($products as $i => $p) {
        $itemListItems[] = [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'name'     => $p->name,
            'url'      => route('products.show', $p->slug),
        ];
    }
    $itemListJsonLd = [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => $page->city_name . ' için ürünler',
        'itemListElement' => $itemListItems,
    ];

    // Şehir bazlı SSS — hem Schema.org FAQPage hem de accordion için kullanılır
    $cityFaqs = [
        [
            'q' => $page->city_name . "'a kargo ne kadar sürer?",
            'a' => "Çorum'dan {$page->city_name} adresinize " . ($page->delivery_time ?? '1-2 iş günü') . " içinde soğuk zincir kargo ile teslimat yapıyoruz. Sipariş verdikten sonra ürünleriniz aynı gün hazırlanır ve yola çıkar.",
        ],
        [
            'q' => "Ürünler {$page->city_name}'da nasıl saklanır?",
            'a' => "Tüm ürünlerimiz soğuk zincir kargo ile gönderilir. Teslim aldıktan sonra hemen buzdolabınıza yerleştirin. Tereyağı buzlukta 6 ay, peynir ve yoğurt buzdolabında 7 gün taze kalır.",
        ],
        [
            'q' => "Sipariş verirken kargo ücreti nasıl hesaplanıyor?",
            'a' => "Sipariş tutarınıza göre belirlenen tek kargo ücreti uygulanır. Belirli bir tutarın üzerindeki siparişlerde ücretsiz kargo seçeneği sunulur. Sipariş özetinde net tutarı görebilirsiniz.",
        ],
        [
            'q' => "Ürünler tamamen doğal mı?",
            'a' => "Evet. Çorum Büyük Palabıyık Köyü'nde 3 nesildir geleneksel yöntemlerle üretim yapıyoruz. Hayvanlarımız doğal otla beslenir, ürünlerimizde hiçbir katkı maddesi, koruyucu veya hormon kullanılmaz.",
        ],
        [
            'q' => "Hangi ödeme yöntemlerini kullanabilirim?",
            'a' => "Kredi kartı, banka havalesi/EFT ve kapıda ödeme seçeneklerimiz mevcuttur. Sipariş özetinde size uygun ödeme yöntemlerini görebilir, dilediğinizi seçebilirsiniz. Tüm ödemeler 256-bit SSL ile güvence altındadır.",
        ],
    ];

    $faqJsonLd = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => [
            '@type'          => 'Question',
            'name'           => $f['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ], $cityFaqs),
    ];
@endphp
<script type="application/ld+json">{!! json_encode($cityServiceJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode($breadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode($faqJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@if(!empty($itemListItems))
<script type="application/ld+json">{!! json_encode($itemListJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
@endpush

@section('content')

{{-- ═══════════════════════════════════════════════════════════════
     HERO — büyük başlık + CTA + floating stats card
     ═══════════════════════════════════════════════════════════════ --}}
<section class="city-hero" aria-labelledby="city-hero-title">
    <div class="city-hero__bg-pattern" aria-hidden="true"></div>
    <div class="container city-hero__container">
        <nav class="breadcrumb-custom city-hero__breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}"><i class="fa-solid fa-home"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>{{ $page->city_name }} Köy Ürünleri</span>
        </nav>

        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="city-hero__badge">
                    <i class="fa-solid fa-map-pin"></i>
                    <span>Çorum'dan {{ $page->city_name }}'a</span>
                </div>

                <h1 class="city-hero__title" id="city-hero-title">
                    {{ $page->hero_heading }}
                </h1>

                @if($page->hero_description)
                <p class="city-hero__lead">{{ $page->hero_description }}</p>
                @endif

                <div class="city-hero__cta">
                    <a href="#urunler" class="city-hero__cta-primary">
                        <i class="fa-solid fa-shopping-basket"></i>
                        <span>Ürünleri Keşfet</span>
                    </a>
                    @if($whatsapp)
                    <a href="{{ whatsapp_url($whatsapp, $whatsappMessage) }}" target="_blank" rel="noopener noreferrer" class="city-hero__cta-whatsapp">
                        <i class="fa-brands fa-whatsapp"></i>
                        <span>WhatsApp ile Sor</span>
                    </a>
                    @endif
                </div>

                <div class="city-hero__quick-stats">
                    <div class="city-hero__stat">
                        <strong>{{ $page->delivery_time ?? '1-2 gün' }}</strong>
                        <span>Hızlı Teslimat</span>
                    </div>
                    <div class="city-hero__stat-divider" aria-hidden="true"></div>
                    <div class="city-hero__stat">
                        <strong>%100</strong>
                        <span>Doğal & Katkısız</span>
                    </div>
                    <div class="city-hero__stat-divider" aria-hidden="true"></div>
                    <div class="city-hero__stat">
                        <strong>3 Nesil</strong>
                        <span>Aile Çiftliği</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 d-none d-lg-block">
                <div class="city-hero__visual">
                    <div class="city-hero__circle city-hero__circle--1" aria-hidden="true"></div>
                    <div class="city-hero__circle city-hero__circle--2" aria-hidden="true"></div>
                    <div class="city-hero__circle city-hero__circle--3" aria-hidden="true"></div>

                    <div class="city-hero__icon-grid" aria-hidden="true">
                        <div class="city-hero__icon-item"><i class="fa-solid fa-cow"></i></div>
                        <div class="city-hero__icon-item"><i class="fa-solid fa-egg"></i></div>
                        <div class="city-hero__icon-item"><i class="fa-solid fa-jar"></i></div>
                        <div class="city-hero__icon-item"><i class="fa-solid fa-cheese"></i></div>
                        <div class="city-hero__icon-item"><i class="fa-solid fa-wheat-awn"></i></div>
                        <div class="city-hero__icon-item"><i class="fa-solid fa-leaf"></i></div>
                    </div>

                    <div class="city-hero__floating-card">
                        <div class="city-hero__floating-card-icon">
                            <i class="fa-solid fa-truck-fast"></i>
                        </div>
                        <div>
                            <strong>Şu an {{ $page->city_name }}'a yolda</strong>
                            <small>{{ $page->shipping_note ?? 'Soğuk zincir kargo ile teslimat' }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="city-hero__wave" aria-hidden="true">
        <svg viewBox="0 0 1440 80" preserveAspectRatio="none">
            <path d="M0,32 C480,80 960,0 1440,48 L1440,80 L0,80 Z" fill="currentColor"></path>
        </svg>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════════
     BENEFITS — 3 büyük fayda kartı (numara prefix + premium hover)
     ═══════════════════════════════════════════════════════════════ --}}
<section class="city-benefits" aria-labelledby="city-benefits-title">
    <div class="city-benefits__bg-blob city-benefits__bg-blob--1" aria-hidden="true"></div>
    <div class="city-benefits__bg-blob city-benefits__bg-blob--2" aria-hidden="true"></div>
    <div class="container city-benefits__container">
        <div class="section-header text-center mb-5">
            <span class="section-badge"><i class="fa-solid fa-star"></i> Neden Bizi Tercih Ediyorlar</span>
            <h2 class="section-title" id="city-benefits-title">{{ $page->city_name }}'a Özel Hizmetimiz</h2>
            <p class="section-subtitle">Sıradan bir e-ticaret değil — Çorum'dan {{ $page->city_name }} kapınıza ulaşan aile değeri.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <article class="city-benefit-card city-benefit-card--green">
                    <span class="city-benefit-card__number">01</span>
                    <div class="city-benefit-card__icon city-benefit-card__icon--green">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>
                    <h3 class="city-benefit-card__title">Hızlı Soğuk Zincir Kargo</h3>
                    <p class="city-benefit-card__text">
                        Çorum'dan {{ $page->city_name }} adresinize <strong>{{ $page->delivery_time ?? '1-2 iş günü' }}</strong>
                        içinde teslimat. Soğuk zincir kesintisiz korunur, ürün tazeliği garantili.
                    </p>
                    <span class="city-benefit-card__arrow" aria-hidden="true">
                        <i class="fa-solid fa-arrow-right"></i>
                    </span>
                </article>
            </div>
            <div class="col-md-4">
                <article class="city-benefit-card city-benefit-card--gold">
                    <span class="city-benefit-card__number">02</span>
                    <div class="city-benefit-card__icon city-benefit-card__icon--gold">
                        <i class="fa-solid fa-leaf"></i>
                    </div>
                    <h3 class="city-benefit-card__title">%100 Doğal Köy Üretimi</h3>
                    <p class="city-benefit-card__text">
                        Hayvanlarımız doğal otla beslenir. Hiçbir katkı maddesi, koruyucu veya hormon yok.
                        Geleneksel yöntemlerle, ailecek üretim.
                    </p>
                    <span class="city-benefit-card__arrow" aria-hidden="true">
                        <i class="fa-solid fa-arrow-right"></i>
                    </span>
                </article>
            </div>
            <div class="col-md-4">
                <article class="city-benefit-card city-benefit-card--blue">
                    <span class="city-benefit-card__number">03</span>
                    <div class="city-benefit-card__icon city-benefit-card__icon--blue">
                        <i class="fa-solid fa-handshake"></i>
                    </div>
                    <h3 class="city-benefit-card__title">Doğrudan Üreticiden Kapınıza</h3>
                    <p class="city-benefit-card__text">
                        Aracı yok, distribütör yok. Çorum köyünde aile üretimi, doğrudan {{ $page->city_name }} adresinize.
                        Üretici sizi tanır, ihtiyacınız olduğunda <strong>WhatsApp'tan ulaşırsınız</strong>.
                    </p>
                    <span class="city-benefit-card__arrow" aria-hidden="true">
                        <i class="fa-solid fa-arrow-right"></i>
                    </span>
                </article>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════════
     ANA İÇERİK + SIDEBAR
     ═══════════════════════════════════════════════════════════════ --}}
<section class="city-content-section">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                <article class="city-landing-content">
                    @if($renderedContent ?? false)
                        {!! $renderedContent !!}
                    @else
                        <h2>{{ $page->city_name }}'a Kargo Süresi</h2>
                        <p>Çorum Büyük Palabıyık Köyü'nden {{ $page->city_name }} şehrine soğuk zincir kargo ile doğal köy ürünleri gönderiyoruz. Taze süt, köy peyniri, tereyağı, organik yumurta ve doğal balımız <strong>{{ $page->delivery_time ?? '1-3 iş günü' }}</strong> içinde kapınıza teslim edilir.</p>
                        <p>{{ $page->shipping_note ?? "Çorum'dan {$page->city_name}'a soğuk zincir kargo ile 1-2 iş günü içinde teslimat." }}</p>

                        <h2>Neden Çorum Köy Ürünleri?</h2>
                        <p>Aile işletmemiz <strong>Orhan Babanın Çiftliği</strong>, üç nesildir doğal yöntemlerle üretim yapan bir köy işletmesidir. Hayvanlarımız sadece doğal otla beslenir, ürünlerimizde hiçbir katkı maddesi kullanılmaz. Çorum'un bereketli toprakları, temiz havası ve doğal mera koşulları, ürünlerimizin doğal lezzetini garanti altına alır.</p>

                        <h2>Ürünlerimiz</h2>
                        <p>{{ $page->city_name }}'a gönderdiğimiz tüm doğal köy ürünlerimizi aşağıdaki listeden inceleyebilirsiniz. Her ürün, sipariş gününe en yakın tarihte hazırlanır ve {{ $page->city_name }} adresinize özenle gönderilir.</p>
                    @endif
                </article>
            </div>

            {{-- Sticky Sidebar --}}
            <aside class="col-lg-4">
                <div class="city-sidebar">
                    <div class="city-sidebar__card">
                        <div class="city-sidebar__card-header">
                            <div class="city-sidebar__card-icon">
                                <i class="fa-solid fa-map-location-dot"></i>
                            </div>
                            <div>
                                <small class="city-sidebar__card-eyebrow">Hizmet Bölgesi</small>
                                <h3 class="city-sidebar__title">{{ $page->city_name }}</h3>
                            </div>
                        </div>
                        <ul class="city-sidebar__list">
                            @if($page->region)
                            <li>
                                <i class="fa-solid fa-map-pin"></i>
                                <div><strong>Bölge</strong><span>{{ $page->region }}</span></div>
                            </li>
                            @endif
                            <li>
                                <i class="fa-solid fa-clock"></i>
                                <div><strong>Teslimat</strong><span>{{ $page->delivery_time ?? '1-3 iş günü' }}</span></div>
                            </li>
                            <li>
                                <i class="fa-solid fa-snowflake"></i>
                                <div><strong>Kargo</strong><span>Soğuk zincir korumalı</span></div>
                            </li>
                        </ul>
                    </div>

                    {{-- Mini Stats Kart --}}
                    <div class="city-sidebar__stats">
                        <div class="city-sidebar__stat">
                            <strong>3</strong>
                            <span>Nesil Aile Çiftliği</span>
                        </div>
                        <div class="city-sidebar__stat">
                            <strong>%100</strong>
                            <span>Doğal Üretim</span>
                        </div>
                        <div class="city-sidebar__stat">
                            <strong>0</strong>
                            <span>Katkı Maddesi</span>
                        </div>
                    </div>

                    @if($whatsapp)
                    <div class="city-sidebar__whatsapp">
                        <div class="city-sidebar__whatsapp-pulse" aria-hidden="true"></div>
                        <i class="fa-brands fa-whatsapp city-sidebar__whatsapp-icon"></i>
                        <h4 class="city-sidebar__whatsapp-title">Bir sorunuz mu var?</h4>
                        <p>{{ $page->city_name }} sevkiyatı ve ürünlerimiz hakkında WhatsApp'tan anında bilgi alabilirsiniz.</p>
                        <a href="{{ whatsapp_url($whatsapp, $whatsappMessage) }}" target="_blank" rel="noopener noreferrer" class="city-sidebar__whatsapp-btn">
                            <i class="fa-brands fa-whatsapp"></i> Hemen Mesaj Gönder
                        </a>
                    </div>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════════
     ÜRÜN VİTRİNİ
     ═══════════════════════════════════════════════════════════════ --}}
@if($products->isNotEmpty())
<section class="city-products-section" id="urunler" aria-labelledby="city-products-title">
    <div class="container">
        <div class="section-header text-center mb-5">
            <span class="section-badge"><i class="fa-solid fa-basket-shopping"></i> {{ $page->city_name }}'a Gönderiyoruz</span>
            <h2 class="section-title" id="city-products-title">Doğal Köy Ürünlerimiz</h2>
            <p class="section-subtitle">{{ $products->count() }} farklı doğal ürün — soğuk zincir kargo ile {{ $page->city_name }} adresinize özenle teslim edilir</p>
        </div>

        <div class="row g-4">
            @foreach($products as $product)
            <div class="col-md-6 col-lg-4">
                <article class="pcard">
                    <div class="pcard__image">
                        @if($product->is_featured)
                        <span class="pcard__badge">Öne Çıkan</span>
                        @endif
                        @if($product->is_new)
                        <span class="pcard__badge pcard__badge--new">Yeni</span>
                        @endif
                        @if($product->cover_image)
                        <x-responsive-image :path="$product->cover_image" :alt="$product->name" size="md" />
                        @else
                        <i class="fa-solid fa-box-open pcard__icon"></i>
                        @endif
                    </div>
                    <div class="pcard__content">
                        <div class="pcard__category">{{ $product->category?->name }}</div>
                        <h3 class="pcard__title">
                            <a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a>
                        </h3>
                        @if($product->short_description)
                        <p class="pcard__desc">{{ Str::limit($product->short_description, 100) }}</p>
                        @endif
                        @if(($product->reviews_count ?? 0) > 0)
                        <div class="pcard__rating">
                            @php $avg = round(($product->reviews_avg ?? 0) * 2) / 2; @endphp
                            @for($i = 1; $i <= 5; $i++)
                                @if($i <= floor($avg))
                                    <i class="fa-solid fa-star"></i>
                                @elseif($i - 0.5 <= $avg)
                                    <i class="fa-solid fa-star-half-stroke"></i>
                                @else
                                    <i class="fa-regular fa-star"></i>
                                @endif
                            @endfor
                            <span>{{ number_format((float) ($product->reviews_avg ?? 0), 1) }} ({{ $product->reviews_count }})</span>
                        </div>
                        @endif
                        <div class="pcard__footer">
                            <div class="pcard__price">
                                @if($product->hasDiscount())
                                <span class="pcard__price-old">{{ number_format($product->discounted_price, 0) }} ₺</span>
                                @endif
                                {{ number_format($product->price, 0) }} ₺
                                <span>/ {{ $product->unit }}</span>
                            </div>
                            <a href="{{ route('products.show', $product->slug) }}" class="pcard__btn"
                               aria-label="{{ $product->name }} detayını gör">
                                <i class="fa-solid fa-plus"></i>
                            </a>
                        </div>
                    </div>
                </article>
            </div>
            @endforeach
        </div>

        <div class="text-center mt-5">
            <a href="{{ route('products.all') }}" class="btn-custom btn-lg">
                <i class="fa-solid fa-th-large me-2"></i>Tüm Ürünleri Gör
            </a>
        </div>
    </div>
</section>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     ÇORUM → ŞEHİR YOLCULUĞU
     ═══════════════════════════════════════════════════════════════ --}}
<section class="city-journey" aria-labelledby="city-journey-title">
    <div class="city-journey__bg-pattern" aria-hidden="true"></div>
    <div class="container city-journey__container">
        <div class="section-header text-center mb-5">
            <span class="section-badge"><i class="fa-solid fa-route"></i> Çorum'dan {{ $page->city_name }}'a</span>
            <h2 class="section-title" id="city-journey-title">Soğuk Zincir Kargo Yolculuğu</h2>
            <p class="section-subtitle">Sipariş verdikten 60 saniye sonra başlayan, kapınızda biten 4 adımlı süreç</p>
        </div>

        <div class="city-journey__timeline">
            <div class="city-journey__step">
                <span class="city-journey__step-number">01</span>
                <div class="city-journey__step-icon"><i class="fa-solid fa-cow"></i></div>
                <strong class="city-journey__step-title">Üretim</strong>
                <p class="city-journey__step-text">Çorum köyünde geleneksel yöntemlerle taze üretim</p>
            </div>
            <div class="city-journey__connector" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></div>
            <div class="city-journey__step">
                <span class="city-journey__step-number">02</span>
                <div class="city-journey__step-icon"><i class="fa-solid fa-box"></i></div>
                <strong class="city-journey__step-title">Hazırlık</strong>
                <p class="city-journey__step-text">Aynı gün soğuk zincir paketleme ve kargoya teslim</p>
            </div>
            <div class="city-journey__connector" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></div>
            <div class="city-journey__step">
                <span class="city-journey__step-number">03</span>
                <div class="city-journey__step-icon"><i class="fa-solid fa-truck-fast"></i></div>
                <strong class="city-journey__step-title">Yola Çıkış</strong>
                <p class="city-journey__step-text">Anlaşmalı kargo firması ile {{ $page->city_name }} hattı</p>
            </div>
            <div class="city-journey__connector" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></div>
            <div class="city-journey__step city-journey__step--final">
                <span class="city-journey__step-number">04</span>
                <div class="city-journey__step-icon"><i class="fa-solid fa-house-circle-check"></i></div>
                <strong class="city-journey__step-title">Teslimat</strong>
                <p class="city-journey__step-text">{{ $page->delivery_time ?? '1-2 iş günü' }} içinde {{ $page->city_name }} adresinize</p>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════════
     SSS / FAQ Accordion
     ═══════════════════════════════════════════════════════════════ --}}
<section class="city-faq-section" aria-labelledby="city-faq-title">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="section-header text-center mb-5">
                    <span class="section-badge"><i class="fa-solid fa-circle-question"></i> Sıkça Sorulan</span>
                    <h2 class="section-title" id="city-faq-title">{{ $page->city_name }} İçin Bilmeniz Gerekenler</h2>
                </div>

                <div class="city-faq accordion" id="cityFaqAccordion">
                    @foreach($cityFaqs as $i => $faq)
                    <div class="city-faq__item accordion-item">
                        <h3 class="accordion-header">
                            <button class="city-faq__btn accordion-button {{ $i === 0 ? '' : 'collapsed' }}"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#cityFaq{{ $i }}"
                                    aria-expanded="{{ $i === 0 ? 'true' : 'false' }}"
                                    aria-controls="cityFaq{{ $i }}">
                                {{ $faq['q'] }}
                            </button>
                        </h3>
                        <div id="cityFaq{{ $i }}"
                             class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}"
                             data-bs-parent="#cityFaqAccordion">
                            <div class="city-faq__body accordion-body">
                                {{ $faq['a'] }}
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════════
     BÜYÜK CTA — floating shapes + live status badge
     ═══════════════════════════════════════════════════════════════ --}}
<section class="city-big-cta" aria-labelledby="city-big-cta-title">
    <div class="city-big-cta__bg" aria-hidden="true"></div>
    <div class="city-big-cta__shape city-big-cta__shape--1" aria-hidden="true"></div>
    <div class="city-big-cta__shape city-big-cta__shape--2" aria-hidden="true"></div>
    <div class="city-big-cta__shape city-big-cta__shape--3" aria-hidden="true"></div>

    <div class="container city-big-cta__container">
        <div class="city-big-cta__live-badge">
            <span class="city-big-cta__live-dot" aria-hidden="true"></span>
            <span>Şu an sipariş alıyoruz</span>
        </div>

        <div class="city-big-cta__icon" aria-hidden="true">
            <i class="fa-solid fa-basket-shopping"></i>
        </div>

        <h2 class="city-big-cta__title" id="city-big-cta-title">
            {{ $page->city_name }}'a Sipariş Vermeye Hazır mısınız?
        </h2>
        <p class="city-big-cta__lead">
            Çorum köyünden doğal lezzetleri {{ $page->delivery_time ?? '1-2 iş günü' }} içinde {{ $page->city_name }} adresinize gönderelim.
            Sorularınız varsa WhatsApp'tan hemen ulaşın.
        </p>
        <div class="city-big-cta__buttons">
            <a href="#urunler" class="city-big-cta__btn city-big-cta__btn--primary">
                <i class="fa-solid fa-shopping-basket"></i>
                Hemen Sipariş Ver
            </a>
            @if($whatsapp)
            <a href="{{ whatsapp_url($whatsapp, $whatsappMessage) }}" target="_blank" rel="noopener noreferrer" class="city-big-cta__btn city-big-cta__btn--whatsapp">
                <i class="fa-brands fa-whatsapp"></i>
                WhatsApp ile İletişim
            </a>
            @endif
        </div>

        <div class="city-big-cta__trust-row" aria-hidden="true">
            <span><i class="fa-solid fa-shield-halved"></i> Güvenli Ödeme</span>
            <span><i class="fa-solid fa-snowflake"></i> Soğuk Zincir</span>
            <span><i class="fa-solid fa-leaf"></i> %100 Doğal</span>
        </div>
    </div>
</section>

@endsection
