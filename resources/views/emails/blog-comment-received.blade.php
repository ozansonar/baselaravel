@extends('emails.layout')

@section('content')
    <p class="em-greeting">{{ __('mail.common.greeting') }} {{ $comment->name }}</p>
    <h1 class="em-heading">{{ __('mail.comment_received.heading') }} &#9989;</h1>

    <p class="em-text">{{ __('mail.comment_received.lead') }}</p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">{{ __('mail.common.post') }}:</span> {{ $comment->post?->title ?? '-' }}</p>
                <p class="em-info-row"><span class="em-info-label">{{ __('mail.common.date') }}:</span> {{ $comment->created_at?->format('d.m.Y H:i') ?? '-' }}</p>
            </td>
        </tr>
    </table>

    <hr class="em-divider">

    <p class="em-heading-sm">{{ __('mail.comment_received.body') }}</p>

    <p class="em-text">{{ $comment->body }}</p>

    <p class="em-text-sm">{{ __('mail.comment_received.ignore') }}</p>
@endsection
