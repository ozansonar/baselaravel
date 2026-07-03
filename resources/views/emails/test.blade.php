@extends('emails.layout')

@section('content')
    <p class="em-greeting">Test E-postası</p>
    <h1 class="em-heading">{{ $mailSubject }}</h1>

    <p class="em-text">{{ $mailBody }}</p>

    <hr class="em-divider">

    <p class="em-text-sm">Bu e-posta, SMTP ayarlarınızın doğru çalışıp çalışmadığını test etmek amacıyla gönderilmiştir.</p>
@endsection
