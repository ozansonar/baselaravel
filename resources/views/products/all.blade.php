@extends('layouts.app')

@section('title', 'Ürünlerimiz | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Doğal ve organik köy ürünleri. Taze süt, peynir, yumurta, bal ve tereyağı. ' . \App\Models\Setting::getValue('site_name', config('app.name')) . ' kapınıza.')
@section('canonical', route('products.all'))
@if(\App\Models\Setting::getValue('og_image'))
@section('og_image', url(upload_url(\App\Models\Setting::getValue('og_image'))))
@endif

@push('json-ld')
@php
    $allBreadcrumbJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Ürünlerimiz'],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($allBreadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')

{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <div class="page-header-content">
            <nav class="breadcrumb-custom animate-fade-up" aria-label="Breadcrumb">
                <a href="{{ route('home') }}"><i class="fas fa-home"></i> Ana Sayfa</a>
                <i class="fas fa-chevron-right"></i>
                <span>Ürünlerimiz</span>
            </nav>
            <h1 class="page-title animate-fade-up delay-1">Doğal Ürünlerimiz</h1>
            <p class="page-subtitle animate-fade-up delay-2">
                Çiftliğimizden sofralarınıza, tamamen doğal ve organik ürünlerimizi keşfedin
            </p>
        </div>
    </div>
</header>

{{-- Filter Section --}}
@if($categories->isNotEmpty())
<section class="filter-section" aria-labelledby="filter-heading">
    <div class="container">
        <h2 class="visually-hidden" id="filter-heading">Kategoriler</h2>
        <div class="filter-row">
            <div class="category-filters">
                <a href="{{ route('products.all') }}" class="filter-btn active">
                    <i class="fas fa-th-large"></i>
                    Tümü
                    <span class="count">{{ $products->total() }}</span>
                </a>
                @foreach($categories as $category)
                <a href="{{ route('products.index', $category->slug) }}" class="filter-btn">
                    <i class="fas fa-tag"></i>
                    {{ $category->name }}
                    <span class="count">{{ $category->products_count }}</span>
                </a>
                @endforeach
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="sort-dropdown">
                    <select id="sortSelect" aria-label="Sıralama">
                        <option value="popular">En Popüler</option>
                        <option value="price-low">Fiyat: Düşükten Yükseğe</option>
                        <option value="price-high">Fiyat: Yüksekten Düşüğe</option>
                        <option value="newest">En Yeniler</option>
                    </select>
                </div>
                <div class="view-options">
                    <button class="view-btn active" data-view="grid" title="Grid Görünümü" aria-label="Grid görünümü">
                        <i class="fas fa-th"></i>
                    </button>
                    <button class="view-btn" data-view="list" title="Liste Görünümü" aria-label="Liste görünümü">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- Products Section --}}
<section class="products-section" aria-labelledby="products-heading">
    <div class="container">
        <h2 class="visually-hidden" id="products-heading">Ürünler</h2>

        @if($products->isNotEmpty())
        <p class="products-count">
            <strong>{{ $products->count() }}</strong> ürün gösteriliyor (toplam <strong>{{ $products->total() }}</strong> ürün)
        </p>

        <div class="row g-4" id="productsGrid">
            @foreach($products as $product)
            <div class="col-md-6 col-lg-4">
                <article class="product-card">
                    <div class="product-image">
                        @if($product->is_featured || $product->is_new || $product->discounted_price)
                        <div class="product-badges">
                            @if($product->is_featured)
                            <span class="product-badge badge-bestseller"><i class="fas fa-fire"></i> Popüler</span>
                            @endif
                            @if($product->is_new)
                            <span class="product-badge badge-new"><i class="fas fa-sparkles"></i> Yeni</span>
                            @endif
                            @if($product->discounted_price)
                            <span class="product-badge badge-discount">%{{ round((1 - $product->price / $product->discounted_price) * 100) }} İndirim</span>
                            @endif
                        </div>
                        @endif
                        @if($product->cover_image)
                        <x-responsive-image :path="$product->cover_image" :alt="$product->name" size="md" class="product-img-cover" />
                        @else
                        <i class="fas fa-box-open product-icon"></i>
                        @endif
                        <a href="{{ route('products.show', $product->slug) }}" class="quick-view-btn">
                            <i class="fas fa-eye me-2"></i>Hızlı Bak
                        </a>
                    </div>
                    <div class="product-content">
                        <div class="product-category">{{ $product->category?->name }}</div>
                        <h3 class="product-title">
                            <a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a>
                        </h3>
                        @if($product->short_description)
                        <p class="product-description">{{ Str::limit($product->short_description, 100) }}</p>
                        @endif
                        @if($product->reviews_count > 0)
                        <div class="product-rating">
                            <div class="stars">
                                @php $avg = round($product->reviews_avg * 2) / 2; @endphp
                                @for($i = 1; $i <= 5; $i++)
                                    @if($i <= floor($avg))
                                        <i class="fas fa-star"></i>
                                    @elseif($i - 0.5 <= $avg)
                                        <i class="fas fa-star-half-alt"></i>
                                    @else
                                        <i class="far fa-star"></i>
                                    @endif
                                @endfor
                            </div>
                            <span class="rating-text">{{ number_format($product->reviews_avg, 1) }} ({{ $product->reviews_count }})</span>
                        </div>
                        @endif
                        <div class="product-footer">
                            <div class="product-price">
                                @if($product->discounted_price)
                                ₺{{ number_format($product->price, 0, ',', '.') }} <span>/ {{ $product->unit }}</span>
                                <span class="old-price">₺{{ number_format($product->discounted_price, 0, ',', '.') }}</span>
                                @else
                                ₺{{ number_format($product->price, 0, ',', '.') }} <span>/ {{ $product->unit }}</span>
                                @endif
                            </div>
                            <a href="{{ route('products.show', $product->slug) }}" class="btn-add-cart" aria-label="{{ $product->name }} detayını gör">
                                <i class="fas fa-plus"></i>
                            </a>
                        </div>
                    </div>
                </article>
            </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        {{ $products->links('vendor.pagination.custom') }}

        @else
        <div class="text-center py-5">
            <i class="fas fa-box-open fa-3x text-muted mb-3 d-block"></i>
            <h3 class="text-brown-light">Henüz ürün bulunmuyor.</h3>
            <a href="{{ route('home') }}" class="btn-custom mt-3">
                <i class="fas fa-arrow-left"></i> Anasayfaya Dön
            </a>
        </div>
        @endif
    </div>
</section>

@endsection

@push('styles')
<style>
    /* Page Header Pattern */
    .page-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%234a7c43' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }

    .page-header-content {
        position: relative;
        z-index: 1;
    }

    /* Shine Animation */
    @keyframes shine {
        from { transform: translateX(-100%) rotate(45deg); opacity: 1; }
        to { transform: translateX(100%) rotate(45deg); opacity: 1; }
    }

    /* Filter Section */
    .filter-section {
        padding: 30px 0;
        background: white;
        border-bottom: 1px solid var(--green-mist);
        position: sticky;
        top: 70px;
        z-index: 100;
    }

    .filter-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .category-filters {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .filter-btn {
        padding: 12px 24px;
        border-radius: 30px;
        border: 2px solid var(--green-pale);
        background: white;
        color: var(--green-dark);
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .filter-btn:hover {
        border-color: var(--green-primary);
        color: var(--green-primary);
    }

    .filter-btn.active {
        background: var(--green-primary);
        border-color: var(--green-primary);
        color: white;
    }

    .filter-btn .count {
        background: var(--green-mist);
        color: var(--green-primary);
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.8rem;
    }

    .filter-btn.active .count {
        background: rgba(255,255,255,0.3);
        color: white;
    }

    .sort-dropdown select {
        padding: 12px 40px 12px 20px;
        border-radius: 15px;
        border: 2px solid var(--green-pale);
        background: white;
        color: var(--green-dark);
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%234a7c43' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 15px center;
    }

    .sort-dropdown select:focus {
        outline: none;
        border-color: var(--green-primary);
    }

    .view-options {
        display: flex;
        gap: 10px;
    }

    .view-btn {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        border: 2px solid var(--green-pale);
        background: white;
        color: var(--brown-light);
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .view-btn:hover, .view-btn.active {
        border-color: var(--green-primary);
        color: var(--green-primary);
        background: var(--green-mist);
    }

    /* Products Section */
    .products-section {
        padding: 50px 0 100px;
    }

    .products-count {
        color: var(--brown-light);
        margin-bottom: 30px;
    }

    .products-count strong {
        color: var(--green-dark);
    }

    /* Product Card */
    .product-card {
        background: white;
        border-radius: 25px;
        overflow: hidden;
        box-shadow: var(--shadow-soft);
        transition: all 0.5s ease;
        height: 100%;
        position: relative;
    }

    .product-card:hover {
        transform: translateY(-15px);
        box-shadow: var(--shadow-hover);
    }

    .product-image {
        height: 220px;
        background: var(--green-mist);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }

    .product-image::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent);
        transform: rotate(45deg);
        transition: all 0.6s ease;
        opacity: 0;
    }

    .product-card:hover .product-image::before {
        animation: shine 0.6s ease;
    }

    .product-img-cover {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .product-icon {
        font-size: 5rem;
        color: var(--green-primary);
        transition: all 0.4s ease;
    }

    .product-card:hover .product-icon {
        transform: scale(1.15) rotate(5deg);
    }

    .product-badges {
        position: absolute;
        top: 15px;
        left: 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        z-index: 2;
    }

    .product-badge {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .badge-bestseller {
        background: linear-gradient(135deg, var(--gold), #e6c358);
        color: white;
    }

    .badge-new {
        background: linear-gradient(135deg, #e74c3c, #ff6b6b);
        color: white;
    }

    .badge-discount {
        background: linear-gradient(135deg, #9b59b6, #8e44ad);
        color: white;
    }

    .badge-organic {
        background: linear-gradient(135deg, var(--green-primary), var(--green-light));
        color: white;
    }

    .product-content {
        padding: 25px;
    }

    .product-category {
        color: var(--green-light);
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 8px;
    }

    .product-title {
        font-size: 1.4rem;
        color: var(--green-dark);
        margin-bottom: 10px;
        transition: color 0.3s ease;
    }

    .product-card:hover .product-title {
        color: var(--green-primary);
    }

    .product-title a {
        color: inherit;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .product-title a:hover {
        color: var(--green-primary);
    }

    .product-description {
        color: var(--brown-light);
        font-size: 0.9rem;
        line-height: 1.6;
        margin-bottom: 15px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .product-rating {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 15px;
    }

    .product-rating .stars {
        color: var(--gold);
        font-size: 0.9rem;
    }

    .product-rating .rating-text {
        color: var(--brown-light);
        font-size: 0.85rem;
    }

    .product-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 15px;
        border-top: 1px solid var(--green-mist);
    }

    .product-price {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--green-primary);
    }

    .product-price span {
        font-size: 0.9rem;
        color: var(--brown-light);
        font-weight: 400;
    }

    .product-price .old-price {
        text-decoration: line-through;
        color: var(--brown-light);
        font-size: 1rem;
        font-weight: 400;
        margin-left: 8px;
    }

    .btn-add-cart {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: none;
        background: linear-gradient(135deg, var(--green-primary), var(--green-light));
        color: white;
        font-size: 1.2rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(74, 124, 67, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }

    .btn-add-cart:hover {
        transform: scale(1.1) rotate(90deg);
        color: white;
    }

    .quick-view-btn {
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        padding: 10px 25px;
        background: white;
        border: none;
        border-radius: 25px;
        color: var(--green-dark);
        font-weight: 600;
        cursor: pointer;
        box-shadow: var(--shadow-soft);
        opacity: 0;
        transition: all 0.3s ease;
        text-decoration: none;
        z-index: 2;
        white-space: nowrap;
    }

    .product-card:hover .quick-view-btn {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }

    .quick-view-btn:hover {
        background: var(--green-primary);
        color: white;
    }

    /* List View */
    .list-view .row {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .list-view .col-md-6,
    .list-view .col-lg-4 {
        width: 100%;
        max-width: 100%;
        flex: none;
    }

    .list-view .product-card {
        display: flex;
        flex-direction: row;
        height: auto;
        border-radius: 20px;
    }

    .list-view .product-card:hover {
        transform: translateX(10px);
    }

    .list-view .product-image {
        width: 250px;
        min-width: 250px;
        height: 200px;
        border-radius: 20px 0 0 20px;
    }

    .list-view .product-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .list-view .product-description {
        -webkit-line-clamp: 3;
    }

    .list-view .product-footer {
        margin-top: auto;
    }

    .list-view .quick-view-btn {
        display: none;
    }

    /* Pagination */
    .pagination-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-top: 50px;
        flex-wrap: wrap;
    }

    .pagination-btn {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        border: 2px solid var(--green-pale);
        background: white;
        color: var(--green-dark);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }

    .pagination-btn:hover {
        border-color: var(--green-primary);
        color: var(--green-primary);
        background: var(--green-mist);
    }

    .pagination-btn.active {
        background: var(--green-primary);
        border-color: var(--green-primary);
        color: white;
    }

    .pagination-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    .pagination-btn.arrow {
        width: auto;
        padding: 0 20px;
        gap: 8px;
    }

    .pagination-info {
        color: var(--brown-light);
        margin: 0 15px;
        font-size: 0.95rem;
    }

    /* Responsive */
    @media (max-width: 991px) {
        .filter-section {
            position: relative;
            top: 0;
        }

        .filter-row {
            justify-content: center;
        }

        .list-view .product-card {
            flex-direction: column;
        }

        .list-view .product-image {
            width: 100%;
            min-width: auto;
            border-radius: 20px 20px 0 0;
        }
    }

    @media (max-width: 767px) {
        .category-filters {
            justify-content: center;
            width: 100%;
        }

        .filter-btn {
            padding: 10px 18px;
            font-size: 0.9rem;
        }

        .product-image {
            height: 180px;
        }

        .product-icon {
            font-size: 4rem;
        }

        .pagination-btn.arrow span {
            display: none;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // View toggle (Grid/List)
        document.querySelectorAll('.view-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.view-btn').forEach(function(b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');

                var productsSection = document.querySelector('.products-section');
                if (this.getAttribute('data-view') === 'list') {
                    productsSection.classList.add('list-view');
                } else {
                    productsSection.classList.remove('list-view');
                }
            });
        });

    });
</script>
@endpush
