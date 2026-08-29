@extends('layouts.auth')

@section('title', __('site.register.title'))

@section('content')
    <h1 class="auth-card__title">{{ __('site.register.join_us') }}</h1>
    <p class="auth-card__sub">{{ __('site.register.free_account') }}</p>

    <form method="POST" action="{{ route('register') }}" data-validate novalidate>
        @csrf

        {{-- First / Last name --}}
        <div class="row g-3 mb-3">
            <div class="col-6">
                <label for="first_name" class="form-label">{{ __('site.register.first_name') }}</label>
                <input type="text"
                       class="form-control @error('first_name') is-invalid @enderror"
                       id="first_name" name="first_name" value="{{ old('first_name') }}"
                       data-validation-engine="validate[required,custom[letters],minSize[2],maxSize[50]]"
                       data-fv-mask="letters"
                       placeholder="{{ __('site.register.first_name_ph') }}" autofocus autocomplete="given-name">
                @error('first_name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-6">
                <label for="last_name" class="form-label">{{ __('site.register.last_name') }}</label>
                <input type="text"
                       class="form-control @error('last_name') is-invalid @enderror"
                       id="last_name" name="last_name" value="{{ old('last_name') }}"
                       data-validation-engine="validate[required,custom[letters],minSize[2],maxSize[50]]"
                       data-fv-mask="letters"
                       placeholder="{{ __('site.register.last_name_ph') }}" autocomplete="family-name">
                @error('last_name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Email --}}
        <div class="mb-3">
            <label for="email" class="form-label">{{ __('site.login.email') }}</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                <input type="text"
                       class="form-control @error('email') is-invalid @enderror"
                       id="email" name="email" value="{{ old('email') }}"
                       data-validation-engine="validate[required,custom[email],maxSize[191]]"
                       placeholder="{{ __('site.register.email_ph') }}" autocomplete="email">
                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Phone (optional) --}}
        <div class="mb-3">
            <label for="phone" class="form-label">{{ __('site.register.phone') }} <span class="text-muted fw-normal">{{ __('site.misc.optional') }}</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                {{-- Sunucu burada biçim dayatıyor (regex: rakam, boşluk, - + parantez),
                     bu yüzden custom[phone] var. Maske yok: digits maskesi "+90"ı
                     ve parantezleri silerdi, oysa sunucu ikisini de kabul ediyor. --}}
                <input type="text"
                       class="form-control @error('phone') is-invalid @enderror"
                       id="phone" name="phone" value="{{ old('phone') }}"
                       data-validation-engine="validate[custom[phone],maxSize[20]]"
                       placeholder="{{ __('site.register.phone_ph') }}" autocomplete="tel">
                @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Password --}}
        <div class="mb-3">
            <label for="password" class="form-label">{{ __('site.login.password') }}</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                <input type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       id="password" name="password"
                       data-validation-engine="validate[required,minSize[8]]"
                       placeholder="••••••••" autocomplete="new-password">
                @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-text">{{ __('site.register.password_hint') }}</div>
        </div>

        {{-- Password confirmation --}}
        <div class="mb-3">
            <label for="password_confirmation" class="form-label">{{ __('site.register.password_again') }}</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                <input type="password"
                       class="form-control"
                       id="password_confirmation" name="password_confirmation"
                       data-validation-engine="validate[required,equals[password]]"
                       placeholder="••••••••" autocomplete="new-password">
            </div>
        </div>

        <x-recaptcha />

        <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-user-plus"></i> {{ __('site.auth.register') }}
        </button>
    </form>

    <p class="text-center text-muted mt-4 mb-0">
        {{ __('site.register.have_account') }} <a href="{{ route('login') }}" class="fw-semibold">{{ __('site.auth.login_long') }}</a>
    </p>
@endsection
