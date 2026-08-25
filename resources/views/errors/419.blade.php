@extends('layouts.app')

@section('title', '419 - Oturum Süresi Doldu')
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '419',
        'title'   => 'Oturum Süresi Doldu',
        'message' => 'Güvenlik nedeniyle oturumunuz sonlandı. Lütfen sayfayı yenileyip tekrar deneyin.',
    ])
@endsection
