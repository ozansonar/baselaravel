@extends('layouts.app')

@php
    $siteName = \App\Models\Setting::getValue('site_name', config('app.name'));
@endphp

@section('title', 'Site Haritası | ' . $siteName)
@section('meta_description', $siteName . ' site haritası — tüm ürünler, blog yazıları, hizmet bölgeleri ve sayfalara hızlı erişim.')
@section('canonical', route('sitemap-page'))

@push('json-ld')
@php
    $breadcrumbJsonLd = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Site Haritası', 'item' => route('sitemap-page')],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($breadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')

{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <nav class="breadcrumb-custom" aria-label="Breadcrumb">
            <a href="{{ route('home') }}"><i class="fa-solid fa-home"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Site Haritası</span>
        </nav>
        <h1 class="page-title">Site Haritası</h1>
        <p class="page-subtitle">Tüm ürünler, yazılar ve sayfalarımıza tek bakışta ulaşın.</p>
    </div>
</header>

<section class="sitemap-page-section py-5">
    <div class="container">

        {{-- Ana Sayfalar --}}
        <div class="sitemap-block mb-5">
            <h2 class="sitemap-block__title">
                <i class="fa-solid fa-house"></i> Ana Sayfalar
            </h2>
            <ul class="sitemap-list">
                <li><a href="{{ route('home') }}">Anasayfa</a></li>
                <li><a href="{{ route('products.all') }}">Tüm Ürünler</a></li>
                <li><a href="{{ route('blog.index') }}">Blog</a></li>
                <li><a href="{{ route('gallery') }}">Galeri</a></li>
                <li><a href="{{ route('faq') }}">Sıkça Sorulan Sorular</a></li>
                <li><a href="{{ route('contact') }}">İletişim</a></li>
            </ul>
        </div>

        {{-- Ürün Kategorileri --}}
        @if($groups['categories']->isNotEmpty())
        <div class="sitemap-block mb-5">
            <h2 class="sitemap-block__title">
                <i class="fa-solid fa-box-seam"></i> Ürün Kategorileri
            </h2>
            <div class="row g-4">
                @foreach($groups['categories'] as $category)
                <div class="col-md-6 col-lg-4">
                    <h3 class="sitemap-group__title">
                        <a href="{{ route('products.index', $category->slug) }}">
                            {{ $category->name }}
                        </a>
                    </h3>
                    @if($category->products->isNotEmpty())
                    <ul class="sitemap-list sitemap-list--nested">
                        @foreach($category->products as $product)
                        <li>
                            <a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a>
                        </li>
                        @endforeach
                    </ul>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Hizmet Bölgelerimiz --}}
        @if($groups['city_pages']->isNotEmpty())
        <div class="sitemap-block mb-5">
            <h2 class="sitemap-block__title">
                <i class="fa-solid fa-location-dot"></i> Hizmet Bölgelerimiz
            </h2>
            <ul class="sitemap-list sitemap-list--columns">
                @foreach($groups['city_pages'] as $cityPage)
                <li>
                    <a href="{{ url('/' . $cityPage->city_slug . '-koy-urunleri') }}">
                        {{ $cityPage->city_name }} Köy Ürünleri
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Blog Kategorileri --}}
        @if($groups['blog_categories']->isNotEmpty())
        <div class="sitemap-block mb-5">
            <h2 class="sitemap-block__title">
                <i class="fa-solid fa-pen-nib"></i> Blog Yazıları
            </h2>
            <div class="row g-4">
                @foreach($groups['blog_categories'] as $blogCategory)
                @if($blogCategory->posts->isNotEmpty())
                <div class="col-md-6">
                    <h3 class="sitemap-group__title">
                        <a href="{{ route('blog.category', $blogCategory->slug) }}">
                            {{ $blogCategory->name }}
                        </a>
                        <small class="sitemap-group__count">({{ $blogCategory->posts->count() }})</small>
                    </h3>
                    <ul class="sitemap-list sitemap-list--nested">
                        @foreach($blogCategory->posts as $post)
                        <li>
                            <a href="{{ route('blog.show', [$blogCategory->slug, $post->slug]) }}">
                                {{ $post->title }}
                            </a>
                            @if($post->published_at)
                            <small class="sitemap-list__date">
                                {{ $post->published_at->translatedFormat('d M Y') }}
                            </small>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
                @endforeach
            </div>
        </div>
        @endif

        {{-- Bilgi Sayfaları --}}
        @if($groups['pages']->isNotEmpty())
        <div class="sitemap-block mb-5">
            <h2 class="sitemap-block__title">
                <i class="fa-solid fa-circle-info"></i> Bilgi Sayfaları
            </h2>
            <ul class="sitemap-list sitemap-list--columns">
                @foreach($groups['pages'] as $page)
                <li>
                    <a href="{{ route('pages.show', $page->slug) }}">{{ $page->title }}</a>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

    </div>
</section>

@endsection
