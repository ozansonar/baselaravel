@extends('emails.layout')

@section('content')
    <p class="em-greeting">{{ __('mail.contact_notification.eyebrow') }}</p>
    <h1 class="em-heading">{{ __('mail.contact_notification.heading') }} &#128233;</h1>

    <p class="em-text">{{ __('mail.contact_notification.lead') }}</p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">{{ __('mail.contact_notification.from') }}:</span> {{ $contactMessage->name }}</p>
                <p class="em-info-row"><span class="em-info-label">{{ __('mail.common.email') }}:</span> {{ $contactMessage->email }}</p>
                @if ($contactMessage->phone)
                    <p class="em-info-row"><span class="em-info-label">{{ __('mail.contact_notification.phone') }}:</span> {{ $contactMessage->phone }}</p>
                @endif
                <p class="em-info-row"><span class="em-info-label">{{ __('mail.common.subject') }}:</span> {{ $contactMessage->subject }}</p>
                <p class="em-info-row"><span class="em-info-label">{{ __('mail.common.date') }}:</span> {{ $contactMessage->created_at->format('d.m.Y H:i') }}</p>
            </td>
        </tr>
    </table>

    <hr class="em-divider">

    <p class="em-heading-sm">{{ __('mail.contact_notification.body') }}</p>

    <p class="em-text">{{ $contactMessage->message }}</p>

    <hr class="em-divider">

    <p class="em-text">{{ __('mail.contact_notification.outro') }}</p>

    <div class="em-btn-wrap">
        <a href="{{ url('/admin/contact-messages/' . $contactMessage->id) }}" class="em-btn">{{ __('mail.contact_notification.button') }}</a>
    </div>
@endsection
