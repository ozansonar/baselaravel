@extends('layouts.app')

@section('title', $page->meta_title ?? $page->title . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', $page->meta_description ?? $page->excerpt)
@section('canonical', route('pages.show', $page->slug))
@if($page->image)
@section('og_image', url(upload_url($page->image)))
@endif

@push('json-ld')
@php
    $pageBreadcrumbJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $page->title],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($pageBreadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')

{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <nav class="breadcrumb-custom" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>{{ $page->title }}</span>
        </nav>
        <h1 class="page-title">{{ $page->title }}</h1>
        @if($page->excerpt)
        <p class="page-subtitle">{{ $page->excerpt }}</p>
        @endif
    </div>
</header>

{{-- Page Content --}}
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                @if($page->image)
                <div class="mb-4">
                    <x-responsive-image :path="$page->image" :alt="$page->title"
                                        size="lg" class="rounded-4 w-100" :responsive="true" />
                </div>
                @endif

                <article class="page-content">
                    {!! $page->content !!}
                </article>
            </div>
        </div>
    </div>
</section>

@endsection

@push('styles')
<style>
    .page-content { line-height: 1.9; color: var(--brown); }
    .page-content h2 { color: var(--green-dark); font-size: 1.8rem; margin-top: 2rem; margin-bottom: 1rem; }
    .page-content h3 { color: var(--green-dark); font-size: 1.4rem; margin-top: 1.5rem; margin-bottom: 0.8rem; }
    .page-content p { margin-bottom: 1.2rem; }
    .page-content ul, .page-content ol { padding-left: 1.5rem; margin-bottom: 1.2rem; }
    .page-content li { margin-bottom: 0.5rem; }
    .page-content strong { color: var(--green-dark); }
    .page-content a { color: var(--green-primary); }
    .page-content a:hover { color: var(--green-dark); }
</style>
@endpush
