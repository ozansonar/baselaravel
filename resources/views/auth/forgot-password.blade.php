@extends('layouts.auth')

@section('title', __('site.password.forgot_title'))

@section('content')
    <h1 class="auth-card__title">{{ __('site.password.forgot_title') }}</h1>
    <p class="auth-card__sub">{{ __('site.password.forgot_subtitle') }}</p>

    <form method="POST" action="{{ route('password.email') }}" data-validate novalidate>
        @csrf

        {{-- Email --}}
        <div class="mb-3">
            <label for="email" class="form-label">{{ __('site.login.email') }}</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                <input type="text"
                       class="form-control @error('email') is-invalid @enderror"
                       id="email" name="email" value="{{ old('email') }}"
                       data-validation-engine="validate[required,custom[email],maxSize[191]]"
                       placeholder="{{ __('site.password.email_ph') }}" autofocus autocomplete="email">
                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <x-recaptcha />

        <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-paper-plane"></i> {{ __('site.password.send_link') }}
        </button>
    </form>

    <p class="text-center text-muted mt-4 mb-0">
        <a href="{{ route('login') }}" class="fw-semibold">
            <i class="fa-solid fa-arrow-left"></i> {{ __('site.password.back_to_login') }}
        </a>
    </p>
@endsection
