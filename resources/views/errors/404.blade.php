@extends('layouts.app')

@section('title', '404 - Sayfa Bulunamadı | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Aradığınız sayfa bulunamadı.')
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="error-page">
        <div class="error-page__card">
            <div class="error-page__emoji">🌿</div>
            <h1 class="error-page__code">404</h1>
            <h2 class="error-page__title">Sayfa Bulunamadı</h2>
            <div class="error-page__divider"></div>
            <p class="error-page__text">
                Aradığınız sayfa kaldırılmış, adı değişmiş veya geçici olarak kullanım dışı olabilir.
            </p>
            <div class="error-page__actions">
                <a href="{{ route('home') }}" class="btn-error-primary">
                    <i class="fa-solid fa-house me-2"></i> Ana Sayfaya Dön
                </a>
                <a href="{{ route('products.all') }}" class="btn-error-outline">
                    <i class="fa-solid fa-basket-shopping me-2"></i> Ürünleri Keşfet
                </a>
            </div>
        </div>
    </section>
@endsection
