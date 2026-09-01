@extends('emails.layout')

@section('content')
    <p class="em-greeting">{{ __('mail.common.security') }}</p>
    <h1 class="em-heading">{{ __('mail.reset.heading') }} &#128274;</h1>

    <p class="em-text">{{ __('mail.reset.lead') }}</p>

    <div class="em-btn-wrap">
        <a href="{{ $resetUrl }}" class="em-btn">&#128275; {{ __('mail.reset.button') }}</a>
    </div>

    <table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-highlight-td">
                <p class="em-text-sm">&#9200; {{ __('mail.reset.expires', ['minutes' => 60]) }}</p>
            </td>
        </tr>
    </table>

    <hr class="em-divider">

    <p class="em-text">{{ __('mail.reset.ignore') }}</p>

    <p class="em-text-sm">
        {{ __('mail.reset.fallback') }}<br>
        <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
    </p>
@endsection
