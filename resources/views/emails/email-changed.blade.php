@extends('emails.layout')

@section('content')
    <p class="em-greeting">{{ __('mail.common.security') }}</p>
    <h1 class="em-heading">{{ __('mail.email_changed.heading') }}</h1>

    <p class="em-text">
        {!! __('mail.email_changed.lead', [
            'name' => e($userName),
            'date' => '<strong>' . e($changedAt) . '</strong>',
        ]) !!}
    </p>

    <table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-highlight-td">
                <p class="em-text-sm">{{ __('mail.email_changed.previous') }}: <strong>{{ $previousEmail }}</strong></p>
                <p class="em-text-sm">{{ __('mail.email_changed.new') }}: <strong>{{ $maskedNewEmail }}</strong></p>
            </td>
        </tr>
    </table>

    <p class="em-text">{{ __('mail.email_changed.was_you') }}</p>

    <hr class="em-divider">

    {{-- Anahtar <strong> taşıyor: uyarının ağırlık merkezi cümlenin neresine
         düştüğü dile göre değişiyor. --}}
    <p class="em-text">{!! __('mail.email_changed.was_not_you') !!}</p>

    <p class="em-text">
        <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
    </p>

    <p class="em-text-sm">{{ __('mail.email_changed.last_mail') }}</p>
@endsection
