@extends('emails.layout')

@section('content')
    <p class="em-greeting">Merhaba</p>
    <h1 class="em-heading">E-posta Adresinizi Doğrulayın</h1>

    <p class="em-text">
        {{ $user->full_name }}, hesabınızı kullanmaya başlamak için aşağıdaki
        butona tıklayarak e-posta adresinizi doğrulayın.
    </p>

    <div class="em-btn-wrap">
        <a href="{{ $verificationUrl }}" class="em-btn">E-postamı Doğrula</a>
    </div>

    <hr class="em-divider">

    <p class="em-text-sm">
        Buton çalışmıyorsa bu adresi tarayıcınıza yapıştırabilirsiniz:<br>
        <span style="word-break: break-all;">{{ $verificationUrl }}</span>
    </p>

    <p class="em-text-sm">
        Bu hesabı siz oluşturmadıysanız bu e-postayı yok sayabilirsiniz.
    </p>
@endsection
