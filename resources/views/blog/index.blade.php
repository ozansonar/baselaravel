@extends('layouts.app')

@section('title', $activeCategory?->name ? $activeCategory->name . ' — ' . __('site.blog.title') : __('site.blog.title'))
@section('meta_description', $activeCategory?->name ? __('site.blog.category_meta', ['category' => $activeCategory->name]) : __('site.blog.meta_desc'))
@section('canonical', url()->current())

@section('content')

    {{-- ══════════ PAGE HERO ══════════ --}}
    <section class="page-hero">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('site.nav.home') }}</a></li>
                    @if($activeCategory)
                        <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">{{ __('site.blog.title') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $activeCategory->name }}</li>
                    @else
                        <li class="breadcrumb-item active" aria-current="page">{{ __('site.blog.title') }}</li>
                    @endif
                </ol>
            </nav>
            <h1 class="page-hero__title">{{ $activeCategory?->name ?? __('site.blog.title') }}</h1>
            <p class="page-hero__lead">{{ __('site.blog.lead') }}</p>
        </div>
    </section>

    {{-- ══════════ LIST ══════════ --}}
    <section class="section--tight">
        <div class="container">

            {{-- Category filter --}}
            @if($categories->isNotEmpty())
                <nav class="pill-nav mb-4" aria-label="{{ __('site.misc.category_filter') }}">
                    <a href="{{ route('blog.index') }}" class="pill {{ $activeCategory ? '' : 'pill--active' }}">{{ __('site.blog.all') }}</a>
                    @foreach($categories as $cat)
                        <a href="{{ route('blog.category', $cat->slug) }}"
                           class="pill {{ $activeCategory?->id === $cat->id ? 'pill--active' : '' }}">{{ $cat->name }}</a>
                    @endforeach
                </nav>
            @endif

            @if($posts->isNotEmpty())
                <div class="row g-4">
                    @foreach($posts as $post)
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
                                    <h2 class="post-card__title">
                                        <a href="{{ route('blog.show', [$post->category->slug, $post->slug]) }}">{{ $post->title }}</a>
                                    </h2>
                                    @if($post->excerpt)
                                        <p class="post-card__excerpt">{{ \Illuminate\Support\Str::limit($post->excerpt, 110) }}</p>
                                    @endif
                                    <a href="{{ route('blog.show', [$post->category->slug, $post->slug]) }}" class="post-card__more">{{ __('site.actions.read_more') }} <i class="fa-solid fa-arrow-right"></i></a>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5">
                    {{ $posts->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            @else
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="fa-regular fa-newspaper"></i></div>
                    <h2 class="h5">{{ __('site.blog.empty') }}</h2>
                    <p class="mb-0">{{ __('site.blog.empty_lead') }}</p>
                </div>
            @endif

        </div>
    </section>

@endsection
