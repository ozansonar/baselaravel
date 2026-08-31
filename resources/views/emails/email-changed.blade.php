@extends('emails.layout')

@section('content')
    <p class="em-greeting">Güvenlik</p>
    <h1 class="em-heading">Hesabınızın e-posta adresi değiştirildi</h1>

    <p class="em-text">
        Merhaba {{ $userName }}, hesabınızın e-posta adresi <strong>{{ $changedAt }}</strong>
        tarihinde değiştirildi.
    </p>

    <table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-highlight-td">
                <p class="em-text-sm">Eski adres: <strong>{{ $previousEmail }}</strong></p>
                <p class="em-text-sm">Yeni adres: <strong>{{ $maskedNewEmail }}</strong></p>
            </td>
        </tr>
    </table>

    <p class="em-text">
        Bu değişikliği siz yaptıysanız yapmanız gereken bir şey yok; bu bilgilendirme
        mailini yok sayabilirsiniz.
    </p>

    <hr class="em-divider">

    <p class="em-text">
        <strong>Bu değişikliği siz yapmadıysanız hesabınız başkasının eline geçmiş
        olabilir.</strong> Bildirimler ve şifre sıfırlama bağlantıları artık yeni
        adrese gideceği için hesabı kendi başınıza geri almanız mümkün olmayabilir.
        Vakit kaybetmeden bize ulaşın:
    </p>

    <p class="em-text">
        <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
    </p>

    <p class="em-text-sm">
        Bu mail, adresi hesabınızdan kaldırılmadan önce son kez size gönderildi.
    </p>
@endsection
