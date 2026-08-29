@extends('emails.layout')

@section('content')
    <p class="em-greeting">Merhaba {{ $comment->name }}</p>
    <h1 class="em-heading">Yorumunuz Alındı &#9989;</h1>

    <p class="em-text">
        Yorumunuz bize ulaştı ve değerlendirme aşamasında. Onaylandığında
        yazının altında yayınlanacak ve size ayrıca haber vereceğiz.
    </p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">Yazı:</span> {{ $comment->post?->title ?? '-' }}</p>
                <p class="em-info-row"><span class="em-info-label">Tarih:</span> {{ $comment->created_at?->format('d.m.Y H:i') ?? '-' }}</p>
            </td>
        </tr>
    </table>

    <hr class="em-divider">

    <p class="em-heading-sm">Yorumunuz</p>

    <p class="em-text">{{ $comment->body }}</p>

    <p class="em-text-sm">
        Bu yorumu siz yazmadıysanız bu e-postayı yok sayabilirsiniz.
    </p>
@endsection
