@extends('layouts.auth')

@section('title', __('site.login.title'))

@section('content')
    <h1 class="auth-card__title">{{ __('site.login.welcome_back') }}</h1>
    <p class="auth-card__sub">{{ __('site.login.subtitle') }}</p>

    <form method="POST" action="{{ route('login') }}" data-validate novalidate>
        @csrf

        {{-- Email --}}
        <div class="mb-3">
            <label for="email" class="form-label">{{ __('site.login.email') }}</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                <input type="text"
                       class="form-control @error('email') is-invalid @enderror"
                       id="email" name="email" value="{{ old('email') }}"
                       data-validation-engine="validate[required,custom[email],maxSize[255]]"
                       placeholder="ornek@mail.com" autofocus autocomplete="email">
                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Password --}}
        <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between">
                <label for="password" class="form-label mb-0">{{ __('site.login.password') }}</label>
                <a href="{{ route('password.request') }}" class="small">{{ __('site.login.forgot') }}</a>
            </div>
            <div class="input-group mt-2">
                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                <input type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       id="password" name="password"
                       data-validation-engine="validate[required,minSize[8]]"
                       placeholder="••••••••" autocomplete="current-password">
                @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Remember --}}
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="remember" name="remember" data-fv-ignore {{ old('remember') ? 'checked' : '' }}>
            <label class="form-check-label" for="remember">{{ __('site.login.remember') }}</label>
        </div>

        <x-recaptcha />

        <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-right-to-bracket"></i> {{ __('site.auth.login_long') }}
        </button>
    </form>

    <p class="text-center text-muted mt-4 mb-0">
        Hesabınız yok mu? <a href="{{ route('register') }}" class="fw-semibold">{{ __('site.auth.register') }}</a>
    </p>
@endsection
