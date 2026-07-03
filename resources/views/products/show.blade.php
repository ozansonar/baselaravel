@extends('layouts.app')

{{-- Modül 3 (SEO): title/meta_description ProductSeoService üzerinden gelir.
     Admin meta_title/meta_description girdiyse onlar; aksi halde
     "Doğal {ürün} — Çorum Köy Ürünleri | {site}" /
     "Çorum Büyük Palabıyık Köyü'nden {ürün}. {kısa_açıklama}. ..." pattern'i. --}}
@section('title', $seoTitle)
@section('meta_description', $seoDescription)
@section('canonical', route('products.show', $product->slug))
@if($product->image)
@section('og_image', url(upload_url($product->image, 'lg')))
@endif

@if($product->image)
@push('preload')
<link rel="preload" as="image" href="{{ upload_url($product->image, 'lg') }}" fetchpriority="high">
@endpush
@endif

@push('json-ld')
@php
    $siteName = \App\Models\Setting::getValue('site_name', config('app.name'));

    $productJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'description' => $seoDescription,
        'sku' => $product->sku ?? $product->slug,
        'brand' => [
            '@type' => 'Brand',
            'name' => $siteName,
        ],
        'offers' => [
            '@type' => 'Offer',
            'price' => number_format($product->currentPrice(), 2, '.', ''),
            'priceCurrency' => 'TRY',
            'availability' => $product->inStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url' => route('products.show', $product->slug),
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => [
                '@type' => 'Organization',
                'name' => $siteName,
            ],
            'shippingDetails' => [
                '@type' => 'OfferShippingDetails',
                'shippingDestination' => [
                    '@type' => 'DefinedRegion',
                    'addressCountry' => 'TR',
                ],
                'deliveryTime' => [
                    '@type' => 'ShippingDeliveryTime',
                    'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY'],
                    'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 3, 'unitCode' => 'DAY'],
                ],
                'shippingRate' => [
                    '@type' => 'MonetaryAmount',
                    'value' => \App\Models\Setting::getValue('shipping_fee', '39.90'),
                    'currency' => 'TRY',
                ],
            ],
            'hasMerchantReturnPolicy' => [
                '@type' => 'MerchantReturnPolicy',
                'applicableCountry' => 'TR',
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
                'merchantReturnDays' => 14,
                'returnMethod' => 'https://schema.org/ReturnByMail',
                'returnFees' => 'https://schema.org/FreeReturn',
            ],
        ],
        'breadcrumb' => [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => route('home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $product->category?->name, 'item' => route('products.index', $product->category?->slug ?? '')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $product->name],
            ],
        ],
    ];
    if ($product->image) {
        $productJsonLd['image'] = url(upload_url($product->image, 'lg'));
    }
    if ($reviewStats['total'] > 0) {
        $productJsonLd['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => (string) $reviewStats['average'],
            'reviewCount' => (string) $reviewStats['total'],
        ];
    }
@endphp
<script type="application/ld+json">{!! json_encode($productJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')

{{-- Breadcrumb --}}
<section class="breadcrumb-section">
    <div class="container">
        <nav class="breadcrumb-custom" aria-label="Breadcrumb">
            <a href="{{ route('home') }}"><i class="fa-solid fa-home"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <a href="{{ route('products.index', $product->category?->slug ?? '') }}">{{ $product->category?->name }}</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>{{ $product->name }}</span>
        </nav>
    </div>
</section>

{{-- Product Section --}}
<section class="product-detail-section" aria-labelledby="product-title">
    <div class="container">
        <div class="row">
            {{-- Product Gallery --}}
            <div class="col-lg-6">
                <div class="product-gallery animate-fade-left">
                    <div class="main-image-container">
                        <div class="product-main-image" id="mainImage">
                            {{-- Badges --}}
                            <div class="product-badges">
                                @if($product->is_featured)
                                <div class="product-badge badge-bestseller">
                                    <i class="fa-solid fa-fire"></i> En Çok Satan
                                </div>
                                @endif
                                @if($product->is_new)
                                <div class="product-badge badge-new">
                                    <i class="fa-solid fa-sparkles"></i> Yeni
                                </div>
                                @endif
                                <div class="product-badge badge-organic">
                                    <i class="fa-solid fa-leaf"></i> %100 Organik
                                </div>
                                @if($product->inStock())
                                <div class="product-badge badge-fresh">
                                    <i class="fa-solid fa-clock"></i> Günlük Taze
                                </div>
                                @endif
                            </div>

                            {{-- Main Image --}}
                            @if($product->image)
                            <x-responsive-image :path="$product->image" :alt="$product->name"
                                                size="lg" class="product-detail-img" :responsive="true" loading="eager" fetchpriority="high" />
                            <div class="zoom-overlay" id="zoomOverlay" role="button" aria-label="Görseli büyüt">
                                <i class="fa-solid fa-magnifying-glass-plus"></i>
                            </div>
                            @else
                            <i class="fa-solid fa-image product-icon"></i>
                            @endif
                        </div>
                    </div>

                    {{-- Hidden gallery links for GLightbox --}}
                    @if($product->image)
                    <div id="productLightboxGallery" class="d-none">
                        <a href="{{ upload_url($product->image) }}" class="glightbox" data-gallery="product-gallery"
                           data-description="{{ $product->name }}"></a>
                        @foreach($product->images as $image)
                        <a href="{{ upload_url($image->image) }}" class="glightbox" data-gallery="product-gallery"
                           data-description="{{ $image->alt_text ?? $product->name }}"></a>
                        @endforeach
                    </div>
                    @endif

                    {{-- Thumbnail Gallery --}}
                    @if($product->images->isNotEmpty())
                    <div class="thumbnail-gallery-wrapper">
                        <button class="thumb-arrow thumb-arrow--prev" type="button" aria-label="Önceki görseller">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <div class="thumbnail-gallery" id="thumbnailGallery">
                            <div class="thumbnail active" data-index="0">
                                @if($product->image)
                                <x-responsive-image :path="$product->image" :alt="$product->name"
                                                    size="thumb" class="thumbnail-img" />
                                @else
                                <i class="fa-solid fa-image"></i>
                                @endif
                            </div>
                            @foreach($product->images as $index => $image)
                            <div class="thumbnail" data-index="{{ $index + 1 }}">
                                <x-responsive-image :path="$image->image" :alt="$image->alt_text ?? $product->name"
                                                    size="thumb" class="thumbnail-img" />
                            </div>
                            @endforeach
                        </div>
                        <button class="thumb-arrow thumb-arrow--next" type="button" aria-label="Sonraki görseller">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                    @endif

                    {{-- Trust / Info Card --}}
                    <div class="product-trust-card">
                        <div class="product-trust-grid">
                            <div class="product-trust-item">
                                <div class="product-trust-icon">
                                    <i class="fa-solid fa-leaf"></i>
                                </div>
                                <div>
                                    <strong>%100 Doğal</strong>
                                    <small>Katkısız, hormonsuz üretim</small>
                                </div>
                            </div>
                            <div class="product-trust-item">
                                <div class="product-trust-icon">
                                    <i class="fa-solid fa-truck-fast"></i>
                                </div>
                                <div>
                                    <strong>Hızlı Kargo</strong>
                                    <small>Soğuk zincir teslimat</small>
                                </div>
                            </div>
                            <div class="product-trust-item">
                                <div class="product-trust-icon">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </div>
                                <div>
                                    <strong>Güvenli Ödeme</strong>
                                    <small>256-bit SSL korumalı</small>
                                </div>
                            </div>
                            <div class="product-trust-item">
                                <div class="product-trust-icon">
                                    <i class="fa-solid fa-location-dot"></i>
                                </div>
                                <div>
                                    <strong>Çorum Köyünden</strong>
                                    <small>Büyük Palabıyık Köyü</small>
                                </div>
                            </div>
                            @if($reviewStats['total'] > 0)
                            <div class="product-trust-item">
                                <div class="product-trust-icon product-trust-icon--gold">
                                    <i class="fa-solid fa-star"></i>
                                </div>
                                <div>
                                    <strong>{{ number_format($reviewStats['average'], 1) }} / 5 Puan</strong>
                                    <small>{{ $reviewStats['total'] }} müşteri değerlendirmesi</small>
                                </div>
                            </div>
                            @endif
                            <div class="product-trust-item">
                                <div class="product-trust-icon">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </div>
                                <div>
                                    <strong>Koşulsuz İade</strong>
                                    <small>Memnuniyet garantisi</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Product Info --}}
            <div class="col-lg-6">
                <div class="product-info">
                    <div class="product-category">
                        <i class="fa-solid fa-tag"></i> {{ $product->category?->name }}
                    </div>
                    <h1 class="product-title" id="product-title">{{ $product->name }}</h1>

                    @if($product->short_description)
                    <p class="product-description">{{ $product->short_description }}</p>
                    @endif

                    {{-- Price Box --}}
                    <div class="product-price-box">
                        <div class="price-row">
                            @if($product->hasDiscount())
                            <span class="current-price">{{ number_format($product->price, 2) }} ₺</span>
                            <span class="old-price">{{ number_format($product->discounted_price, 2) }} ₺</span>
                            <span class="discount-badge">%{{ round((1 - $product->price / $product->discounted_price) * 100) }} İndirim</span>
                            @else
                            <span class="current-price">{{ number_format($product->price, 2) }} ₺</span>
                            @endif
                            <span class="price-unit">/{{ $product->unit }}</span>
                        </div>
                        @php
                            $priceNote = $product->shippingItems->where('type', 'shipping')->first();
                        @endphp
                        @if($priceNote)
                        <p class="price-note">
                            <i class="fa-solid fa-truck"></i> {{ $priceNote->content }}
                        </p>
                        @endif
                    </div>

                    {{-- Stock --}}
                    <div class="stock-info">
                        @if($product->inStock())
                        <span class="stock-badge stock-in">
                            <i class="fa-solid fa-check-circle"></i> Stokta ({{ $product->stock }} {{ $product->unit }})
                        </span>
                        @else
                        <span class="stock-badge stock-out">
                            <i class="fa-solid fa-times-circle"></i> Stokta Yok
                        </span>
                        @endif
                    </div>

                    {{-- Quantity & Add to Cart --}}
                    @if($product->inStock())
                    <div class="quantity-section">
                        <label class="quantity-label">Miktar Seçin</label>
                        <div class="quantity-selector">
                            <button class="qty-btn" id="qtyMinus" type="button">-</button>
                            <input type="number" class="qty-input" id="qtyInput" value="1" min="1" max="{{ $product->stock }}" readonly>
                            <button class="qty-btn" id="qtyPlus" type="button">+</button>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-add-cart" id="addToCartBtn" type="button" data-product-id="{{ $product->id }}">
                            <i class="fa-solid fa-shopping-cart"></i>
                            <span>Sepete Ekle</span>
                        </button>
                        @php
                            $whatsapp = \App\Models\Setting::getValue('social_whatsapp');
                            $whatsappMessage = "Merhaba,\n\n"
                                . "\"" . $product->name . "\" ürününüz hakkında bilgi almak istiyorum.\n"
                                . "Fiyat: " . number_format($product->price, 2, ',', '.') . " ₺/" . $product->unit . "\n\n"
                                . "Teşekkürler.";
                        @endphp
                        @if($whatsapp)
                        <a href="{{ whatsapp_url($whatsapp, $whatsappMessage) }}" class="btn-whatsapp-order" target="_blank" rel="noopener noreferrer">
                            <i class="fa-brands fa-whatsapp"></i> WhatsApp ile Bilgi Al
                        </a>
                        @endif
                    </div>
                    @endif

                    {{-- Product Features --}}
                    @if($product->features->isNotEmpty())
                    <div class="product-features">
                        @foreach($product->features as $feature)
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="{{ $feature->icon }}"></i>
                            </div>
                            <span class="feature-text">{{ $feature->text }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    {{-- Social Share --}}
                    @include('partials.social-share', [
                        'url'         => route('products.show', $product->slug),
                        'title'       => $product->name . ' — Orhan Babanın Çiftliği',
                        'description' => $product->short_description ?? '',
                        'image'       => $product->image ? url(upload_url($product->image, 'lg')) : '',
                    ])
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Tabs Section --}}
<section class="tabs-section">
    <div class="container">
        <div class="custom-tabs">
            <button class="tab-btn active" data-tab="tab-description" type="button">
                <i class="fa-solid fa-info-circle me-2"></i>Ürün Detayları
            </button>
            <button class="tab-btn" data-tab="tab-nutrition" type="button">
                <i class="fa-solid fa-apple-alt me-2"></i>Besin Değerleri
            </button>
            @if($product->allow_reviews)
            <button class="tab-btn" data-tab="tab-reviews" type="button">
                <i class="fa-solid fa-comments me-2"></i>Değerlendirmeler
                @if($reviewStats['total'] > 0)
                    ({{ $reviewStats['total'] }})
                @endif
            </button>
            @endif
            <button class="tab-btn" data-tab="tab-shipping" type="button">
                <i class="fa-solid fa-truck me-2"></i>Kargo & İade
            </button>
            @if($productFaqs->isNotEmpty())
            <button class="tab-btn" data-tab="tab-faq" type="button">
                <i class="fa-solid fa-circle-question me-2"></i>SSS ({{ $productFaqs->count() }})
            </button>
            @endif
        </div>

        {{-- Description Tab --}}
        <div class="tab-content active" id="tab-description">
            <div class="row">
                <div class="col-lg-8">
                    <div class="description-content">
                        @if($product->description)
                        {!! $product->description !!}
                        @else
                        <h3>{{ $product->name }}</h3>
                        <p>{{ $product->short_description }}</p>
                        @endif
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="info-box">
                        <h5 class="info-box-title">
                            <i class="fa-solid fa-award me-2 icon-gold"></i>Ürün Bilgileri
                        </h5>
                        <ul class="description-list">
                            @if($product->sku)
                            <li><i class="fa-solid fa-barcode"></i> SKU: {{ $product->sku }}</li>
                            @endif
                            <li><i class="fa-solid fa-box"></i> Birim: {{ $product->unit }}</li>
                            <li><i class="fa-solid fa-tag"></i> Kategori: {{ $product->category?->name }}</li>
                            @if($product->inStock())
                            <li><i class="fa-solid fa-check-circle"></i> Stok: {{ $product->stock }} {{ $product->unit }}</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- Nutrition Tab --}}
        <div class="tab-content" id="tab-nutrition">
            <div class="row">
                <div class="col-lg-8">
                    @if($product->nutritions->count() > 0)
                    <div class="nutrition-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Besin Değerleri</th>
                                    <th>100g Başına</th>
                                    <th>Günlük Değer %</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($product->nutritions as $nutrition)
                                <tr>
                                    <td><strong>{{ $nutrition->name }}</strong></td>
                                    <td>{{ $nutrition->per_value ?? '-' }}</td>
                                    <td>{{ $nutrition->daily_value ?? '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-5">
                        <i class="fa-solid fa-clipboard-list fa-3x mb-3 opacity-low"></i>
                        <p class="text-muted">Bu ürün için besin değerleri bilgisi henüz eklenmemiştir.</p>
                    </div>
                    @endif
                </div>
                <div class="col-lg-4">
                    <div class="info-box mt-4 mt-lg-0">
                        <h5 class="info-box-title mb-3">
                            <i class="fa-solid fa-award me-2 icon-gold"></i>Ürün Bilgileri
                        </h5>
                        <ul class="description-list">
                            @if($product->sku)
                            <li><i class="fa-solid fa-barcode"></i> SKU: {{ $product->sku }}</li>
                            @endif
                            <li><i class="fa-solid fa-box"></i> Birim: {{ $product->unit }}</li>
                            <li><i class="fa-solid fa-tag"></i> Kategori: {{ $product->category?->name }}</li>
                            @if($product->inStock())
                            <li><i class="fa-solid fa-check-circle"></i> Stok: {{ $product->stock }} {{ $product->unit }}</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- Reviews Tab --}}
        @if($product->allow_reviews)
        <div class="tab-content" id="tab-reviews">
            <div class="reviews-header">
                <div class="reviews-summary-card">
                    <div class="reviews-summary-left">
                        <div class="big-rating">{{ $reviewStats['average'] > 0 ? number_format($reviewStats['average'], 1) : '-' }}</div>
                        <div class="big-rating-label">
                            <div class="stars">
                                @php $avg = $reviewStats['average']; @endphp
                                @for($i = 1; $i <= 5; $i++)
                                    @if($avg >= $i)
                                        <i class="fa-solid fa-star"></i>
                                    @elseif($avg >= $i - 0.5)
                                        <i class="fa-solid fa-star-half-alt"></i>
                                    @else
                                        <i class="fa-regular fa-star"></i>
                                    @endif
                                @endfor
                            </div>
                            <span class="reviews-count">{{ $reviewStats['total'] }} değerlendirme</span>
                        </div>
                    </div>
                    <div class="reviews-summary-divider"></div>
                    <div class="reviews-summary-right">
                        <div class="rating-breakdown">
                            @for($i = 5; $i >= 1; $i--)
                            <div class="rating-bar">
                                <span class="rating-bar__star">{{ $i }} <i class="fa-solid fa-star"></i></span>
                                <div class="bar"><div class="bar-fill" data-width="{{ $reviewStats['distribution'][$i] ?? 0 }}"></div></div>
                                <span class="rating-bar__pct">%{{ $reviewStats['distribution'][$i] ?? 0 }}</span>
                            </div>
                            @endfor
                        </div>
                    </div>
                </div>
                <button class="btn-write-review" id="btnWriteReview" type="button">
                    <i class="fa-solid fa-pen me-2"></i>Değerlendirme Yaz
                </button>
            </div>

            {{-- Review Form --}}
            <div class="review-form-wrapper" id="reviewFormWrapper">
                <div class="review-form-card">
                    <h4 class="review-form-title">
                        <i class="fa-solid fa-pen-to-square me-2"></i>Değerlendirme Yaz
                    </h4>
                    <form id="reviewForm">
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">Ad Soyad <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-input-custom" placeholder="Adınız Soyadınız" required>
                                <div class="invalid-feedback-custom" data-field="name"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Telefon</label>
                                <input type="tel" name="phone" class="form-input-custom" placeholder="05XX XXX XX XX">
                                <div class="invalid-feedback-custom" data-field="phone"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">E-posta <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-input-custom" placeholder="ornek@email.com" required>
                                <div class="invalid-feedback-custom" data-field="email"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Puanınız <span class="text-danger">*</span></label>
                                <div class="star-rating-input" id="starRatingInput">
                                    <input type="hidden" name="rating" id="ratingInput" value="0">
                                    <i class="fa-regular fa-star" data-star="1"></i>
                                    <i class="fa-regular fa-star" data-star="2"></i>
                                    <i class="fa-regular fa-star" data-star="3"></i>
                                    <i class="fa-regular fa-star" data-star="4"></i>
                                    <i class="fa-regular fa-star" data-star="5"></i>
                                    <span class="star-rating-text" id="starRatingText"></span>
                                </div>
                                <div class="invalid-feedback-custom" data-field="rating"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label-custom">Yorumunuz <span class="text-danger">*</span></label>
                                <textarea name="comment" class="form-input-custom" rows="4" placeholder="Ürün hakkındaki düşüncelerinizi yazın... (En az 10 karakter)" required></textarea>
                                <div class="invalid-feedback-custom" data-field="comment"></div>
                            </div>
                            <div class="col-12">
                                <x-recaptcha />
                            </div>
                            <div class="col-12">
                                <div class="review-form-actions">
                                    <button type="submit" class="btn-submit-review" id="btnSubmitReview">
                                        <i class="fa-solid fa-paper-plane me-2"></i>Gönder
                                    </button>
                                    <button type="button" class="btn-cancel-review" id="btnCancelReview">İptal</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Success Message --}}
            <div class="review-success-message" id="reviewSuccessMessage">
                <i class="fa-solid fa-check-circle"></i>
                <p>Değerlendirmeniz başarıyla gönderildi. Yönetici onayından sonra yayınlanacaktır.</p>
            </div>

            <div class="reviews-list">
                @forelse($approvedReviews as $review)
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <div class="reviewer-avatar">{{ $review->initials() }}</div>
                            <div>
                                <div class="reviewer-name">{{ $review->name }}</div>
                                <div class="review-date">{{ $review->created_at->translatedFormat('d F Y') }}</div>
                            </div>
                        </div>
                        <div class="review-rating">
                            @for($i = 1; $i <= 5; $i++)
                                @if($i <= $review->rating)
                                    <i class="fa-solid fa-star"></i>
                                @else
                                    <i class="fa-regular fa-star"></i>
                                @endif
                            @endfor
                        </div>
                    </div>
                    <p class="review-text">{{ $review->comment }}</p>
                </div>
                @empty
                <div class="no-reviews-message">
                    <div class="no-reviews-icon">
                        <i class="fa-regular fa-comment-dots"></i>
                    </div>
                    <h5 class="no-reviews-title">Henüz Değerlendirme Yok</h5>
                    <p class="no-reviews-text">Bu ürünü denediniz mi? Deneyiminizi paylaşarak diğer müşterilere yardımcı olun.</p>
                    <button class="btn-first-review" type="button" onclick="document.getElementById('btnWriteReview').click()">
                        <i class="fa-solid fa-pen me-2"></i>İlk Değerlendirmeyi Yaz
                    </button>
                </div>
                @endforelse
            </div>
        </div>
        @endif

        {{-- Shipping Tab --}}
        {{-- Shipping & Return Tab --}}
        @php
            $shippingInfos = $product->shippingItems->where('type', 'shipping');
            $returnInfos = $product->shippingItems->where('type', 'return');
        @endphp
        <div class="tab-content" id="tab-shipping">
            @if($shippingInfos->isNotEmpty() || $returnInfos->isNotEmpty())
            <div class="row g-4">
                @if($shippingInfos->isNotEmpty())
                <div class="col-md-6">
                    <div class="info-box h-100">
                        <h5 class="info-box-title">
                            <i class="fa-solid fa-truck me-2 icon-green"></i>Kargo Bilgileri
                        </h5>
                        <ul class="description-list">
                            @foreach($shippingInfos as $item)
                            <li><i class="fa-solid fa-check-circle"></i> {{ $item->content }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                @endif
                @if($returnInfos->isNotEmpty())
                <div class="col-md-6">
                    <div class="info-box-white h-100">
                        <h5 class="info-box-title">
                            <i class="fa-solid fa-undo me-2 icon-green"></i>İade Politikası
                        </h5>
                        <ul class="description-list">
                            @foreach($returnInfos as $item)
                            <li><i class="fa-solid fa-check-circle"></i> {{ $item->content }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                @endif
            </div>
            @else
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="shipping-card">
                        <div class="shipping-card__icon">
                            <i class="fa-solid fa-truck-fast"></i>
                        </div>
                        <h5 class="shipping-card__title">Hızlı Kargo</h5>
                        <p class="shipping-card__text">Siparişleriniz özenle paketlenerek en kısa sürede kapınıza ulaştırılır.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="shipping-card">
                        <div class="shipping-card__icon shipping-card__icon--fresh">
                            <i class="fa-solid fa-snowflake"></i>
                        </div>
                        <h5 class="shipping-card__title">Soğuk Zincir</h5>
                        <p class="shipping-card__text">Ürünlerimiz tazeliğini koruyacak şekilde soğuk zincir ile gönderilir.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="shipping-card">
                        <div class="shipping-card__icon shipping-card__icon--return">
                            <i class="fa-solid fa-rotate-left"></i>
                        </div>
                        <h5 class="shipping-card__title">Kolay İade</h5>
                        <p class="shipping-card__text">Memnun kalmadığınız ürünleri kolayca iade edebilirsiniz.</p>
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- FAQ Tab --}}
        @if($productFaqs->isNotEmpty())
        <div class="tab-content" id="tab-faq">
            <div class="faq-accordion">
                @foreach($productFaqs as $faqItem)
                <div class="faq-item {{ $loop->first ? 'active' : '' }}">
                    <button class="faq-question" type="button" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                        <span>{{ $faqItem->question }}</span>
                        <i class="fa-solid fa-chevron-down faq-arrow"></i>
                    </button>
                    <div class="faq-answer {{ $loop->first ? '' : 'd-none' }}">
                        <p>{{ $faqItem->answer }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</section>

{{-- Product FAQ Schema.org --}}
@if($productFaqs->isNotEmpty())
@push('json-ld')
@php
    $faqJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $productFaqs->map(fn ($f) => [
            '@type' => 'Question',
            'name' => $f->question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $f->answer,
            ],
        ])->values()->all(),
    ];
@endphp
<script type="application/ld+json">{!! json_encode($faqJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
@endif

{{-- Modül 8 — İlgili Blog Yazıları (Topic Cluster hub) --}}
@if(($relatedBlogPosts ?? collect())->isNotEmpty())
<section class="related-blog-section py-5">
    <div class="container">
        <div class="section-header text-center mb-5">
            <div class="section-badge">
                <i class="fa-solid fa-book-open"></i> {{ $product->name }} Hakkında
            </div>
            <h2 class="section-title">İlgili Yazılar</h2>
            <p class="section-subtitle">
                {{ $product->name }} hakkında detaylı rehberlerimiz — saklama, faydalar, tarifler ve daha fazlası.
            </p>
        </div>

        <div class="row g-4">
            @foreach($relatedBlogPosts as $relatedPost)
            <div class="col-md-6 col-lg-4">
                <article class="related-blog-card h-100">
                    @if($relatedPost->image)
                    <a href="{{ route('blog.show', [$relatedPost->category->slug, $relatedPost->slug]) }}" class="related-blog-card__image">
                        <img src="{{ upload_url($relatedPost->image, 'md') }}" alt="{{ $relatedPost->title }}" loading="lazy" decoding="async">
                    </a>
                    @endif
                    <div class="related-blog-card__body">
                        @if($relatedPost->category)
                        <div class="related-blog-card__category">
                            <i class="fa-solid fa-folder"></i> {{ $relatedPost->category->name }}
                        </div>
                        @endif
                        <h3 class="related-blog-card__title">
                            <a href="{{ route('blog.show', [$relatedPost->category->slug, $relatedPost->slug]) }}">
                                {{ $relatedPost->title }}
                            </a>
                        </h3>
                        @if($relatedPost->excerpt)
                        <p class="related-blog-card__excerpt">{{ Str::limit($relatedPost->excerpt, 100) }}</p>
                        @endif
                        <a href="{{ route('blog.show', [$relatedPost->category->slug, $relatedPost->slug]) }}" class="related-blog-card__link">
                            Yazıyı Oku <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </article>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Related Products --}}
@if($product->show_related && $relatedProducts->isNotEmpty())
<section class="related-section">
    <div class="container">
        <div class="section-header">
            <div class="section-badge">
                <i class="fa-solid fa-heart"></i> Bunları da Beğenebilirsiniz
            </div>
            <h2 class="section-title">Benzer Ürünler</h2>
        </div>
        <div class="row g-4">
            @foreach($relatedProducts as $related)
            <div class="col-md-6 col-lg-3">
                <div class="product-card">
                    <a href="{{ route('products.show', $related->slug) }}" class="product-card-image">
                        @if($related->image)
                        <x-responsive-image :path="$related->image" :alt="$related->name"
                                            size="md" class="product-card-img" loading="lazy" />
                        @else
                        <i class="fa-solid fa-image"></i>
                        @endif
                    </a>
                    <div class="product-card-content">
                        <div class="product-card-category">{{ $related->category?->name }}</div>
                        <h4 class="product-card-title">
                            <a href="{{ route('products.show', $related->slug) }}">{{ $related->name }}</a>
                        </h4>
                        @if($related->reviews_count > 0)
                        <div class="product-rating">
                            <div class="stars">
                                @php $ravg = round($related->reviews_avg * 2) / 2; @endphp
                                @for($i = 1; $i <= 5; $i++)
                                    @if($i <= floor($ravg))
                                        <i class="fa-solid fa-star"></i>
                                    @elseif($i - 0.5 <= $ravg)
                                        <i class="fa-solid fa-star-half-stroke"></i>
                                    @else
                                        <i class="fa-regular fa-star"></i>
                                    @endif
                                @endfor
                            </div>
                            <span class="rating-text">{{ number_format($related->reviews_avg, 1) }} ({{ $related->reviews_count }})</span>
                        </div>
                        @endif
                        <div class="product-card-footer">
                            <div class="product-card-price">
                                {{ number_format($related->currentPrice(), 2) }} ₺
                                <span>/ {{ $related->unit }}</span>
                            </div>
                            @if($related->inStock())
                            <button class="btn-card-add" type="button" data-product-id="{{ $related->id }}">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Cart Indicator --}}
<div class="cart-indicator" id="cartIndicator">
    <i class="fa-solid fa-check-circle"></i>
    <span>Ürün sepete eklendi!</span>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/vendor/glightbox/css/glightbox.min.css') }}">
<style>
    /* Breadcrumb */
    .breadcrumb-section {
        padding: 190px 0 30px;
        background: linear-gradient(180deg, var(--green-mist) 0%, var(--cream) 100%);
    }

    /* Product Section */
    .product-detail-section {
        padding: 50px 0 100px;
    }

    /* Product Gallery */
    .product-gallery {
        position: relative;
    }

    .main-image-container {
        background: white;
        border-radius: 30px;
        padding: 30px;
        box-shadow: var(--shadow-soft);
        position: relative;
        overflow: visible;
        transition: all 0.4s ease;
    }

    .main-image-container:hover {
        box-shadow: var(--shadow-hover);
    }

    .product-main-image {
        width: 100%;
        height: 400px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--green-mist);
        border-radius: 20px;
        position: relative;
        overflow: hidden;
    }

    .product-main-image::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transition: all 0.6s ease;
        z-index: 5;
    }

    .main-image-container:hover .product-main-image::before {
        left: 100%;
    }

    .product-detail-img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        position: relative;
        z-index: 1;
    }

    .product-main-image .product-icon {
        font-size: 12rem;
        color: var(--green-primary);
        position: relative;
        z-index: 1;
    }

    .product-main-image .zoom-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.25);
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 6;
        cursor: pointer;
        border-radius: 20px;
    }

    .product-main-image .zoom-overlay i {
        font-size: 2.5rem;
        color: #fff;
    }

    .product-main-image:hover .zoom-overlay {
        opacity: 1;
    }

    .product-badges {
        position: absolute;
        top: 20px;
        left: 20px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        z-index: 10;
    }

    .product-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .badge-bestseller {
        background: linear-gradient(135deg, var(--gold), #e6c358);
        color: white;
    }

    .badge-new {
        background: linear-gradient(135deg, #e74c3c, #ff6b6b);
        color: white;
    }

    .badge-organic {
        background: linear-gradient(135deg, var(--green-primary), var(--green-light));
        color: white;
    }

    .badge-fresh {
        background: white;
        color: var(--green-primary);
        border: 2px solid var(--green-pale);
    }

    .thumbnail-gallery-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 25px;
        padding-top: 5px;
    }

    .thumbnail-gallery {
        display: flex;
        gap: 12px;
        overflow-x: auto;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        flex: 1;
        min-width: 0;
        padding: 8px 4px;
    }

    .thumbnail-gallery::-webkit-scrollbar {
        display: none;
    }

    .thumb-arrow {
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: none;
        background: var(--green-primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.75rem;
        box-shadow: var(--shadow-soft);
        opacity: 0;
        visibility: hidden;
    }

    .thumb-arrow.visible {
        opacity: 1;
        visibility: visible;
    }

    .thumb-arrow:hover {
        background: var(--green-dark);
        transform: scale(1.1);
    }

    .thumbnail {
        width: 80px;
        height: 80px;
        min-width: 80px;
        border-radius: 15px;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: var(--shadow-soft);
        transition: all 0.3s ease;
        border: 3px solid transparent;
        overflow: hidden;
    }

    .thumbnail:hover, .thumbnail.active {
        border-color: var(--green-primary);
        transform: translateY(-5px);
    }

    .thumbnail i {
        font-size: 2rem;
        color: var(--green-primary);
    }

    .thumbnail-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Trust Card */
    .product-trust-card {
        margin-top: 20px;
        padding: 20px;
        background: white;
        border-radius: 20px;
        box-shadow: var(--shadow-soft);
        border: 1px solid var(--green-pale);
    }
    .product-trust-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .product-trust-item {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .product-trust-item strong {
        display: block;
        font-size: 0.9rem;
        color: var(--green-dark);
        line-height: 1.3;
    }
    .product-trust-item small {
        display: block;
        font-size: 0.78rem;
        color: var(--brown-light);
        line-height: 1.3;
    }
    .product-trust-icon {
        width: 40px;
        height: 40px;
        min-width: 40px;
        border-radius: 12px;
        background: var(--green-mist);
        color: var(--green-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }
    .product-trust-icon--gold {
        background: #fff8e1;
        color: var(--gold, #d4a84b);
    }

    /* Product Info */
    .product-info {
        padding-left: 30px;
    }

    .product-category {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--green-pale);
        padding: 8px 18px;
        border-radius: 25px;
        font-size: 0.85rem;
        color: var(--green-dark);
        font-weight: 600;
        margin-bottom: 15px;
    }

    .product-title {
        font-size: 3rem;
        color: var(--green-dark);
        margin-bottom: 15px;
        line-height: 1.2;
    }

    .product-description {
        color: var(--brown-light);
        font-size: 1.1rem;
        line-height: 1.8;
        margin-bottom: 25px;
    }

    .product-price-box {
        background: white;
        padding: 25px 30px;
        border-radius: 20px;
        box-shadow: var(--shadow-soft);
        margin-bottom: 25px;
    }

    .price-row {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }

    .current-price {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--green-primary);
        font-family: 'Playfair Display', serif;
    }

    .old-price {
        font-size: 1.3rem;
        color: var(--brown-light);
        text-decoration: line-through;
    }

    .discount-badge {
        background: #e74c3c;
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .price-unit {
        color: var(--brown-light);
        font-size: 1rem;
    }

    .price-note {
        color: var(--brown-light);
        font-size: 0.9rem;
        margin-bottom: 0;
    }

    .price-note i {
        color: var(--green-light);
        margin-right: 5px;
    }

    /* Stock Info */
    .stock-info {
        margin-bottom: 25px;
    }

    .stock-badge {
        padding: 8px 18px;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .stock-in {
        background: var(--green-mist);
        color: var(--green-primary);
    }

    .stock-out {
        background: #fde8e8;
        color: #e74c3c;
    }

    /* Quantity Selector */
    .quantity-section {
        margin-bottom: 25px;
    }

    .quantity-label {
        font-weight: 600;
        color: var(--green-dark);
        margin-bottom: 12px;
        display: block;
    }

    .quantity-selector {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .qty-btn {
        width: 50px;
        height: 50px;
        border-radius: 15px;
        border: 2px solid var(--green-pale);
        background: white;
        color: var(--green-primary);
        font-size: 1.3rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .qty-btn:hover {
        background: var(--green-primary);
        color: white;
        border-color: var(--green-primary);
    }

    .qty-input {
        width: 80px;
        height: 50px;
        border-radius: 15px;
        border: 2px solid var(--green-pale);
        text-align: center;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--green-dark);
        -moz-appearance: textfield;
        -webkit-appearance: none;
        appearance: none;
        padding: 0;
    }

    .qty-input::-webkit-outer-spin-button,
    .qty-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .qty-input:focus {
        outline: none;
        border-color: var(--green-primary);
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 15px;
        margin-bottom: 30px;
    }

    .btn-add-cart {
        flex: 1;
        padding: 18px 30px;
        border-radius: 15px;
        border: none;
        background: linear-gradient(135deg, var(--green-primary), var(--green-light));
        color: white;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.4s ease;
        box-shadow: 0 8px 30px rgba(74, 124, 67, 0.3);
    }

    .btn-add-cart:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 40px rgba(74, 124, 67, 0.4);
    }

    .btn-add-cart.added {
        background: var(--gold);
    }

    .btn-whatsapp-order {
        padding: 18px 30px;
        border-radius: 15px;
        border: none;
        background: #25D366;
        color: white;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.4s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 8px 30px rgba(37, 211, 102, 0.3);
    }

    .btn-whatsapp-order:hover {
        background: #20bd5a;
        transform: translateY(-3px);
        box-shadow: 0 12px 40px rgba(37, 211, 102, 0.4);
        color: white;
    }

    .btn-whatsapp-order i {
        font-size: 1.3rem;
    }

    /* Product Features */
    .product-features {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .feature-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(45, 90, 39, 0.05);
        transition: all 0.3s ease;
    }

    .feature-item:hover {
        transform: translateX(5px);
        box-shadow: var(--shadow-soft);
    }

    .feature-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        background: var(--green-mist);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--green-primary);
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .feature-text {
        font-weight: 500;
        color: var(--green-dark);
        font-size: 0.95rem;
    }

    /* Tabs Section */
    .tabs-section {
        padding: 80px 0;
        background: white;
    }

    .custom-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 40px;
        flex-wrap: wrap;
    }

    .tab-btn {
        padding: 15px 30px;
        border-radius: 30px;
        border: 2px solid var(--green-pale);
        background: white;
        color: var(--green-dark);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .tab-btn:hover {
        border-color: var(--green-primary);
        color: var(--green-primary);
    }

    .tab-btn.active {
        background: var(--green-primary);
        border-color: var(--green-primary);
        color: white;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    /* Description Content */
    .description-content h3 {
        font-size: 1.8rem;
        color: var(--green-dark);
        margin-bottom: 20px;
    }

    .description-content p {
        color: var(--brown-light);
        line-height: 1.9;
        margin-bottom: 20px;
    }

    .description-list {
        list-style: none;
        padding: 0;
    }

    .description-list li {
        padding: 12px 0;
        border-bottom: 1px solid var(--green-mist);
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--brown);
    }

    .description-list li i {
        color: var(--green-primary);
        font-size: 1.1rem;
    }

    /* Nutrition Tab */
    .nutrition-table {
        background: var(--green-mist);
        border-radius: 20px;
        overflow: hidden;
    }

    .nutrition-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .nutrition-table th {
        background: var(--green-primary);
        color: white;
        padding: 18px 25px;
        text-align: left;
        font-weight: 600;
    }

    .nutrition-table td {
        padding: 15px 25px;
        border-bottom: 1px solid var(--green-pale);
        color: var(--brown);
    }

    .nutrition-table tr:last-child td {
        border-bottom: none;
    }

    .nutrition-table tr:nth-child(even) {
        background: white;
    }

    /* Reviews Tab */
    .reviews-header {
        display: flex;
        flex-direction: column;
        gap: 20px;
        margin-bottom: 30px;
        align-items: stretch;
    }

    .reviews-summary-card {
        display: flex;
        align-items: center;
        gap: 30px;
        background: var(--green-mist);
        border-radius: 20px;
        padding: 30px;
    }

    .reviews-summary-left {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-shrink: 0;
    }

    .big-rating {
        font-size: 3.5rem;
        font-weight: 700;
        color: var(--green-dark);
        font-family: 'Playfair Display', serif;
        line-height: 1;
    }

    .big-rating-label {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .stars {
        color: var(--gold);
        font-size: 1.1rem;
    }

    .reviews-count {
        font-size: 0.85rem;
        color: var(--brown-light);
        font-weight: 500;
    }

    .reviews-summary-divider {
        width: 1px;
        height: 80px;
        background: var(--green-pale);
        flex-shrink: 0;
    }

    .reviews-summary-right {
        flex: 1;
    }

    .rating-breakdown {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .rating-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.85rem;
    }

    .rating-bar__star {
        width: 40px;
        color: var(--brown-light);
        display: flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
    }

    .rating-bar__star i {
        font-size: 0.7rem;
        color: var(--gold);
    }

    .rating-bar__pct {
        width: 32px;
        text-align: right;
        color: var(--brown-light);
        font-weight: 500;
        flex-shrink: 0;
    }

    .bar {
        flex: 1;
        height: 10px;
        background: white;
        border-radius: 10px;
        overflow: hidden;
    }

    .bar-fill {
        height: 100%;
        background: var(--gold);
        border-radius: 10px;
        transition: width 0.6s ease;
    }

    .btn-write-review {
        padding: 14px 28px;
        border-radius: 30px;
        border: 2px solid var(--green-primary);
        background: var(--green-primary);
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        align-self: flex-end;
    }

    .btn-write-review:hover {
        background: var(--green-dark);
        border-color: var(--green-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(45, 80, 22, 0.3);
    }

    .review-card {
        background: var(--green-mist);
        padding: 30px;
        border-radius: 20px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }

    .review-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-soft);
    }

    .review-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .reviewer-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .reviewer-avatar {
        width: 55px;
        height: 55px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--green-primary), var(--green-light));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .reviewer-name {
        font-weight: 600;
        color: var(--green-dark);
        margin-bottom: 3px;
    }

    .review-date {
        font-size: 0.85rem;
        color: var(--brown-light);
    }

    .review-rating {
        color: var(--gold);
    }

    .review-text {
        color: var(--brown);
        line-height: 1.8;
    }

    /* Review Form */
    .review-form-wrapper {
        display: none;
        margin-bottom: 30px;
    }

    .review-form-wrapper.active {
        display: block;
    }

    .review-form-card {
        background: var(--green-mist);
        padding: 30px;
        border-radius: 20px;
        border: 2px solid var(--green-pale);
    }

    .review-form-title {
        font-size: 1.3rem;
        color: var(--green-dark);
        margin-bottom: 25px;
    }

    .form-label-custom {
        font-weight: 600;
        color: var(--green-dark);
        margin-bottom: 8px;
        display: block;
        font-size: 0.95rem;
    }

    .form-input-custom {
        width: 100%;
        padding: 12px 18px;
        border-radius: 12px;
        border: 2px solid var(--green-pale);
        background: white;
        color: var(--brown);
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .form-input-custom:focus {
        outline: none;
        border-color: var(--green-primary);
        box-shadow: 0 0 0 4px rgba(74, 124, 67, 0.1);
    }

    .form-input-custom.is-invalid {
        border-color: #e74c3c;
    }

    textarea.form-input-custom {
        resize: vertical;
        min-height: 100px;
    }

    .invalid-feedback-custom {
        color: #e74c3c;
        font-size: 0.85rem;
        margin-top: 5px;
        display: none;
    }

    .invalid-feedback-custom.show {
        display: block;
    }

    .star-rating-input {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 0;
    }

    .star-rating-input i {
        font-size: 1.8rem;
        color: var(--green-pale);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .star-rating-input i.active,
    .star-rating-input i.hover {
        color: var(--gold);
    }

    .star-rating-text {
        font-size: 0.9rem;
        color: var(--brown-light);
        margin-left: 5px;
    }

    .review-form-actions {
        display: flex;
        gap: 15px;
        margin-top: 10px;
    }

    .btn-submit-review {
        padding: 14px 35px;
        border-radius: 30px;
        border: none;
        background: linear-gradient(135deg, var(--green-primary), var(--green-light));
        color: white;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-submit-review:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(74, 124, 67, 0.3);
    }

    .btn-submit-review:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    .btn-cancel-review {
        padding: 14px 30px;
        border-radius: 30px;
        border: 2px solid var(--green-pale);
        background: white;
        color: var(--brown-light);
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-cancel-review:hover {
        border-color: var(--brown-light);
    }

    .review-success-message {
        display: none;
        text-align: center;
        padding: 30px;
        background: var(--green-mist);
        border-radius: 20px;
        margin-bottom: 30px;
        border: 2px solid var(--green-primary);
    }

    .review-success-message.show {
        display: block;
    }

    .review-success-message i {
        font-size: 3rem;
        color: var(--green-primary);
        margin-bottom: 10px;
        display: block;
    }

    .review-success-message p {
        color: var(--green-dark);
        font-weight: 500;
        margin-bottom: 0;
    }

    .no-reviews-message {
        text-align: center;
        padding: 50px 20px;
        background: var(--green-mist);
        border-radius: 20px;
        border: 2px dashed var(--green-pale);
    }

    .no-reviews-icon {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        font-size: 1.8rem;
        color: var(--green-primary);
    }

    .no-reviews-title {
        color: var(--green-dark);
        font-weight: 700;
        margin-bottom: 8px;
    }

    .no-reviews-text {
        color: var(--brown-light);
        margin-bottom: 20px;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
        font-size: 0.95rem;
        line-height: 1.6;
    }

    .btn-first-review {
        padding: 12px 24px;
        border-radius: 30px;
        border: none;
        background: var(--green-primary);
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    .btn-first-review:hover {
        background: var(--green-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(45, 80, 22, 0.3);
    }

    /* FAQ Accordion */
    .faq-accordion { display: flex; flex-direction: column; gap: 10px; }
    .faq-item { background: white; border-radius: 16px; border: 1px solid var(--green-pale); overflow: hidden; transition: all 0.3s ease; }
    .faq-item.active { border-color: var(--green-primary); box-shadow: 0 2px 12px rgba(74,124,67,0.08); }
    .faq-question { width: 100%; display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; background: none; border: none; cursor: pointer; font-weight: 600; font-size: 1rem; color: var(--green-dark); text-align: left; gap: 12px; }
    .faq-question:hover { color: var(--green-primary); }
    .faq-arrow { font-size: 0.8rem; color: var(--green-primary); transition: transform 0.3s ease; }
    .faq-item.active .faq-arrow { transform: rotate(180deg); }
    .faq-answer { padding: 0 24px 18px; }
    .faq-answer p { color: var(--brown-light); line-height: 1.8; margin: 0; font-size: 0.95rem; }

    /* Related Products */
    .related-section {
        padding: 80px 0;
        background: var(--cream);
    }

    .product-card {
        background: white;
        border-radius: 25px;
        overflow: hidden;
        box-shadow: var(--shadow-soft);
        transition: all 0.5s ease;
        height: 100%;
    }

    .product-card:hover {
        transform: translateY(-15px);
        box-shadow: var(--shadow-hover);
    }

    .product-card-image {
        height: 200px;
        background: var(--green-mist);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
        text-decoration: none;
    }

    .product-card-image i {
        font-size: 5rem;
        color: var(--green-primary);
        transition: all 0.4s ease;
    }

    .product-card:hover .product-card-image i {
        transform: scale(1.15) rotate(5deg);
    }

    .product-card-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: all 0.4s ease;
    }

    .product-card:hover .product-card-img {
        transform: scale(1.05);
    }

    .product-card-content {
        padding: 25px;
    }

    .product-card-category {
        color: var(--green-light);
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 8px;
    }

    .product-card-title {
        font-size: 1.3rem;
        color: var(--green-dark);
        margin-bottom: 15px;
    }

    .product-card-title a {
        color: inherit;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .product-card-title a:hover {
        color: var(--green-primary);
    }

    .product-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .product-card-price {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--green-primary);
    }

    .product-card-price span {
        font-size: 0.9rem;
        color: var(--brown-light);
        font-weight: 400;
    }

    .btn-card-add {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        border: none;
        background: linear-gradient(135deg, var(--green-primary), var(--green-light));
        color: white;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-card-add:hover {
        transform: scale(1.1) rotate(90deg);
    }

    /* Cart Indicator */
    .cart-indicator {
        position: fixed;
        bottom: 100px;
        right: 30px;
        background: var(--gold);
        color: white;
        padding: 15px 25px;
        border-radius: 30px;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: var(--shadow-hover);
        transform: translateX(200%);
        transition: all 0.5s ease;
        z-index: 999;
    }

    .cart-indicator.show {
        transform: translateX(0);
    }

    /* Responsive */
    @media (max-width: 991px) {
        .product-info {
            padding-left: 0;
            margin-top: 40px;
        }

        .product-title {
            font-size: 2.3rem;
        }

        .product-features {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767px) {
        .product-trust-grid { grid-template-columns: 1fr; gap: 12px; }
        .product-trust-card { padding: 16px; margin-top: 16px; }

        .breadcrumb-section {
            padding: 160px 0 20px;
        }

        .product-title {
            font-size: 2rem;
        }

        .current-price {
            font-size: 2rem;
        }

        .action-buttons {
            flex-direction: column;
        }

        .product-main-image {
            height: 300px;
        }

        .thumb-arrow {
            display: none;
        }

        .thumbnail-gallery-wrapper {
            gap: 0;
        }

        .reviews-summary-card {
            flex-direction: column;
            gap: 20px;
            padding: 24px;
        }

        .reviews-summary-divider {
            width: 100%;
            height: 1px;
        }

        .big-rating {
            font-size: 3rem;
        }

        .btn-write-review {
            align-self: stretch;
            text-align: center;
        }

        .review-header {
            flex-direction: column;
            gap: 10px;
        }

        .review-form-card {
            padding: 20px;
        }

        .review-form-actions {
            flex-direction: column;
        }

        .star-rating-input i {
            font-size: 1.5rem;
        }
    }

    /* Shipping Cards */
    .shipping-card {
        background: white;
        border: 2px solid var(--green-pale);
        border-radius: 20px;
        padding: 30px 24px;
        text-align: center;
        height: 100%;
        transition: all 0.3s ease;
    }

    .shipping-card:hover {
        border-color: var(--green-primary);
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(45, 80, 22, 0.12);
    }

    .shipping-card__icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: var(--green-mist);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        font-size: 1.5rem;
        color: var(--green-primary);
    }

    .shipping-card__icon--fresh {
        background: #e8f4fd;
        color: #2d8bc9;
    }

    .shipping-card__icon--return {
        background: #fef3e8;
        color: #c97a2d;
    }

    .shipping-card__title {
        color: var(--green-dark);
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 10px;
    }

    .shipping-card__text {
        color: var(--brown-light);
        font-size: 0.9rem;
        line-height: 1.6;
        margin-bottom: 0;
    }

    @media (max-width: 767.98px) {
        .shipping-card {
            padding: 24px 20px;
        }

        .shipping-card__icon {
            width: 52px;
            height: 52px;
            font-size: 1.25rem;
        }
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('assets/vendor/glightbox/js/glightbox.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Rating bar fills
    document.querySelectorAll('.bar-fill').forEach(function(bar) {
        var width = bar.getAttribute('data-width');
        if (width) bar.style.width = width + '%';
    });

    // Quantity selector
    var qtyInput = document.getElementById('qtyInput');
    var minusBtn = document.getElementById('qtyMinus');
    var plusBtn = document.getElementById('qtyPlus');

    if (minusBtn && qtyInput) {
        minusBtn.addEventListener('click', function() {
            var val = parseInt(qtyInput.value, 10);
            if (val > 1) qtyInput.value = val - 1;
        });
    }

    if (plusBtn && qtyInput) {
        plusBtn.addEventListener('click', function() {
            var val = parseInt(qtyInput.value, 10);
            var max = parseInt(qtyInput.max, 10);
            if (val < max) qtyInput.value = val + 1;
        });
    }

    // Add to cart
    var addBtn = document.getElementById('addToCartBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            var productId = parseInt(this.dataset.productId, 10);
            var quantity = parseInt(qtyInput.value, 10);
            var btn = this;
            var indicator = document.getElementById('cartIndicator');
            var originalHtml = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Ekleniyor...</span>';

            fetch('{{ route("cart.add") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ product_id: productId, quantity: quantity }),
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    btn.innerHTML = '<i class="fa-solid fa-check"></i><span>Eklendi!</span>';
                    btn.classList.add('added');
                    indicator.classList.add('show');

                    var badge = document.getElementById('cartBadge');
                    if (badge) {
                        badge.textContent = data.cart_count;
                        badge.classList.remove('d-none');
                    }
                    var badgeMobile = document.getElementById('cartBadgeMobile');
                    if (badgeMobile) {
                        badgeMobile.textContent = data.cart_count;
                        badgeMobile.classList.remove('d-none');
                    }

                    setTimeout(function() {
                        btn.innerHTML = originalHtml;
                        btn.classList.remove('added');
                        btn.disabled = false;
                    }, 2000);

                    setTimeout(function() {
                        indicator.classList.remove('show');
                    }, 3000);
                } else {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                    window.showResultModal('error', data.message || 'Bir hata oluştu.');
                }
            })
            .catch(function() {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                window.showResultModal('error', 'Bir hata oluştu. Lütfen tekrar deneyin.');
            });
        });
    }

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tabId = this.getAttribute('data-tab');
            document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
            document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
            this.classList.add('active');
            var target = document.getElementById(tabId);
            if (target) target.classList.add('active');
        });
    });

    // FAQ accordion toggle
    document.querySelectorAll('.faq-question').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var item = btn.closest('.faq-item');
            var answer = item.querySelector('.faq-answer');
            var isActive = item.classList.contains('active');
            item.classList.toggle('active');
            answer.classList.toggle('d-none', isActive);
            btn.setAttribute('aria-expanded', String(!isActive));
        });
    });

    // Thumbnail click - change main image
    @if($product->images->isNotEmpty())
    var thumbGallery = document.getElementById('thumbnailGallery');
    var allImages = [
        {
            src: '{{ upload_url($product->image, 'lg') }}',
            srcset: '{{ upload_srcset($product->image) }}'
        }
        @foreach($product->images as $image)
        , {
            src: '{{ upload_url($image->image, 'lg') }}',
            srcset: '{{ upload_srcset($image->image) }}'
        }
        @endforeach
    ];

    document.querySelectorAll('.thumbnail').forEach(function(thumb) {
        thumb.addEventListener('click', function() {
            var index = parseInt(this.getAttribute('data-index'), 10);
            var mainImg = document.querySelector('#mainImage .product-detail-img');
            if (mainImg && allImages[index]) {
                mainImg.src = allImages[index].src;
                if (allImages[index].srcset) {
                    mainImg.srcset = allImages[index].srcset;
                } else {
                    mainImg.removeAttribute('srcset');
                }
            }
            document.querySelectorAll('.thumbnail').forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            scrollThumbIntoView(this);
        });
    });

    // Scroll active thumbnail into visible area
    function scrollThumbIntoView(el) {
        if (!thumbGallery || !el) return;
        var containerRect = thumbGallery.getBoundingClientRect();
        var elRect = el.getBoundingClientRect();
        var offsetLeft = elRect.left - containerRect.left + thumbGallery.scrollLeft;
        var scrollTo = offsetLeft - (containerRect.width / 2) + (elRect.width / 2);
        thumbGallery.scrollTo({ left: scrollTo, behavior: 'smooth' });
    }

    // Arrow buttons
    var prevArrow = document.querySelector('.thumb-arrow--prev');
    var nextArrow = document.querySelector('.thumb-arrow--next');
    var scrollAmount = 200;

    function updateArrowVisibility() {
        if (!thumbGallery || !prevArrow || !nextArrow) return;
        var maxScroll = thumbGallery.scrollWidth - thumbGallery.clientWidth;
        if (maxScroll <= 0) {
            prevArrow.classList.remove('visible');
            nextArrow.classList.remove('visible');
            return;
        }
        prevArrow.classList.toggle('visible', thumbGallery.scrollLeft > 5);
        nextArrow.classList.toggle('visible', thumbGallery.scrollLeft < maxScroll - 5);
    }

    if (prevArrow) {
        prevArrow.addEventListener('click', function() {
            thumbGallery.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        });
    }
    if (nextArrow) {
        nextArrow.addEventListener('click', function() {
            thumbGallery.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        });
    }

    if (thumbGallery) {
        thumbGallery.addEventListener('scroll', updateArrowVisibility);
        updateArrowVisibility();
    }

    // Drag-to-scroll for desktop
    (function() {
        if (!thumbGallery) return;
        var isDown = false;
        var startX = 0;
        var scrollLeftStart = 0;

        thumbGallery.addEventListener('mousedown', function(e) {
            if (e.target.closest('.thumbnail')) {
                // Allow click on thumbnails
            }
            isDown = true;
            startX = e.pageX - thumbGallery.offsetLeft;
            scrollLeftStart = thumbGallery.scrollLeft;
            thumbGallery.style.cursor = 'grabbing';
        });

        thumbGallery.addEventListener('mouseleave', function() {
            isDown = false;
            thumbGallery.style.cursor = 'grab';
        });

        thumbGallery.addEventListener('mouseup', function() {
            isDown = false;
            thumbGallery.style.cursor = 'grab';
        });

        thumbGallery.addEventListener('mousemove', function(e) {
            if (!isDown) return;
            e.preventDefault();
            var x = e.pageX - thumbGallery.offsetLeft;
            var walk = (x - startX) * 1.5;
            thumbGallery.scrollLeft = scrollLeftStart - walk;
        });

        thumbGallery.style.cursor = 'grab';
    })();
    @endif

    // ── GLightbox Gallery ──
    @if($product->image)
    var productLightbox = GLightbox({
        selector: '#productLightboxGallery .glightbox',
        touchNavigation: true,
        loop: true,
        closeOnOutsideClick: true,
    });

    var zoomOverlay = document.getElementById('zoomOverlay');
    if (zoomOverlay) {
        zoomOverlay.addEventListener('click', function(e) {
            e.stopPropagation();
            var activeThumb = document.querySelector('.thumbnail.active');
            var index = activeThumb ? parseInt(activeThumb.getAttribute('data-index'), 10) : 0;
            productLightbox.openAt(index);
        });
    }
    @endif

    // ── Review Form ──
    var reviewFormWrapper = document.getElementById('reviewFormWrapper');
    var reviewForm = document.getElementById('reviewForm');
    var btnWriteReview = document.getElementById('btnWriteReview');
    var btnCancelReview = document.getElementById('btnCancelReview');
    var btnSubmitReview = document.getElementById('btnSubmitReview');
    var reviewSuccessMessage = document.getElementById('reviewSuccessMessage');
    var starRatingInput = document.getElementById('starRatingInput');
    var ratingInput = document.getElementById('ratingInput');
    var starRatingText = document.getElementById('starRatingText');
    var ratingLabels = ['', 'Çok Kötü', 'Kötü', 'Orta', 'İyi', 'Mükemmel'];

    if (btnWriteReview) {
        btnWriteReview.addEventListener('click', function() {
            reviewFormWrapper.classList.add('active');
            reviewFormWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    if (btnCancelReview) {
        btnCancelReview.addEventListener('click', function() {
            reviewFormWrapper.classList.remove('active');
            reviewForm.reset();
            ratingInput.value = '0';
            clearStars();
            clearErrors();
        });
    }

    // Star rating hover & click
    if (starRatingInput) {
        var stars = starRatingInput.querySelectorAll('i[data-star]');
        stars.forEach(function(star) {
            star.addEventListener('mouseenter', function() {
                var val = parseInt(this.getAttribute('data-star'), 10);
                highlightStars(val, 'hover');
                starRatingText.textContent = ratingLabels[val];
            });

            star.addEventListener('mouseleave', function() {
                clearStarsClass('hover');
                var current = parseInt(ratingInput.value, 10);
                if (current > 0) {
                    starRatingText.textContent = ratingLabels[current];
                } else {
                    starRatingText.textContent = '';
                }
            });

            star.addEventListener('click', function() {
                var val = parseInt(this.getAttribute('data-star'), 10);
                ratingInput.value = val;
                highlightStars(val, 'active');
                starRatingText.textContent = ratingLabels[val];
            });
        });
    }

    function highlightStars(count, cls) {
        var stars = starRatingInput.querySelectorAll('i[data-star]');
        stars.forEach(function(s) {
            var sVal = parseInt(s.getAttribute('data-star'), 10);
            if (sVal <= count) {
                s.classList.add(cls);
                s.classList.remove('fa-regular');
                s.classList.add('fa-solid');
            } else if (cls === 'hover') {
                s.classList.remove(cls);
                if (!s.classList.contains('active')) {
                    s.classList.remove('fa-solid');
                    s.classList.add('fa-regular');
                }
            }
        });
    }

    function clearStarsClass(cls) {
        var stars = starRatingInput.querySelectorAll('i[data-star]');
        var current = parseInt(ratingInput.value, 10);
        stars.forEach(function(s) {
            var sVal = parseInt(s.getAttribute('data-star'), 10);
            s.classList.remove(cls);
            if (sVal <= current) {
                s.classList.add('fa-solid');
                s.classList.remove('fa-regular');
            } else {
                s.classList.remove('fa-solid');
                s.classList.add('fa-regular');
            }
        });
    }

    function clearStars() {
        var stars = starRatingInput.querySelectorAll('i[data-star]');
        stars.forEach(function(s) {
            s.classList.remove('active', 'hover', 'fa-solid');
            s.classList.add('fa-regular');
        });
        starRatingText.textContent = '';
    }

    function clearErrors() {
        document.querySelectorAll('.invalid-feedback-custom').forEach(function(el) {
            el.classList.remove('show');
            el.textContent = '';
        });
        document.querySelectorAll('.form-input-custom.is-invalid').forEach(function(el) {
            el.classList.remove('is-invalid');
        });
    }

    function showFieldError(field, message) {
        var feedback = document.querySelector('.invalid-feedback-custom[data-field="' + field + '"]');
        if (feedback) {
            feedback.textContent = message;
            feedback.classList.add('show');
        }
        var input = reviewForm.querySelector('[name="' + field + '"]');
        if (input && input.classList) {
            input.classList.add('is-invalid');
        }
    }

    // Submit review
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            clearErrors();

            var formData = new FormData(reviewForm);
            var data = {};
            formData.forEach(function(value, key) { data[key] = value; });
            data.rating = parseInt(data.rating, 10);

            btnSubmitReview.disabled = true;
            btnSubmitReview.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Gönderiliyor...';

            fetch('{{ route("reviews.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(data),
            })
            .then(function(r) {
                if (r.status === 422) {
                    return r.json().then(function(d) { throw d; });
                }
                return r.json();
            })
            .then(function(data) {
                if (data.success) {
                    reviewFormWrapper.classList.remove('active');
                    reviewSuccessMessage.classList.add('show');
                    reviewForm.reset();
                    ratingInput.value = '0';
                    clearStars();
                    btnWriteReview.disabled = true;
                    btnWriteReview.innerHTML = '<i class="fa-solid fa-check me-2"></i>Gönderildi';
                    if (typeof grecaptcha !== 'undefined') grecaptcha.reset();
                }
            })
            .catch(function(err) {
                if (err && err.errors) {
                    Object.keys(err.errors).forEach(function(field) {
                        showFieldError(field, err.errors[field][0]);
                    });
                } else {
                    window.showResultModal('error', 'Bir hata oluştu. Lütfen tekrar deneyin.');
                }
            })
            .finally(function() {
                btnSubmitReview.disabled = false;
                btnSubmitReview.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i>Gönder';
            });
        });
    }

    // Related product card add buttons
    document.querySelectorAll('.btn-card-add').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var productId = parseInt(this.dataset.productId, 10);
            var cardBtn = this;
            var indicator = document.getElementById('cartIndicator');

            fetch('{{ route("cart.add") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ product_id: productId, quantity: 1 }),
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    cardBtn.innerHTML = '<i class="fa-solid fa-check"></i>';
                    indicator.classList.add('show');

                    var badge = document.getElementById('cartBadge');
                    if (badge) {
                        badge.textContent = data.cart_count;
                        badge.classList.remove('d-none');
                    }
                    var badgeMobile = document.getElementById('cartBadgeMobile');
                    if (badgeMobile) {
                        badgeMobile.textContent = data.cart_count;
                        badgeMobile.classList.remove('d-none');
                    }

                    setTimeout(function() {
                        cardBtn.innerHTML = '<i class="fa-solid fa-plus"></i>';
                    }, 1500);

                    setTimeout(function() {
                        indicator.classList.remove('show');
                    }, 3000);
                }
            });
        });
    });
});
</script>
@endpush
