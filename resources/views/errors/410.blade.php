@extends('layouts.app')

@section('title', '410 - Sayfa Kaldırıldı | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Bu sayfa kalıcı olarak kaldırılmıştır.')
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="error-page">
        <div class="error-page__card">
            <div class="error-page__emoji">🍂</div>
            <h1 class="error-page__code">410</h1>
            <h2 class="error-page__title">Sayfa Kaldırıldı</h2>
            <div class="error-page__divider"></div>
            <p class="error-page__text">
                Bu sayfa kalıcı olarak kaldırılmıştır ve artık kullanılamaz.
            </p>
            <div class="error-page__actions">
                <a href="{{ route('home') }}" class="btn-error-primary">
                    <i class="fa-solid fa-house me-2"></i> Ana Sayfaya Dön
                </a>
                <a href="{{ route('blog.index') }}" class="btn-error-outline">
                    <i class="fa-solid fa-book-open me-2"></i> Blog'a Göz At
                </a>
            </div>
        </div>
    </section>
@endsection
