@extends('layouts.app')

@section('title', '429 - ' . __('site.errors.429_title'))
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '429',
        'title'   => __('site.errors.429_title'),
        'message' => __('site.errors.429'),
    ])
@endsection
