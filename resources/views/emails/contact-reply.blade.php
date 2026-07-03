@extends('emails.layout')

@section('content')
    <p class="em-greeting">Merhaba {{ $contactMessage->name }},</p>
    <h1 class="em-heading">Mesajınıza Yanıt &#9993;</h1>

    <p class="em-text">
        İletişim formundan gönderdiğiniz mesajınız için teşekkür ederiz. Yanıtımız aşağıdadır:
    </p>

    <table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-highlight-td">
                <p class="em-text">{!! nl2br(e($replyBody)) !!}</p>
            </td>
        </tr>
    </table>

    <hr class="em-divider">

    <p class="em-heading-sm">Orijinal Mesajınız</p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">Konu:</span> {{ $contactMessage->subject }}</p>
                <p class="em-info-row"><span class="em-info-label">Tarih:</span> {{ $contactMessage->created_at->format('d.m.Y H:i') }}</p>
            </td>
        </tr>
    </table>

    <p class="em-text" style="color: {{ $themeMuted }}; font-style: italic;">{{ $contactMessage->message }}</p>

    <hr class="em-divider">

    <p class="em-text">
        Başka sorularınız varsa bu e-postayı yanıtlayabilir veya web sitemiz üzerinden bize ulaşabilirsiniz.
    </p>
@endsection
