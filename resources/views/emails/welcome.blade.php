@extends('emails.layout')

@section('content')
    <p class="em-greeting">Merhaba</p>
    <h1 class="em-heading">Hoş Geldiniz, {{ $user->full_name }}! &#127793;</h1>

    <p class="em-text">
        {{ \App\Models\Setting::getValue('site_name', config('app.name')) }} ailesine katıldığınız için teşekkür ederiz.
        Aramıza hoş geldiniz! Size yardımcı olmaktan mutluluk duyarız.
    </p>

    <hr class="em-divider">

    <p class="em-heading-sm">Hesabınızla neler yapabilirsiniz?</p>

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-feature-td">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="em-feature-icon-td">&#128100;</td>
                        <td class="em-feature-text-td"><strong>Profil bilgilerinizi</strong> yönetin</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="em-feature-td">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="em-feature-icon-td">&#128196;</td>
                        <td class="em-feature-text-td"><strong>İçeriklerimizi</strong> keşfedin</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="em-feature-td">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="em-feature-icon-td">&#128227;</td>
                        <td class="em-feature-text-td"><strong>Yeni yazılardan</strong> haberdar olun</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="em-feature-td">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="em-feature-icon-td">&#9993;</td>
                        <td class="em-feature-text-td"><strong>Bizimle iletişimde</strong> kalın</td>
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
        İyi çalışmalar dileriz!
    </p>
@endsection
