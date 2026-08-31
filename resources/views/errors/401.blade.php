@extends('layouts.app')

@section('title', '401 - ' . __('site.errors.401_title'))
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '401',
        'title'   => __('site.errors.401_title'),
        'message' => __('site.errors.401'),
    ])
@endsection
