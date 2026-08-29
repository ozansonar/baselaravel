@extends('emails.layout')

@section('content')
    <p class="em-greeting">Yorum</p>
    <h1 class="em-heading">Yeni Yorum Geldi &#128172;</h1>

    <p class="em-text">
        Bir blog yazısına yeni bir yorum yapıldı. Yorum onaya düştü; onaylanana
        kadar sitede görünmüyor.
    </p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">Yazan:</span> {{ $comment->name }}</p>
                <p class="em-info-row"><span class="em-info-label">E-posta:</span> {{ $comment->email }}</p>
                <p class="em-info-row"><span class="em-info-label">Yazı:</span> {{ $comment->post?->title ?? '-' }}</p>
                <p class="em-info-row"><span class="em-info-label">Tarih:</span> {{ $comment->created_at?->format('d.m.Y H:i') ?? '-' }}</p>
            </td>
        </tr>
    </table>

    <hr class="em-divider">

    <p class="em-heading-sm">Yorum İçeriği</p>

    <p class="em-text">{{ $comment->body }}</p>

    <div class="em-btn-wrap">
        <a href="{{ url('/admin/blog-comments/' . $comment->id) }}" class="em-btn">Yorumu İncele</a>
    </div>
@endsection
