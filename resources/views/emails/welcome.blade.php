@extends('emails.layout')

@section('content')
    <p class="em-greeting">Merhaba</p>
    <h1 class="em-heading">Hoş Geldiniz, {{ $user->full_name }}! &#127793;</h1>

    <p class="em-text">
        {{ \App\Models\Setting::getValue('site_name', config('app.name')) }} ailesine katıldığınız için teşekkür ederiz.
        Çiftliğimizden sofranıza en taze, en doğal ürünleri ulaştırmak için sabırsızlanıyoruz.
    </p>

    <hr class="em-divider">

    <p class="em-heading-sm">Hesabınızla neler yapabilirsiniz?</p>

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-feature-td">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="em-feature-icon-td">&#127793;</td>
                        <td class="em-feature-text-td"><strong>Taze ürünlere</strong> göz atın</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="em-feature-td">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="em-feature-icon-td">&#128722;</td>
                        <td class="em-feature-text-td"><strong>Kolay ve hızlı</strong> sipariş verin</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="em-feature-td">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="em-feature-icon-td">&#128230;</td>
                        <td class="em-feature-text-td"><strong>Siparişlerinizi</strong> adım adım takip edin</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="em-feature-td">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="em-feature-icon-td">&#127968;</td>
                        <td class="em-feature-text-td"><strong>Teslimat adreslerinizi</strong> kaydedin</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="em-btn-wrap">
        <a href="{{ url('/') }}" class="em-btn">&#127807; Siteyi Keşfet</a>
    </div>

    <hr class="em-divider">

    <p class="em-text">
        Herhangi bir sorunuz varsa bize iletişim sayfamızdan ulaşabilirsiniz.
        Sağlıklı ve doğal günler dileriz!
    </p>
@endsection
