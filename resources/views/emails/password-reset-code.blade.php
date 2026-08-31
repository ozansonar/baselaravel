@extends('emails.layout')

@section('content')
    <p class="em-greeting">Güvenlik</p>
    <h1 class="em-heading">Şifre Sıfırlama Kodunuz &#128274;</h1>

    <p class="em-text">
        Merhaba, hesabınız için bir şifre sıfırlama talebi aldık.
        Uygulamadaki alana aşağıdaki kodu girin:
    </p>

    <table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-highlight-td" align="center">
                <p class="em-heading" style="letter-spacing: 8px; margin: 0;">{{ $code }}</p>
            </td>
        </tr>
    </table>

    <p class="em-text-sm">
        &#9200; Bu kod <strong>{{ $expiresInMinutes }} dakika</strong> içinde geçerliliğini yitirecektir.
    </p>

    <hr class="em-divider">

    <p class="em-text">
        Eğer şifre sıfırlama talebinde bulunmadıysanız, bu e-postayı görmezden gelebilirsiniz.
        Kodu kimseyle paylaşmayın; ekibimiz sizden bu kodu asla istemez.
    </p>
@endsection
