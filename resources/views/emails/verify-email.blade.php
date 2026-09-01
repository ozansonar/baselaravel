@extends('emails.layout')

@section('content')
    <p class="em-greeting">{{ __('mail.common.greeting') }}</p>
    <h1 class="em-heading">{{ __('mail.verify.heading') }}</h1>

    <p class="em-text">{{ __('mail.verify.lead', ['name' => $user->full_name]) }}</p>

    <div class="em-btn-wrap">
        <a href="{{ $verificationUrl }}" class="em-btn">{{ __('mail.verify.button') }}</a>
    </div>

    <hr class="em-divider">

    <p class="em-text-sm">
        {{ __('mail.verify.fallback') }}<br>
        <span style="word-break: break-all;">{{ $verificationUrl }}</span>
    </p>

    <p class="em-text-sm">{{ __('mail.verify.ignore') }}</p>
@endsection
