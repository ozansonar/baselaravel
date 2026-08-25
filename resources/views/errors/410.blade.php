@extends('layouts.app')

@section('title', '410 - İçerik Kaldırıldı')
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '410',
        'title'   => 'İçerik Kaldırıldı',
        'message' => 'Aradığınız içerik kalıcı olarak kaldırılmış görünüyor.',
    ])
@endsection
