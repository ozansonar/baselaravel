@extends('emails.layout')

@section('content')
    <p class="em-greeting">{{ __('mail.common.greeting') }} {{ $comment->name }}</p>
    <h1 class="em-heading">{{ __('mail.comment_approved.heading') }} &#127881;</h1>

    <p class="em-text">{{ __('mail.comment_approved.lead') }}</p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">{{ __('mail.common.post') }}:</span> {{ $comment->post?->title ?? '-' }}</p>
            </td>
        </tr>
    </table>

    <hr class="em-divider">

    <p class="em-heading-sm">{{ __('mail.comment_approved.body') }}</p>

    <p class="em-text">{{ $comment->body }}</p>
@endsection
