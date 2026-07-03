@extends('layouts.auth')

@section('title', 'Şifre Sıfırla')

@section('content')
    <h1 class="auth-card__title">Yeni şifre belirleyin</h1>
    <p class="auth-card__sub">Hesabınız için yeni bir şifre oluşturun</p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        {{-- Email --}}
        <div class="mb-3">
            <label for="email" class="form-label">E-posta Adresi</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                <input type="email"
                       class="form-control @error('email') is-invalid @enderror"
                       id="email" name="email" value="{{ old('email', $email) }}"
                       required readonly autocomplete="email">
                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Password --}}
        <div class="mb-3">
            <label for="password" class="form-label">Yeni Şifre</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                <input type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       id="password" name="password"
                       placeholder="••••••••" required autofocus autocomplete="new-password">
                @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-text">En az 8 karakter kullanın.</div>
        </div>

        {{-- Password confirmation --}}
        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Şifre (Tekrar)</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                <input type="password"
                       class="form-control"
                       id="password_confirmation" name="password_confirmation"
                       placeholder="••••••••" required autocomplete="new-password">
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-check"></i> Şifreyi Sıfırla
        </button>
    </form>

    <p class="text-center text-muted mt-4 mb-0">
        <a href="{{ route('login') }}" class="fw-semibold">
            <i class="fa-solid fa-arrow-left"></i> Giriş
        </a>
    </p>
@endsection
