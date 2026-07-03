@extends('emails.layout')

@section('content')
    <p class="em-greeting">Güvenlik</p>
    <h1 class="em-heading">Şifre Sıfırlama Talebi &#128274;</h1>

    <p class="em-text">
        Merhaba, hesabınız için bir şifre sıfırlama talebi aldık.
        Şifrenizi sıfırlamak için aşağıdaki butona tıklayın:
    </p>

    <div class="em-btn-wrap">
        <a href="{{ $resetUrl }}" class="em-btn">&#128275; Şifremi Sıfırla</a>
    </div>

    <table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-highlight-td">
                <p class="em-text-sm">&#9200; Bu şifre sıfırlama bağlantısı <strong>60 dakika</strong> içinde geçerliliğini yitirecektir.</p>
            </td>
        </tr>
    </table>

    <hr class="em-divider">

    <p class="em-text">
        Eğer şifre sıfırlama talebinde bulunmadıysanız, bu e-postayı görmezden gelebilirsiniz.
        Hesabınız güvende.
    </p>

    <p class="em-text-sm">
        Butona tıklayamıyorsanız aşağıdaki bağlantıyı tarayıcınıza kopyalayıp yapıştırın:<br>
        <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
    </p>
@endsection
