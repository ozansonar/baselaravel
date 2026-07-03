@extends('emails.layout')

@section('content')
    <p class="em-greeting">İletişim</p>
    <h1 class="em-heading">Yeni İletişim Mesajı &#128233;</h1>

    <p class="em-text">
        Web sitesi üzerinden yeni bir iletişim mesajı alındı.
    </p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">Gönderen:</span> {{ $contactMessage->name }}</p>
                <p class="em-info-row"><span class="em-info-label">E-posta:</span> {{ $contactMessage->email }}</p>
                @if ($contactMessage->phone)
                    <p class="em-info-row"><span class="em-info-label">Telefon:</span> {{ $contactMessage->phone }}</p>
                @endif
                <p class="em-info-row"><span class="em-info-label">Konu:</span> {{ $contactMessage->subject }}</p>
                <p class="em-info-row"><span class="em-info-label">Tarih:</span> {{ $contactMessage->created_at->format('d.m.Y H:i') }}</p>
            </td>
        </tr>
    </table>

    <hr class="em-divider">

    <p class="em-heading-sm">Mesaj İçeriği</p>

    <p class="em-text">{{ $contactMessage->message }}</p>

    <hr class="em-divider">

    <p class="em-text">
        Bu mesajı yönetim panelinden görüntüleyebilir ve yanıtlayabilirsiniz.
    </p>

    <div class="em-btn-wrap">
        <a href="{{ url('/admin/contact-messages/' . $contactMessage->id) }}" class="em-btn">Mesajı Görüntüle</a>
    </div>
@endsection
