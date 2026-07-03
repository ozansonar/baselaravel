@extends('layouts.app')

@section('title', ($activeCategory ? $activeCategory->name . ' - ' : '') . 'Blog | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', $activeCategory ? $activeCategory->name . ' kategorisindeki blog yazıları.' : 'Çiftlik hayatı, sağlıklı beslenme ve doğal ürünler hakkında güncel blog yazıları.')
@section('canonical', $activeCategory ? route('blog.category', $activeCategory->slug) : route('blog.index'))
@section('og_image', url(upload_url(\App\Models\Setting::getValue('og_image', ''), 'lg')))

@push('json-ld')
@php
    $blogBreadcrumbItems = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => route('home')],
    ];
    if ($activeCategory) {
        $blogBreadcrumbItems[] = ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => route('blog.index')];
        $blogBreadcrumbItems[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $activeCategory->name];
    } else {
        $blogBreadcrumbItems[] = ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog'];
    }
    $blogBreadcrumbJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $blogBreadcrumbItems,
    ];
@endphp
<script type="application/ld+json">{!! json_encode($blogBreadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@push('pagination-meta')
@if($posts->currentPage() > 1)
<link rel="prev" href="{{ $posts->previousPageUrl() }}">
@endif
@if($posts->hasMorePages())
<link rel="next" href="{{ $posts->nextPageUrl() }}">
@endif
@endpush

@section('content')

{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <nav class="breadcrumb-custom" aria-label="Breadcrumb">
            <a href="{{ route('home') }}"><i class="fa-solid fa-home"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            @if($activeCategory)
            <a href="{{ route('blog.index') }}">Blog</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>{{ $activeCategory->name }}</span>
            @else
            <span>Blog</span>
            @endif
        </nav>
        <h1 class="page-title">{{ $activeCategory ? $activeCategory->name : 'Blog' }}</h1>
        <p class="page-subtitle">{{ $activeCategory ? $activeCategory->name . ' kategorisindeki yazılar' : 'Çiftlik yaşamından notlar, tarifler ve ipuçları' }}</p>
    </div>
</header>

{{-- Blog Section --}}
<section class="blog-section">
    <div class="container">
        <div class="row">
            {{-- Blog Content --}}
            <div class="col-lg-9">
                <div class="row">
                    @forelse($posts as $post)
                    <div class="col-lg-4 col-md-6 mb-4">
                        <article class="blog-card-list">
                            <a href="{{ route('blog.show', [$post->category->slug, $post->slug]) }}" class="blog-card-image">
                                @if($post->image)
                                <img src="{{ upload_url($post->image, 'md') }}" alt="{{ $post->title }}" width="400" height="250" loading="lazy" decoding="async">
                                @else
                                <i class="{{ $post->category?->icon ?? 'fa-solid fa-pen-nib' }}"></i>
                                @endif
                                @if($post->category)
                                <span class="blog-card-category">{{ $post->category->name }}</span>
                                @endif
                            </a>
                            <div class="blog-card-body">
                                <div class="blog-card-meta">
                                    <time datetime="{{ $post->published_at?->format('Y-m-d') }}"><i class="fa-solid fa-calendar-alt"></i> {{ $post->published_at?->translatedFormat('d M Y') }}</time>
                                    <span><i class="fa-solid fa-eye"></i> {{ number_format($post->views) }}</span>
                                </div>
                                <h2 class="blog-card-title">
                                    <a href="{{ route('blog.show', [$post->category->slug, $post->slug]) }}">{{ Str::limit($post->title, 65) }}</a>
                                </h2>
                                <p class="blog-card-excerpt">
                                    {{ Str::limit($post->excerpt ?? strip_tags($post->body), 100) }}
                                </p>
                                <div class="blog-card-footer">
                                    <a href="{{ route('blog.show', [$post->category->slug, $post->slug]) }}" class="read-more">
                                        Devamını Oku <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>
                    @empty
                    <div class="col-12 text-center py-5">
                        <i class="fa-solid fa-pen-nib fa-3x text-muted mb-3 d-block"></i>
                        <p class="text-brown-light">Henüz blog yazısı yayınlanmamış.</p>
                    </div>
                    @endforelse
                </div>

                {{-- Pagination --}}
                @if($posts->hasPages())
                <div class="pagination-container">
                    {{ $posts->links('vendor.pagination.custom') }}
                </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-3">
                <aside class="blog-sidebar">
                    {{-- Categories Widget --}}
                    @if($categories->isNotEmpty())
                    <div class="sidebar-widget">
                        <h4 class="widget-title"><i class="fa-solid fa-folder"></i> Kategoriler</h4>
                        <ul class="category-list">
                            @foreach($categories as $category)
                            <li>
                                <a href="{{ route('blog.category', $category->slug) }}">
                                    {{ $category->name }}
                                    <span class="category-count">{{ $category->posts_count }}</span>
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    {{-- Popular Posts Widget --}}
                    @php
                        $popularPosts = Cache::remember('popular_blog_posts', 3600, function () {
                            return \App\Models\BlogPost::published()
                                ->with('category')
                                ->orderByDesc('views')
                                ->limit(3)
                                ->get();
                        });
                    @endphp
                    @if($popularPosts->isNotEmpty())
                    <div class="sidebar-widget">
                        <h4 class="widget-title"><i class="fa-solid fa-fire"></i> Popüler Yazılar</h4>
                        @foreach($popularPosts as $popular)
                        <div class="popular-post">
                            <div class="popular-post-image">
                                @if($popular->image)
                                <img src="{{ upload_url($popular->image, 'thumb') }}" alt="{{ $popular->title }}" width="80" height="80" loading="lazy" decoding="async">
                                @else
                                <i class="{{ $popular->category?->icon ?? 'fa-solid fa-pen-nib' }}"></i>
                                @endif
                            </div>
                            <div class="popular-post-content">
                                <h5><a href="{{ route('blog.show', [$popular->category->slug, $popular->slug]) }}">{{ Str::limit($popular->title, 50) }}</a></h5>
                                <span class="popular-post-date"><i class="fa-solid fa-calendar-alt me-1"></i> {{ $popular->published_at?->translatedFormat('d F Y') }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </aside>
            </div>
        </div>
    </div>
</section>

@endsection
