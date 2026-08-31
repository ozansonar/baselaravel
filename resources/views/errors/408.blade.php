@extends('layouts.app')

@section('title', '408 - ' . __('site.errors.408_title'))
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '408',
        'title'   => __('site.errors.408_title'),
        'message' => __('site.errors.408'),
    ])
@endsection
