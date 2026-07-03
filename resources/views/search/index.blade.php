@extends('layouts.app')

@section('title', ($query ? "\"$query\" araması" : 'Arama') . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', $query ? "\"$query\" için arama sonuçları." : 'Ürün ve blog yazılarımızda arama yapın.')
@section('robots', 'noindex, follow')

@section('content')

{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <nav class="breadcrumb-custom" aria-label="Breadcrumb">
            <a href="{{ route('home') }}"><i class="fa-solid fa-home"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Arama</span>
        </nav>
        <h1 class="page-title">Arama</h1>
        <p class="page-subtitle">Ürünler ve blog yazılarında arayın</p>
    </div>
</header>

{{-- Search Section --}}
<section class="search-page-section">
    <div class="container">

        {{-- Search Form --}}
        <div class="search-box">
            <form method="GET" action="{{ route('search') }}" class="search-box-form">
                <i class="fa-solid fa-magnifying-glass search-box-icon"></i>
                <input type="text" name="q" value="{{ $query }}"
                       placeholder="Ürün adı, kategori veya blog yazısı arayın..."
                       class="search-box-input" autofocus autocomplete="off">
                <button type="submit" class="btn-custom search-box-btn">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Ara
                </button>
            </form>
        </div>

        @if($query !== '')
            @php
                $productCount = $products?->total() ?? 0;
                $blogCount = $blogPosts?->total() ?? 0;
                $totalCount = $productCount + $blogCount;
            @endphp

            {{-- Results Summary --}}
            <div class="search-summary">
                <h2 class="search-summary-title">
                    <span class="search-keyword">"{{ $query }}"</span> için
                    @if($totalCount > 0)
                        {{ $totalCount }} sonuç bulundu
                    @else
                        sonuç bulunamadı
                    @endif
                </h2>
                @if($totalCount > 0)
                <div class="search-tabs">
                    <button class="search-tab active" data-target="products-section" onclick="switchTab(this)">
                        <i class="fa-solid fa-box-open"></i> Ürünler <span class="search-tab-count">{{ $productCount }}</span>
                    </button>
                    <button class="search-tab" data-target="blog-section" onclick="switchTab(this)">
                        <i class="fa-solid fa-newspaper"></i> Blog Yazıları <span class="search-tab-count">{{ $blogCount }}</span>
                    </button>
                </div>
                @endif
            </div>

            @if($totalCount > 0)
                {{-- ==================== ÜRÜN SONUÇLARI ==================== --}}
                <div class="search-results-section" id="products-section">
                    <div class="search-results-header">
                        <h3 class="search-results-title">
                            <i class="fa-solid fa-box-open icon-green"></i> Ürünler
                            <span class="search-results-count">({{ $productCount }})</span>
                        </h3>
                    </div>

                    @if($productCount > 0)
                    <div class="row g-4">
                        @foreach($products as $product)
                        <div class="col-6 col-md-4 col-lg-3">
                            <article class="product-card">
                                <a href="{{ route('products.show', $product->slug) }}" class="product-card-img-link">
                                    @if($product->cover_image)
                                    <img src="{{ \App\Services\UploadService::url($product->cover_image, 'sm') }}"
                                         alt="{{ $product->name }}"
                                         class="product-card-img img-fluid" width="300" height="200" loading="lazy" decoding="async">
                                    @else
                                    <div class="product-card-placeholder">
                                        <i class="fa-solid fa-image fa-3x text-muted"></i>
                                    </div>
                                    @endif

                                    @if($product->hasDiscount())
                                    <span class="product-card-badge">
                                        %{{ round((1 - $product->price / $product->discounted_price) * 100) }} İndirim
                                    </span>
                                    @endif
                                </a>

                                <div class="product-card-body">
                                    @if($product->category)
                                    <span class="product-card-category">{{ $product->category->name }}</span>
                                    @endif

                                    <h3 class="product-card-title">
                                        <a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a>
                                    </h3>

                                    @if($product->short_description)
                                    <p class="product-card-desc">{{ Str::limit($product->short_description, 60) }}</p>
                                    @endif

                                    @if($product->reviews_count > 0)
                                    <div class="product-rating">
                                        <div class="stars">
                                            @php $avg = round($product->reviews_avg * 2) / 2; @endphp
                                            @for($i = 1; $i <= 5; $i++)
                                                @if($i <= floor($avg))
                                                    <i class="fa-solid fa-star"></i>
                                                @elseif($i - 0.5 <= $avg)
                                                    <i class="fa-solid fa-star-half-stroke"></i>
                                                @else
                                                    <i class="fa-regular fa-star"></i>
                                                @endif
                                            @endfor
                                        </div>
                                        <span class="rating-text">{{ number_format($product->reviews_avg, 1) }} ({{ $product->reviews_count }})</span>
                                    </div>
                                    @endif

                                    <div class="product-card-footer">
                                        <div class="product-card-price">
                                            @if($product->hasDiscount())
                                            <span class="product-card-price-old">{{ number_format((float) $product->discounted_price, 2, ',', '.') }} ₺</span>
                                            <span class="product-card-price-current">{{ number_format((float) $product->price, 2, ',', '.') }} ₺</span>
                                            @else
                                            <span class="product-card-price-current">{{ number_format((float) $product->price, 2, ',', '.') }} ₺</span>
                                            @endif
                                        </div>

                                        @if($product->inStock())
                                        <button type="button" class="btn btn-sm btn-green search-add-cart"
                                                data-product-id="{{ $product->id }}">
                                            <i class="fa-solid fa-basket-shopping"></i>
                                        </button>
                                        @else
                                        <span class="badge bg-danger">Tükendi</span>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        </div>
                        @endforeach
                    </div>

                    @if($products->hasPages())
                        {{ $products->links('vendor.pagination.custom') }}
                    @endif
                    @else
                    <div class="search-empty-section">
                        <i class="fa-solid fa-box-open"></i>
                        <p>Bu arama için ürün bulunamadı.</p>
                    </div>
                    @endif
                </div>

                {{-- ==================== BLOG SONUÇLARI ==================== --}}
                <div class="search-results-section d-none" id="blog-section">
                    <div class="search-results-header">
                        <h3 class="search-results-title">
                            <i class="fa-solid fa-newspaper icon-green"></i> Blog Yazıları
                            <span class="search-results-count">({{ $blogCount }})</span>
                        </h3>
                    </div>

                    @if($blogCount > 0)
                    <div class="row g-4">
                        @foreach($blogPosts as $post)
                        <div class="col-md-6">
                            <article class="search-blog-card">
                                <div class="search-blog-card-image">
                                    @if($post->image)
                                    <img src="{{ upload_url($post->image, 'md') }}" alt="{{ $post->title }}" width="200" height="200" loading="lazy" decoding="async">
                                    @else
                                    <i class="{{ $post->category?->icon ?? 'fa-solid fa-pen-nib' }}"></i>
                                    @endif
                                    @if($post->category)
                                    <span class="blog-card-category">{{ $post->category->name }}</span>
                                    @endif
                                </div>
                                <div class="search-blog-card-body">
                                    <div class="blog-card-meta">
                                        <span><i class="fa-solid fa-calendar-alt"></i> {{ $post->published_at?->translatedFormat('d F Y') }}</span>
                                        <span><i class="fa-solid fa-user"></i> {{ $post->author?->name ?: 'Orhan Gazi SONAR' }}</span>
                                    </div>
                                    <h3 class="search-blog-card-title">
                                        <a href="{{ route('blog.show', [$post->category->slug, $post->slug]) }}">{{ $post->title }}</a>
                                    </h3>
                                    <p class="search-blog-card-excerpt">
                                        {{ Str::limit($post->excerpt ?? strip_tags($post->body), 150) }}
                                    </p>
                                    <a href="{{ route('blog.show', [$post->category->slug, $post->slug]) }}" class="read-more">
                                        Devamını Oku <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            </article>
                        </div>
                        @endforeach
                    </div>

                    @if($blogPosts->hasPages())
                        {{ $blogPosts->links('vendor.pagination.custom') }}
                    @endif
                    @else
                    <div class="search-empty-section">
                        <i class="fa-solid fa-newspaper"></i>
                        <p>Bu arama için blog yazısı bulunamadı.</p>
                    </div>
                    @endif
                </div>

            @else
                {{-- No Results --}}
                <div class="search-no-results">
                    <div class="search-no-results-icon">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <h3>"{{ $query }}" için sonuç bulunamadı</h3>
                    <p>Farklı bir arama terimi deneyebilir veya ürünlerimize göz atabilirsiniz.</p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="{{ route('products.all') }}" class="btn-custom">
                            <i class="fa-solid fa-leaf me-1"></i> Ürünlere Göz At
                        </a>
                        <a href="{{ route('blog.index') }}" class="btn-custom-outline">
                            <i class="fa-solid fa-newspaper me-1"></i> Blog Yazıları
                        </a>
                    </div>
                </div>
            @endif

        @else
            {{-- Initial State --}}
            <div class="search-initial">
                <div class="search-initial-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <h3>Aramak istediğiniz kelimeyi yazın</h3>
                <p>Ürünler ve blog yazılarımızda arama yapabilirsiniz.</p>
            </div>
        @endif

    </div>
</section>

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // Tab switching
    window.switchTab = function (btn) {
        document.querySelectorAll('.search-tab').forEach(function (t) { t.classList.remove('active'); });
        btn.classList.add('active');

        document.querySelectorAll('.search-results-section').forEach(function (s) { s.classList.add('d-none'); });

        var target = document.getElementById(btn.dataset.target);
        if (target) target.classList.remove('d-none');
    };

    // URL'de blog_page parametresi varsa blog tab'ını aç
    var params = new URLSearchParams(window.location.search);
    if (params.has('blog_page')) {
        var blogTab = document.querySelector('.search-tab[data-target="blog-section"]');
        if (blogTab) switchTab(blogTab);
    }

    // Add to cart
    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) return;

    document.querySelectorAll('.search-add-cart').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var productId = parseInt(this.dataset.productId, 10);
            var button = this;
            var originalHtml = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

            fetch('{{ route("cart.add") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken.content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ product_id: productId, quantity: 1 }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    button.innerHTML = '<i class="fa-solid fa-check"></i>';
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
                    setTimeout(function () {
                        button.innerHTML = originalHtml;
                        button.disabled = false;
                    }, 1500);
                } else {
                    button.innerHTML = originalHtml;
                    button.disabled = false;
                }
            })
            .catch(function () {
                button.innerHTML = originalHtml;
                button.disabled = false;
            });
        });
    });
})();
</script>
@endpush
