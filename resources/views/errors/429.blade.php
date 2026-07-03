@extends('layouts.app')

@section('title', '429 - Çok Fazla İstek')
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '429',
        'title'   => 'Çok Fazla İstek',
        'message' => 'Kısa sürede çok fazla istek gönderdiniz. Lütfen biraz bekleyip tekrar deneyin.',
    ])
@endsection
