@extends('layouts.app')

@section('title', '403 - ' . __('site.errors.403_title'))
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '403',
        'title'   => __('site.errors.403_title'),
        'message' => __('site.errors.403'),
    ])
@endsection
