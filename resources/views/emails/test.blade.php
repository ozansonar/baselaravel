@extends('emails.layout')

@section('content')
    <p class="em-greeting">{{ __('mail.test.eyebrow') }}</p>
    <h1 class="em-heading">{{ $mailSubject }}</h1>

    <p class="em-text">{{ $mailBody }}</p>

    <hr class="em-divider">

    <p class="em-text-sm">{{ __('mail.test.outro') }}</p>
@endsection
