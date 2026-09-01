@extends('emails.layout')

@section('content')
    <p class="em-greeting">{{ __('mail.common.security') }}</p>
    <h1 class="em-heading">{{ __('mail.reset_code.heading') }} &#128274;</h1>

    <p class="em-text">{{ __('mail.reset_code.lead') }}</p>

    <table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-highlight-td" align="center">
                <p class="em-heading" style="letter-spacing: 8px; margin: 0;">{{ $code }}</p>
            </td>
        </tr>
    </table>

    <p class="em-text-sm">
        &#9200; {{ __('mail.reset_code.expires', ['minutes' => $expiresInMinutes]) }}
    </p>

    <hr class="em-divider">

    <p class="em-text">{{ __('mail.reset_code.ignore') }}</p>
@endsection
