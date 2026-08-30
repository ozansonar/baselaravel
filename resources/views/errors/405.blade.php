@extends('layouts.app')

@section('title', '405 - ' . __('site.errors.405_title'))
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '405',
        'title'   => __('site.errors.405_title'),
        'message' => __('site.errors.405'),
    ])
@endsection
