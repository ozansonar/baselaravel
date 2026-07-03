@extends('layouts.app')

@section('title', '419 - Oturum Süresi Doldu | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Oturum süreniz dolmuştur. Lütfen sayfayı yenileyip tekrar deneyin.')
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="error-page">
        <div class="error-page__card">
            <div class="error-page__emoji">⏳</div>
            <h1 class="error-page__code">419</h1>
            <h2 class="error-page__title">Oturum Süresi Doldu</h2>
            <div class="error-page__divider"></div>
            <p class="error-page__text">
                Oturum süreniz dolmuş veya güvenlik doğrulaması geçersiz. Lütfen sayfayı yenileyip tekrar deneyin.
            </p>
            <div class="error-page__actions">
                <a href="{{ url()->current() }}" class="btn-error-primary"
                   onclick="event.preventDefault(); window.location.reload();">
                    <i class="fa-solid fa-rotate-right me-2"></i> Sayfayı Yenile
                </a>
                <a href="{{ route('home') }}" class="btn-error-outline">
                    <i class="fa-solid fa-house me-2"></i> Ana Sayfaya Dön
                </a>
            </div>
        </div>
    </section>
@endsection
