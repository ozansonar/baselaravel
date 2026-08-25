@extends('layouts.auth')

@section('title', 'Giriş Yap')

@section('content')
    <h1 class="auth-card__title">Tekrar hoş geldiniz</h1>
    <p class="auth-card__sub">Hesabınıza giriş yapın</p>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        {{-- Email --}}
        <div class="mb-3">
            <label for="email" class="form-label">E-posta Adresi</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                <input type="email"
                       class="form-control @error('email') is-invalid @enderror"
                       id="email" name="email" value="{{ old('email') }}"
                       placeholder="ornek@mail.com" required autofocus autocomplete="email">
                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Password --}}
        <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between">
                <label for="password" class="form-label mb-0">Şifre</label>
                <a href="{{ route('password.request') }}" class="small">Şifremi unuttum</a>
            </div>
            <div class="input-group mt-2">
                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                <input type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       id="password" name="password"
                       placeholder="••••••••" required autocomplete="current-password">
                @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Remember --}}
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
            <label class="form-check-label" for="remember">Beni hatırla</label>
        </div>

        <x-recaptcha />

        <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-right-to-bracket"></i> Giriş Yap
        </button>
    </form>

    <p class="text-center text-muted mt-4 mb-0">
        Hesabınız yok mu? <a href="{{ route('register') }}" class="fw-semibold">Üye Ol</a>
    </p>
@endsection
