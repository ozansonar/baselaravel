@extends('layouts.auth')

@section('title', 'Üye Ol')

@section('content')
    <h1 class="auth-card__title">Aramıza katılın</h1>
    <p class="auth-card__sub">Ücretsiz hesap oluşturun</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        {{-- First / Last name --}}
        <div class="row g-3 mb-3">
            <div class="col-6">
                <label for="first_name" class="form-label">Ad</label>
                <input type="text"
                       class="form-control @error('first_name') is-invalid @enderror"
                       id="first_name" name="first_name" value="{{ old('first_name') }}"
                       placeholder="Adınız" required autofocus autocomplete="given-name">
                @error('first_name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-6">
                <label for="last_name" class="form-label">Soyad</label>
                <input type="text"
                       class="form-control @error('last_name') is-invalid @enderror"
                       id="last_name" name="last_name" value="{{ old('last_name') }}"
                       placeholder="Soyadınız" required autocomplete="family-name">
                @error('last_name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Email --}}
        <div class="mb-3">
            <label for="email" class="form-label">E-posta Adresi</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                <input type="email"
                       class="form-control @error('email') is-invalid @enderror"
                       id="email" name="email" value="{{ old('email') }}"
                       placeholder="ornek@mail.com" required autocomplete="email">
                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Phone (optional) --}}
        <div class="mb-3">
            <label for="phone" class="form-label">Telefon <span class="text-muted fw-normal">(opsiyonel)</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                <input type="tel"
                       class="form-control @error('phone') is-invalid @enderror"
                       id="phone" name="phone" value="{{ old('phone') }}"
                       placeholder="05XX XXX XX XX" autocomplete="tel">
                @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Password --}}
        <div class="mb-3">
            <label for="password" class="form-label">Şifre</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                <input type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       id="password" name="password"
                       placeholder="••••••••" required autocomplete="new-password">
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

        <x-recaptcha />

        <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-user-plus"></i> Üye Ol
        </button>
    </form>

    <p class="text-center text-muted mt-4 mb-0">
        Zaten üye misiniz? <a href="{{ route('login') }}" class="fw-semibold">Giriş Yap</a>
    </p>
@endsection
