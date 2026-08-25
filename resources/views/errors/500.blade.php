@extends('layouts.app')

@section('title', '500 - ' . __('site.errors.500_title'))
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '500',
        'title'   => __('site.errors.500_title'),
        'message' => __('site.errors.500'),
    ])
@endsection
