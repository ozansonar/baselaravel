@extends('layouts.auth')

@section('title', 'E-posta Doğrulama')

@section('robots', 'noindex, nofollow')

@section('content')
    <h1 class="auth-card__title">E-postanızı doğrulayın</h1>
    <p class="auth-card__sub">
        <strong>{{ auth()->user()->email }}</strong> adresine bir doğrulama
        bağlantısı gönderdik. Hesabınızı kullanmaya başlamak için bağlantıya
        tıklamanız yeterli.
    </p>

    @if(session('success'))
        <div class="alert alert-success" role="alert">
            <i class="fa-solid fa-circle-check me-1"></i> {{ session('success') }}
        </div>
    @endif

    <p class="text-muted small">
        E-posta gelmediyse spam klasörünü kontrol edin. Bağlantının geçerlilik
        süresi 60 dakikadır.
    </p>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-paper-plane"></i> Bağlantıyı Tekrar Gönder
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn btn-link w-100 text-muted">
            Çıkış yap
        </button>
    </form>
@endsection
