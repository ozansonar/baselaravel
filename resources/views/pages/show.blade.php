@extends('layouts.app')

@section('title', $page->meta_title ?? $page->title)
@section('meta_description', $page->meta_description ?? $page->excerpt)
@section('canonical', url()->current())

@section('content')

    {{-- ══════════ PAGE HERO ══════════ --}}
    <section class="page-hero">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Anasayfa</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $page->title }}</li>
                </ol>
            </nav>
            <h1 class="page-hero__title">{{ $page->title }}</h1>
            @if($page->excerpt)
                <p class="page-hero__lead">{{ $page->excerpt }}</p>
            @endif
        </div>
    </section>

    {{-- ══════════ CONTENT ══════════ --}}
    <section class="section--tight">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 mx-auto">
                    <article class="article__body">
                        {!! $page->content !!}
                    </article>
                </div>
            </div>
        </div>
    </section>

@endsection
