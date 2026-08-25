@extends('layouts.app')

@section('title', ($post->meta_title ?? $post->title))
@section('meta_description', $post->meta_description ?? \Illuminate\Support\Str::limit($post->excerpt ?? strip_tags($post->body), 160))
@section('canonical', route('blog.show', [$post->category->slug, $post->slug]))
@section('og_type', 'article')
@if($post->image)
@section('og_image', url(upload_url($post->image, 'lg')))
@endif

@section('content')

    {{-- ══════════ PAGE HERO ══════════ --}}
    <section class="page-hero">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Anasayfa</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">İçerikler</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('blog.category', $post->category->slug) }}">{{ $post->category->name }}</a></li>
                </ol>
            </nav>
            <h1 class="page-hero__title">{{ $post->title }}</h1>
            <div class="article__meta mt-3">
                <span><i class="fa-regular fa-calendar me-1"></i>{{ optional($post->published_at)->translatedFormat('d M Y') }}</span>
                @if($post->author?->full_name)
                    <span><i class="fa-regular fa-user me-1"></i>{{ $post->author->full_name }}</span>
                @endif
                <span><i class="fa-regular fa-eye me-1"></i>{{ number_format((int) $post->views) }} görüntülenme</span>
                @if($post->category)
                    <span class="pill pill--active"><i class="fa-solid fa-folder me-1"></i>{{ $post->category->name }}</span>
                @endif
            </div>
        </div>
    </section>

    {{-- ══════════ ARTICLE ══════════ --}}
    <section class="section--tight">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">

                    {{-- Cover --}}
                    @if($post->image)
                        <img src="{{ upload_url($post->image, 'lg') }}" alt="{{ $post->title }}" class="article__cover mb-4" loading="eager" decoding="async">
                    @endif

                    {{-- Body --}}
                    <div class="article__body">
                        {!! $autoLinkedBody !!}
                    </div>

                    <hr class="divider my-4">

                    {{-- Share --}}
                    @include('partials.social-share', [
                        'url'         => route('blog.show', [$post->category->slug, $post->slug]),
                        'title'       => $post->title,
                        'description' => $post->meta_description ?? \Illuminate\Support\Str::limit($post->excerpt ?? strip_tags($post->body), 160),
                        'image'       => $post->image ? url(upload_url($post->image, 'lg')) : '',
                    ])

                </div>
            </div>
        </div>
    </section>

    {{-- ══════════ RELATED ══════════ --}}
    @if($relatedPosts->isNotEmpty())
        <section class="section--tight section--soft">
            <div class="container">
                <div class="section__head--center mb-5">
                    <span class="section__eyebrow"><i class="fa-solid fa-newspaper"></i> Devamı</span>
                    <h2 class="section__title mb-0">İlgili İçerikler</h2>
                </div>
                <div class="row g-4">
                    @foreach($relatedPosts as $related)
                        @continue($related->id === $post->id)
                        <div class="col-md-6 col-lg-4">
                            <article class="post-card">
                                <a href="{{ route('blog.show', [$related->category->slug, $related->slug]) }}" class="post-card__media">
                                    @if($related->image)
                                        <img src="{{ upload_url($related->image, 'md') }}" alt="{{ $related->title }}" class="post-card__img" loading="lazy" decoding="async">
                                    @else
                                        <span class="post-card__ph"><i class="fa-regular fa-image"></i></span>
                                    @endif
                                    @if($related->category)
                                        <span class="post-card__cat">{{ $related->category->name }}</span>
                                    @endif
                                </a>
                                <div class="post-card__body">
                                    <div class="post-card__meta">
                                        <span><i class="fa-regular fa-calendar me-1"></i>{{ optional($related->published_at)->translatedFormat('d M Y') }}</span>
                                    </div>
                                    <h3 class="post-card__title">
                                        <a href="{{ route('blog.show', [$related->category->slug, $related->slug]) }}">{{ $related->title }}</a>
                                    </h3>
                                    <a href="{{ route('blog.show', [$related->category->slug, $related->slug]) }}" class="post-card__more">Devamını oku <i class="fa-solid fa-arrow-right"></i></a>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ══════════ COMMENTS ══════════ --}}
    <section class="section--tight">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    @include('partials.blog-comments', ['post' => $post, 'comments' => $comments, 'commentCount' => $commentCount])
                </div>
            </div>
        </div>
    </section>

@endsection
