@extends('layouts.app')

@section('title', '404 - ' . __('site.errors.404_title') . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', __('site.errors.404'))
@section('robots', 'noindex, nofollow')

@section('content')

    <section class="section">
        <div class="container">
            <div class="empty-state mw-readable mx-auto">
                <div class="empty-state__icon"><i class="fa-solid fa-compass"></i></div>
                <div class="stat__num text-brand">404</div>
                <h1 class="section__title mt-2">{{ __('site.errors.404_title') }}</h1>
                <p class="section__lead mx-auto mb-4">
                    {{ __('site.errors.404_lead') }}
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="{{ route('home') }}" class="btn btn-primary btn-lg">
                        <i class="fa-solid fa-house"></i> {{ __('site.nav.home') }}
                    </a>
                    <a href="{{ route('blog.index') }}" class="btn btn-light btn-lg">
                        <i class="fa-solid fa-book-open"></i> {{ __('site.blog.title') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

@endsection
