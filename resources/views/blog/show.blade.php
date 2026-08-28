@extends('layouts.app')

@section('title', ($post->meta_title ?? $post->title))
@section('meta_description', $post->meta_description ?? \Illuminate\Support\Str::limit($post->excerpt ?? strip_tags($post->body), 160))
@section('canonical', $canonicalUrl)
@section('og_type', 'article')
@section('og_locale', $post->locale)
@if($post->image)
@section('og_image', url(upload_url($post->image, 'lg')))
@endif

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('css/content-attachments.css') }}">
@endpush

@section('content')

    {{-- Okuma çubuğu: uzun yazıda ne kadar kaldığını gösteriyor. --}}
    <div class="read-progress" data-read-progress role="presentation"></div>

    {{-- ══════════ PAGE HERO ══════════ --}}
    <section class="page-hero">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('site.nav.home') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">{{ __('site.blog.title') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('blog.category', $post->category->slug) }}">{{ $post->category->name }}</a></li>
                </ol>
            </nav>
            @if($post->category)
                <span class="page-hero__eyebrow"><i class="fa-solid fa-folder-open"></i> {{ $post->category->name }}</span>
            @endif
            <h1 class="page-hero__title">{{ $post->title }}</h1>
            <div class="article__meta mt-3">
                <span><i class="fa-regular fa-calendar me-1"></i>{{ optional($post->published_at)->translatedFormat('d M Y') }}</span>
                @if($post->author?->full_name)
                    <span><i class="fa-regular fa-user me-1"></i>{{ $post->author->full_name }}</span>
                @endif
                <span><i class="fa-regular fa-eye me-1"></i>{{ __('site.blog.views_count', ['count' => number_format((int) $post->views)]) }}</span>
                @if($post->category)
                    <span class="pill pill--active"><i class="fa-solid fa-folder me-1"></i>{{ $post->category->name }}</span>
                @endif
            </div>

            {{-- Bu yazının öteki dildeki sürümüne doğrudan geçiş --}}
            @include('partials.content-language-links', ['contentLocale' => $post->locale])
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

                    {{-- Ekler: yazının diline ait dosyalar, türlerine göre --}}
                    @if($attachmentGroups->isNotEmpty())
                        @include('partials.content-attachments', ['attachmentGroups' => $attachmentGroups])
                    @endif

                    <hr class="divider my-4">

                    {{-- Share --}}
                    @include('partials.social-share', [
                        'url'         => route('blog.show', [$post->category->slug, $post->slug]),
                        'title'       => $post->title,
                        'description' => $post->meta_description ?? \Illuminate\Support\Str::limit($post->excerpt ?? strip_tags($post->body), 160),
                        'image'       => $post->image ? url(upload_url($post->image, 'lg')) : '',
                    ])

                    {{-- Yorumlar paylaşım düğmelerinin hemen altında: yazıyı
                         bitiren kişi yorum kutusunu görmek için ilgili
                         içeriklerin arasından geçmek zorunda kalmıyor. --}}
                    <hr class="divider my-4">

                    @include('partials.blog-comments', ['post' => $post, 'comments' => $comments, 'commentCount' => $commentCount])

                </div>
            </div>
        </div>
    </section>

    {{-- ══════════ RELATED ══════════ --}}
    @if($relatedPosts->isNotEmpty())
        <section class="section--tight section--soft">
            <div class="container">
                <div class="section__head--center mb-5">
                    <span class="section__eyebrow"><i class="fa-solid fa-newspaper"></i> {{ __('site.blog.keep_reading') }}</span>
                    <h2 class="section__title mb-0">{{ __('site.blog.related') }}</h2>
                </div>
                <div class="row g-4">
                    @foreach($relatedPosts as $related)
                        @continue($related->id === $post->id)
                        <div class="col-md-6 col-lg-4" data-reveal data-reveal-delay="{{ $loop->index % 4 }}">
                            @include('partials.post-card', ['post' => $related, 'showExcerpt' => false])
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

@endsection

@push('scripts')
{{-- Büyütme penceresinin etiketleri sunucudan: pencere ziyaretçinin dilinde
     açılmalı, JS içine gömülü metin İngilizce sayfada yanlış olurdu. --}}
@php
    $attachmentLabels = [
        'close'    => __('site.attachments.close'),
        'prev'     => __('site.attachments.prev'),
        'next'     => __('site.attachments.next'),
        'download' => __('site.attachments.download'),
    ];
@endphp
<script type="application/json" id="attachmentsLabels">@json($attachmentLabels)</script>
<script src="{{ versioned_asset('js/content-attachments.js') }}" defer></script>
@endpush
