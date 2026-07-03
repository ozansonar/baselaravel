@extends('layouts.app')

@section('title', '403 - Erişim Reddedildi')
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '403',
        'title'   => 'Erişim Reddedildi',
        'message' => 'Bu sayfaya erişim yetkiniz bulunmuyor.',
    ])
@endsection
