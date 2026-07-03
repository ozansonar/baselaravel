@extends('layouts.app')

@section('title', '500 - Sunucu Hatası | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Bir sunucu hatası oluştu.')
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="error-page">
        <div class="error-page__card">
            <div class="error-page__emoji">⚙️</div>
            <h1 class="error-page__code">500</h1>
            <h2 class="error-page__title">Sunucu Hatası</h2>
            <div class="error-page__divider"></div>
            <p class="error-page__text">
                Beklenmeyen bir hata oluştu. Ekibimiz bilgilendirildi ve en kısa sürede çözülecektir.
            </p>
            <div class="error-page__actions">
                <a href="{{ route('home') }}" class="btn-error-primary">
                    <i class="fa-solid fa-house me-2"></i> Ana Sayfaya Dön
                </a>
            </div>
        </div>
    </section>
@endsection
