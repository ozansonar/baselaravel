@extends('layouts.app')

@section('title', '429 - Çok Fazla İstek | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Çok fazla istek gönderdiniz. Lütfen biraz bekleyip tekrar deneyin.')
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="error-page">
        <div class="error-page__card">
            <div class="error-page__emoji">🛑</div>
            <h1 class="error-page__code">429</h1>
            <h2 class="error-page__title">Çok Fazla İstek</h2>
            <div class="error-page__divider"></div>
            <p class="error-page__text">
                Kısa sürede çok fazla istek gönderdiniz. Lütfen biraz bekleyip tekrar deneyin.
            </p>
            <div class="error-page__actions">
                <a href="{{ route('home') }}" class="btn-error-primary">
                    <i class="fa-solid fa-house me-2"></i> Ana Sayfaya Dön
                </a>
            </div>
        </div>
    </section>
@endsection
