@extends('emails.layout')

@section('content')
    <p class="em-greeting">{{ __('mail.comment_admin.eyebrow') }}</p>
    <h1 class="em-heading">{{ __('mail.comment_admin.heading') }} &#128172;</h1>

    <p class="em-text">{{ __('mail.comment_admin.lead') }}</p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">{{ __('mail.comment_admin.author') }}:</span> {{ $comment->name }}</p>
                <p class="em-info-row"><span class="em-info-label">{{ __('mail.common.email') }}:</span> {{ $comment->email }}</p>
                <p class="em-info-row"><span class="em-info-label">{{ __('mail.common.post') }}:</span> {{ $comment->post?->title ?? '-' }}</p>
                <p class="em-info-row"><span class="em-info-label">{{ __('mail.common.date') }}:</span> {{ $comment->created_at?->format('d.m.Y H:i') ?? '-' }}</p>
            </td>
        </tr>
    </table>

    <hr class="em-divider">

    <p class="em-heading-sm">{{ __('mail.comment_admin.body') }}</p>

    <p class="em-text">{{ $comment->body }}</p>

    <div class="em-btn-wrap">
        <a href="{{ url('/admin/blog-comments/' . $comment->id) }}" class="em-btn">{{ __('mail.comment_admin.button') }}</a>
    </div>
@endsection
