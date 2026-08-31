@extends('layouts.auth')

@section('title', __('site.two_factor.challenge_title'))

@section('content')

    <h1 class="auth-card__title">{{ __('site.two_factor.challenge_title') }}</h1>
    <p class="auth-card__sub">{{ __('site.two_factor.challenge_lead') }}</p>

    <form action="{{ route('login.two-factor.verify') }}" method="POST" data-validate novalidate>
        @csrf

        <div class="mb-3">
            <label class="form-label" for="code">{{ __('site.two_factor.code_label') }}</label>
            <input type="text" class="form-control @error('code') is-invalid @enderror"
                   id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus
                   placeholder="000000"
                   data-validation-engine="validate[required,maxSize[32]]">
            @error('code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">{{ __('site.two_factor.challenge_hint') }}</div>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-right-to-bracket"></i> {{ __('site.two_factor.challenge_btn') }}
        </button>
    </form>

    <p class="text-center mt-4 mb-0">
        <a href="{{ localized_route('login') }}">{{ __('site.two_factor.back_to_login') }}</a>
    </p>

@endsection
