@extends('layouts.app')

@section('title', ($post->meta_title ?? $post->title) . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', $post->meta_description ?? Str::limit($post->excerpt ?? strip_tags($post->body), 160))
@section('canonical', route('blog.show', [$post->category->slug, $post->slug]))

@section('og_title', $post->meta_title ?? $post->title)
@section('og_description', $post->meta_description ?? Str::limit($post->excerpt ?? strip_tags($post->body), 160))
@section('og_type', 'article')
@if($post->image)
@section('og_image', url(upload_url($post->image, 'lg')))
@endif

@if($post->image)
@push('preload')
<link rel="preload" as="image" href="{{ upload_url($post->image, 'lg') }}" fetchpriority="high">
@endpush
@endif

@push('json-ld')
@php
    $siteName = \App\Models\Setting::getValue('site_name', config('app.name'));
    $siteLogo = \App\Models\Setting::getValue('site_logo');
    $logoUrl = $siteLogo ? url(upload_url($siteLogo)) : asset('images/logo.png');
    $postImage = $post->image ? url(upload_url($post->image, 'lg')) : url($logoUrl);

    $articleJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post->title,
        'description' => $post->meta_description ?? Str::limit($post->excerpt ?? strip_tags($post->body), 160),
        'image' => $postImage,
        'datePublished' => ($post->published_at ?? $post->created_at)->toIso8601String(),
        'dateModified' => $post->updated_at->toIso8601String(),
        'author' => [
            '@type' => 'Person',
            'name' => $post->author?->full_name ?? 'Orhan Gazi SONAR',
            'url' => url('/'),
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => $siteName,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $logoUrl,
            ],
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => route('blog.show', [$post->category->slug, $post->slug]),
        ],
        'inLanguage' => 'tr',
        'articleSection' => $post->category->name,
        'wordCount' => str_word_count(strip_tags($post->body)),
        'breadcrumb' => [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => route('home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => route('blog.index')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $post->category->name, 'item' => route('blog.category', $post->category->slug)],
                ['@type' => 'ListItem', 'position' => 4, 'name' => $post->title],
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($articleJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/vendor/jquery-validation-engine/css/validationEngine.jquery.min.css') }}">
@endpush

@section('content')

{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <nav class="breadcrumb-custom" aria-label="Breadcrumb">
            <a href="{{ route('home') }}"><i class="fa-solid fa-home"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <a href="{{ route('blog.index') }}">Blog</a>
            <i class="fa-solid fa-chevron-right"></i>
            <a href="{{ route('blog.category', $post->category->slug) }}">{{ $post->category->name }}</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>{{ Str::limit($post->title, 40) }}</span>
        </nav>
        <h1 class="page-title">{{ $post->title }}</h1>
        <p class="page-subtitle">{{ $post->category?->name }}</p>
    </div>
</header>

{{-- Blog Detail --}}
<section class="blog-section">
    <div class="container">
        <div class="row">
            {{-- Blog Content --}}
            <div class="col-lg-8">
                {{-- Header --}}
                <div class="blog-detail-header">
                    @if($post->category)
                    <span class="blog-detail-category">{{ $post->category->name }}</span>
                    @endif
                    <div class="blog-detail-meta">
                        <time datetime="{{ $post->published_at?->format('Y-m-d') }}"><i class="fa-solid fa-calendar-alt"></i> {{ $post->published_at?->translatedFormat('d F Y') }}</time>
                        <span><i class="fa-solid fa-user"></i> {{ $post->author?->full_name ?: 'Orhan Gazi SONAR' }}</span>
                        <span><i class="fa-solid fa-eye"></i> {{ number_format($post->views) }} görüntülenme</span>
                    </div>
                </div>

                {{-- Cover Image --}}
                @if($post->image)
                <figure class="blog-detail-image">
                    <x-responsive-image :path="$post->image" :alt="$post->title" size="lg" loading="eager" fetchpriority="high" />
                </figure>
                @endif

                {{-- Content --}}
                <div class="blog-detail-content">
                    {!! $autoLinkedBody !!}
                </div>

                {{-- Share --}}
                <div class="blog-card-list mb-4">
                    <div class="blog-card-body">
                        @include('partials.social-share', [
                            'url'         => route('blog.show', [$post->category->slug, $post->slug]),
                            'title'       => $post->title . ' — ' . \App\Models\Setting::getValue('site_name', config('app.name')),
                            'description' => $post->meta_description ?? Str::limit($post->excerpt ?? strip_tags($post->body), 160),
                            'image'       => $post->image ? url(upload_url($post->image, 'lg')) : '',
                        ])
                    </div>
                </div>

                {{-- Related Posts --}}
                @if($relatedPosts->isNotEmpty())
                <h2 class="mb-4 text-green-dark">İlgili Yazılar</h2>
                <div class="row g-4">
                    @foreach($relatedPosts as $related)
                    @if($related->id !== $post->id)
                    <div class="col-md-6">
                        <article class="blog-card-list">
                            <div class="blog-card-image">
                                @if($related->image)
                                <img src="{{ upload_url($related->image, 'md') }}" alt="{{ $related->title }}" width="400" height="220" loading="lazy" decoding="async">
                                @else
                                <i class="{{ $related->category?->icon ?? 'fa-solid fa-pen-nib' }}"></i>
                                @endif
                                @if($related->category)
                                <span class="blog-card-category">{{ $related->category->name }}</span>
                                @endif
                            </div>
                            <div class="blog-card-body">
                                <div class="blog-card-meta">
                                    <span><i class="fa-solid fa-calendar-alt"></i> {{ $related->published_at?->translatedFormat('d F Y') }}</span>
                                </div>
                                <h4 class="blog-card-title">
                                    <a href="{{ route('blog.show', [$related->category->slug, $related->slug]) }}">{{ $related->title }}</a>
                                </h4>
                                <a href="{{ route('blog.show', [$related->category->slug, $related->slug]) }}" class="read-more">
                                    Devamını Oku <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>
                        </article>
                    </div>
                    @endif
                    @endforeach
                </div>
                @endif

                {{-- Comments --}}
                @include('partials.blog-comments')
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-4">
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

@push('scripts')
{{-- jQuery (only for this page - Validation Engine dependency) --}}
<script src="{{ asset('assets/vendor/jquery/jquery-3.7.1.min.js') }}"></script>
{{-- jQuery Validation Engine --}}
<script src="{{ asset('assets/vendor/jquery-validation-engine/js/jquery.validationEngine-tr.js') }}"></script>
<script src="{{ asset('assets/vendor/jquery-validation-engine/js/jquery.validationEngine.min.js') }}"></script>
<script>
(function ($) {
    'use strict';

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) return;

    // Common validation options
    var validationOptions = {
        promptPosition: 'bottomLeft',
        scroll: false,
        focusFirstField: true,
        autoHidePrompt: true,
        autoHideDelay: 4000,
        onValidationComplete: function (form, status) {
            if (status) {
                submitComment(form[0] || form);
            }
        }
    };

    // Attach validation to all comment forms
    $('.blog-comment-form').validationEngine('attach', validationOptions);

    // Reply button toggle
    document.querySelectorAll('.blog-comment-reply-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var commentId = this.dataset.commentId;
            var replyForm = document.getElementById('reply-form-' + commentId);
            if (replyForm) {
                replyForm.classList.toggle('d-none');
                if (!replyForm.classList.contains('d-none')) {
                    // Attach validation on newly visible reply form
                    $(replyForm).find('form').validationEngine('attach', validationOptions);
                    replyForm.querySelector('input[name="name"]').focus();
                }
            }
        });
    });

    // Cancel reply
    window.cancelReply = function (commentId) {
        var replyForm = document.getElementById('reply-form-' + commentId);
        if (replyForm) {
            replyForm.classList.add('d-none');
            var $form = $(replyForm).find('form');
            $form[0].reset();
            $form.validationEngine('hideAll');
        }
    };

    // Submit comment via AJAX
    function submitComment(form) {
        var $form = $(form);
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gönderiliyor...';

        var formData = new FormData(form);
        var data = {};
        formData.forEach(function (value, key) {
            if (key !== '_token') data[key] = value;
        });

        // Get reCAPTCHA response from any widget on page
        if (!data['g-recaptcha-response'] && typeof grecaptcha !== 'undefined') {
            data['g-recaptcha-response'] = grecaptcha.getResponse();
        }

        fetch('{{ route("blog-comments.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        })
        .then(function (r) { return r.json(); })
        .then(function (response) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;

            if (response.success) {
                form.reset();
                $form.validationEngine('hideAll');
                if (typeof grecaptcha !== 'undefined') grecaptcha.reset();
                showResultModal('success', response.message);

                // Hide reply form if it's a reply
                var parentId = form.dataset.parentId;
                if (parentId) {
                    var replyFormEl = document.getElementById('reply-form-' + parentId);
                    if (replyFormEl) replyFormEl.classList.add('d-none');
                }
            } else if (response.errors) {
                var messages = [];
                Object.keys(response.errors).forEach(function (key) {
                    messages.push(response.errors[key][0]);
                });
                showResultModal('error', messages.join('<br>'));
            } else {
                showResultModal('error', response.message || 'Bir hata oluştu.');
            }
        })
        .catch(function () {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
            showResultModal('error', 'Bir hata oluştu. Lütfen tekrar deneyin.');
        });
    }
})(jQuery);
</script>
@endpush
