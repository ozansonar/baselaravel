@extends('emails.layout')

@section('content')
    <p class="em-greeting">Merhaba {{ $comment->name }}</p>
    <h1 class="em-heading">Yorumunuz Yayınlandı &#127881;</h1>

    <p class="em-text">
        Yorumunuz onaylandı ve artık yazının altında herkes tarafından
        görülebiliyor. Katkınız için teşekkür ederiz.
    </p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">Yazı:</span> {{ $comment->post?->title ?? '-' }}</p>
            </td>
        </tr>
    </table>

    <hr class="em-divider">

    <p class="em-heading-sm">Yorumunuz</p>

    <p class="em-text">{{ $comment->body }}</p>
@endsection
