@extends('layouts.auth')

@section('title', __('site.verify.title'))

@section('robots', 'noindex, nofollow')

@section('content')
    <h1 class="auth-card__title">{{ __('site.verify.title') }}</h1>
    <p class="auth-card__sub">
        {!! __('site.verify.sent_to', ['email' => '<strong>' . e(auth()->user()->email) . '</strong>']) !!}
    </p>

    @if(session('success'))
        <div class="alert alert-success" role="alert">
            <i class="fa-solid fa-circle-check me-1"></i> {{ session('success') }}
        </div>
    @endif

    <p class="text-muted small">{{ __('site.verify.spam') }}</p>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-paper-plane"></i> {{ __('site.verify.resend') }}
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn btn-link w-100 text-muted">
            {{ __('site.verify.logout') }}
        </button>
    </form>
@endsection
