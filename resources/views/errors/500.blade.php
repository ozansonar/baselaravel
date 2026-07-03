@extends('layouts.app')

@section('title', '500 - Sunucu Hatası')
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '500',
        'title'   => 'Sunucu Hatası',
        'message' => 'Beklenmedik bir hata oluştu. Ekibimiz bilgilendirildi, en kısa sürede çözülecektir.',
    ])
@endsection
