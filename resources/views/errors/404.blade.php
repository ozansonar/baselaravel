@extends('layouts.app')

@section('title', '404 - Sayfa Bulunamadı | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Aradığınız sayfa bulunamadı.')
@section('robots', 'noindex, nofollow')

@section('content')

    <section class="section">
        <div class="container">
            <div class="empty-state mw-readable mx-auto">
                <div class="empty-state__icon"><i class="fa-solid fa-compass"></i></div>
                <div class="stat__num text-brand">404</div>
                <h1 class="section__title mt-2">Sayfa bulunamadı</h1>
                <p class="section__lead mx-auto mb-4">
                    Aradığınız sayfa kaldırılmış, adı değişmiş veya geçici olarak kullanım dışı olabilir.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="{{ route('home') }}" class="btn btn-primary btn-lg">
                        <i class="fa-solid fa-house"></i> Anasayfa
                    </a>
                    <a href="{{ route('blog.index') }}" class="btn btn-light btn-lg">
                        <i class="fa-solid fa-book-open"></i> İçerikler
                    </a>
                </div>
            </div>
        </div>
    </section>

@endsection
