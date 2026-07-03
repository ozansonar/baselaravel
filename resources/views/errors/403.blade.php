@extends('layouts.app')

@section('title', '403 - Erişim Engellendi | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Bu sayfaya erişim izniniz bulunmamaktadır.')
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="error-page">
        <div class="error-page__card">
            <div class="error-page__emoji">🔒</div>
            <h1 class="error-page__code">403</h1>
            <h2 class="error-page__title">Erişim Engellendi</h2>
            <div class="error-page__divider"></div>
            <p class="error-page__text">
                Bu sayfaya erişim izniniz bulunmamaktadır. Lütfen yetkili bir hesapla giriş yapın.
            </p>
            <div class="error-page__actions">
                <a href="{{ route('home') }}" class="btn-error-primary">
                    <i class="fa-solid fa-house me-2"></i> Ana Sayfaya Dön
                </a>
                <a href="{{ route('login') }}" class="btn-error-outline">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Giriş Yap
                </a>
            </div>
        </div>
    </section>
@endsection
