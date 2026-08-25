@extends('layouts.app')

@section('title', '410 - ' . __('site.errors.410_title'))
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '410',
        'title'   => __('site.errors.410_title'),
        'message' => __('site.errors.410'),
    ])
@endsection
